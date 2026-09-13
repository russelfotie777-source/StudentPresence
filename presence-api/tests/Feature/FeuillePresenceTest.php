<?php

namespace Tests\Feature;

use App\Enums\FormationType;
use App\Models\Filiere;
use App\Models\Parametre;
use App\Models\PresenceEtudiant;
use App\Models\Salle;
use App\Models\Seance;
use App\Models\Semaine;
use App\Models\User;
use App\Services\ListeHebdomadaire;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feuille de présence d'une salle sur une semaine — la source commune de la
 * grille admin et du PDF officiel.
 */
class FeuillePresenceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Salle $salle;

    private Semaine $semaine;

    protected function setUp(): void
    {
        parent::setUp();

        // Jeudi 17 septembre 2026, 11:00 à Douala : la séance de 08h est passée,
        // celle de 14h est à venir.
        Carbon::setTestNow(Carbon::parse('2026-09-17 11:00:00', 'Africa/Douala'));

        $this->admin = User::factory()->admin()->create();
        $filiere = Filiere::factory()->create();
        $this->salle = Salle::factory()->create(['filiere_id' => $filiere->id, 'formation' => FormationType::FI]);
        $this->semaine = Semaine::factory()->create(['numero' => 1, 'date_debut' => '2026-09-14', 'date_fin' => '2026-09-20']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function seance(array $attributs = []): Seance
    {
        return Seance::factory()->create([
            'salle_id' => $this->salle->id,
            'semaine_id' => $this->semaine->id,
            'date_seance' => '2026-09-14',
            'jour' => 'LUNDI',
            'heure_debut' => '08:00',
            'heure_fin' => '10:00',
            ...$attributs,
        ]);
    }

    private function feuille(): array
    {
        return $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/salles/{$this->salle->id}/feuille-presence?semaine_id={$this->semaine->id}")
            ->assertOk()
            ->json();
    }

    public function test_sheet_lists_the_room_students_and_the_week_sessions(): void
    {
        $fi = User::factory()->etudiant($this->salle)->create(['name' => 'Bella']);
        $fm = User::factory()->etudiant($this->salle)->create(['name' => 'Awa', 'formation' => FormationType::FM]);
        $delegue = User::factory()->delegue($this->salle)->create(['name' => 'Chantal']);
        User::factory()->etudiant(Salle::factory()->create())->create(['name' => 'Ailleurs']);

        $lundi = $this->seance();
        $this->seance(['date_seance' => '2026-09-24', 'jour' => 'JEUDI', 'semaine_id' => Semaine::factory()->create(['numero' => 2, 'date_debut' => '2026-09-21', 'date_fin' => '2026-09-27'])->id]);

        $feuille = $this->feuille();

        $this->assertSame(['Awa', 'Bella', 'Chantal'], array_column($feuille['etudiants'], 'name'), 'Ordre alphabétique, FM inclus, autre salle exclue.');
        $this->assertSame([$lundi->id], array_column($feuille['seances'], 'id'), 'Seules les séances de la semaine.');
        $this->assertSame('FM', $feuille['etudiants'][0]['formation']);
        $this->assertSame($delegue->id, $feuille['delegue']['id']);
        $this->assertSame('coche', $feuille['symboles']);
        $this->assertSame($fi->id, $feuille['etudiants'][1]['id']);
        $this->assertSame($fm->id, $feuille['etudiants'][0]['id']);
    }

    public function test_each_cell_reflects_the_check_in_or_the_validated_roll_call(): void
    {
        $present = User::factory()->etudiant($this->salle)->create(['name' => 'A Présent']);
        $absent = User::factory()->etudiant($this->salle)->create(['name' => 'B Absent']);
        $oublie = User::factory()->etudiant($this->salle)->create(['name' => 'C Oublié']);

        // Appel validé par le délégué : C n'a aucun pointage → absent.
        $validee = $this->seance(['presences_locked' => true]);
        PresenceEtudiant::create(['seance_id' => $validee->id, 'etudiant_id' => $present->id, 'etat' => 'present']);
        PresenceEtudiant::create(['seance_id' => $validee->id, 'etudiant_id' => $absent->id, 'etat' => 'absent']);

        // Passée mais jamais validée : seul A a pointé, on ne dit rien des autres.
        $nonValidee = $this->seance(['date_seance' => '2026-09-15', 'jour' => 'MARDI']);
        PresenceEtudiant::create(['seance_id' => $nonValidee->id, 'etudiant_id' => $present->id, 'etat' => 'present']);

        // Passée sans le moindre pointage, et à venir.
        $vide = $this->seance(['date_seance' => '2026-09-16', 'jour' => 'MERCREDI']);
        $aVenir = $this->seance(['date_seance' => '2026-09-17', 'jour' => 'JEUDI', 'heure_debut' => '14:00', 'heure_fin' => '16:00']);

        $feuille = $this->feuille();
        $statuts = array_column($feuille['seances'], 'statut', 'id');
        $this->assertSame('tenue', $statuts[$validee->id]);
        $this->assertSame('tenue', $statuts[$nonValidee->id], 'Un pointage suffit à considérer la séance tenue.');
        $this->assertSame('non_validee', $statuts[$vide->id]);
        $this->assertSame('a_venir', $statuts[$aVenir->id]);

        $cases = collect($feuille['etudiants'])->keyBy('name')->map(fn ($e) => $e['presences']);
        $this->assertSame('present', $cases['A Présent'][$validee->id]);
        $this->assertSame('absent', $cases['B Absent'][$validee->id]);
        $this->assertSame('absent', $cases['C Oublié'][$validee->id], 'Appel validé sans lui : absent.');
        $this->assertSame('present', $cases['A Présent'][$nonValidee->id]);
        $this->assertNull($cases['C Oublié'][$nonValidee->id], 'Appel non validé : on ne tranche pas.');
        $this->assertNull($cases['A Présent'][$aVenir->id]);
    }

    public function test_sheet_without_week_parameter_uses_the_current_week(): void
    {
        $this->seance();

        $feuille = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/salles/{$this->salle->id}/feuille-presence")
            ->assertOk()
            ->json();

        $this->assertSame($this->semaine->id, $feuille['semaine']['id']);
        $this->assertCount(1, $feuille['seances']);
    }

    public function test_sheet_is_admin_only(): void
    {
        $etudiant = User::factory()->etudiant($this->salle)->create();

        $this->actingAs($etudiant, 'sanctum')
            ->getJson("/api/salles/{$this->salle->id}/feuille-presence")
            ->assertForbidden();
    }

    // --- symboles -----------------------------------------------------------

    public function test_admin_chooses_how_presence_is_written(): void
    {
        $this->actingAs($this->admin, 'sanctum');

        $this->getJson('/api/parametres/liste-presence')->assertOk()->assertJsonPath('symboles', 'coche');

        $this->putJson('/api/parametres/liste-presence', ['symboles' => 'valeur'])
            ->assertOk()
            ->assertJsonPath('symboles', 'valeur');

        $this->assertSame('valeur', Parametre::symbolesPresence());
        $this->getJson("/api/salles/{$this->salle->id}/feuille-presence")->assertJsonPath('symboles', 'valeur');

        $this->putJson('/api/parametres/liste-presence', ['symboles' => 'emoji'])->assertUnprocessable();
    }

    public function test_pdf_data_carries_marks_per_day_in_the_chosen_symbols(): void
    {
        $etudiant = User::factory()->etudiant($this->salle)->create(['name' => 'Bebo']);
        $this->seance(['presences_locked' => true]); // matin, sans pointage : absent
        $soir = $this->seance(['heure_debut' => '14:00', 'heure_fin' => '16:00', 'presences_locked' => true]);
        PresenceEtudiant::create(['seance_id' => $soir->id, 'etudiant_id' => $etudiant->id, 'etat' => 'present']);
        $this->seance(['date_seance' => '2026-09-18', 'jour' => 'VENDREDI']); // à venir : case vide

        $donnees = app(ListeHebdomadaire::class)->pour($this->salle, $this->semaine, symboles: 'valeur');

        $jours = $donnees['etudiants']->first()['jours'];
        $this->assertSame(['absent', 'present'], $jours['LUNDI'], 'Deux séances le lundi : une marque chacune, dans l\'ordre horaire.');
        $this->assertSame([null], $jours['VENDREDI']);
        $this->assertSame([], $jours['MARDI']);
        $this->assertSame('valeur', $donnees['symboles']);
        $this->assertTrue($donnees['contient_marques']);

        $html = view('pdf.liste-hebdomadaire', $donnees)->render();
        $this->assertStringContainsString('<span class="marque absent">−1</span>', $html);
        $this->assertStringContainsString('<span class="marque present">+1</span>', $html);
        $this->assertStringNotContainsString('✓', $html);

        $html = view('pdf.liste-hebdomadaire', app(ListeHebdomadaire::class)->pour($this->salle, $this->semaine))->render();
        $this->assertStringContainsString('<span class="marque absent">✗</span>', $html);
        $this->assertStringContainsString('<span class="marque present">✓</span>', $html);

    }

    public function test_pdf_endpoint_accepts_a_symbol_override(): void
    {
        $this->seance();

        $this->actingAs($this->admin, 'sanctum')
            ->get("/api/salles/{$this->salle->id}/liste-presence.pdf?semaine_id={$this->semaine->id}&symboles=valeur")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/salles/{$this->salle->id}/liste-presence.pdf?semaine_id={$this->semaine->id}&symboles=croix")
            ->assertUnprocessable();
    }
}
