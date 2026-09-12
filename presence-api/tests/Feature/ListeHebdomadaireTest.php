<?php

namespace Tests\Feature;

use App\Enums\FormationType;
use App\Models\Filiere;
use App\Models\Niveau;
use App\Models\Salle;
use App\Models\Seance;
use App\Models\Semaine;
use App\Models\User;
use App\Services\ListeHebdomadaire;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Liste de présence hebdomadaire officielle d'une salle. On teste les
 * données assemblées plutôt que le PDF rendu : c'est ce qui est
 * déterministe, et c'est là que vivent les règles métier (qui figure sur la
 * liste, comment le sigle et le semestre sont dérivés).
 */
class ListeHebdomadaireTest extends TestCase
{
    use RefreshDatabase;

    private ListeHebdomadaire $liste;

    protected function setUp(): void
    {
        parent::setUp();
        $this->liste = app(ListeHebdomadaire::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_sigle_derives_from_initials_and_keeps_existing_acronyms(): void
    {
        $this->assertSame('GI', $this->liste->sigle('Génie Informatique'));
        $this->assertSame('GRT', $this->liste->sigle('Génie des Réseaux et Télécommunications'));
        $this->assertSame('GRT', $this->liste->sigle('GRT'));
        // Les mots vides (de, l') ne comptent pas, l'apostrophe non plus.
        $this->assertSame('GOL', $this->liste->sigle("Génie de l'Organisation Logistique"));
    }

    public function test_niveau_digit_becomes_a_roman_numeral(): void
    {
        $this->assertSame(2, $this->liste->chiffreDuNiveau('L2'));
        $this->assertSame(3, $this->liste->chiffreDuNiveau('DUT3'));
        $this->assertSame(1, $this->liste->chiffreDuNiveau('Licence'));
    }

    public function test_semester_follows_niveau_and_time_of_year(): void
    {
        Carbon::setTestNow('2026-10-15');
        $this->assertSame(3, $this->liste->semestreCourant(2), 'Niveau II en octobre : semestre 3.');
        $this->assertSame(5, $this->liste->semestreCourant(3));

        Carbon::setTestNow('2027-03-15');
        $this->assertSame(4, $this->liste->semestreCourant(2), 'Niveau II en mars : semestre 4.');
    }

    public function test_academic_year_switches_in_september(): void
    {
        Carbon::setTestNow('2026-09-12');
        $this->assertSame('2026-2027', $this->liste->anneeAcademiqueCourante());

        Carbon::setTestNow('2027-05-01');
        $this->assertSame('2026-2027', $this->liste->anneeAcademiqueCourante());
    }

    /**
     * Le cœur de la demande : les FM (migrants FA → FI) figurent sur la
     * liste d'une salle FI et y sont signalés ; un FA n'y figure pas.
     */
    public function test_fi_list_includes_fm_students_flagged_and_excludes_fa(): void
    {
        [$salle, $semaine] = $this->salleEtSemaine(FormationType::FI);

        User::factory()->etudiant($salle)->create(['name' => 'Zoé Fi', 'formation' => FormationType::FI]);
        User::factory()->etudiant($salle)->create(['name' => 'Ali Migrant', 'formation' => FormationType::FM]);
        User::factory()->etudiant($salle)->create(['name' => 'Bob Alternance', 'formation' => FormationType::FA]);
        User::factory()->delegue($salle)->create(['name' => 'Chef Délégué', 'formation' => FormationType::FI]);

        $donnees = $this->liste->pour($salle, $semaine);
        $etudiants = $donnees['etudiants'];

        // Ordre alphabétique, numérotés, délégué inclus (c'est un étudiant).
        $this->assertSame(['ALI MIGRANT', 'CHEF DÉLÉGUÉ', 'ZOÉ FI'], $etudiants->pluck('nom')->all());
        $this->assertSame([1, 2, 3], $etudiants->pluck('numero')->all());
        $this->assertTrue($etudiants->firstWhere('nom', 'ALI MIGRANT')['fm']);
        $this->assertFalse($etudiants->firstWhere('nom', 'ZOÉ FI')['fm']);
    }

    public function test_fa_list_only_includes_fa_students(): void
    {
        [$salle, $semaine] = $this->salleEtSemaine(FormationType::FA);

        User::factory()->etudiant($salle)->create(['name' => 'Bob Alternance', 'formation' => FormationType::FA]);
        User::factory()->etudiant($salle)->create(['name' => 'Ali Migrant', 'formation' => FormationType::FM]);

        $donnees = $this->liste->pour($salle, $semaine);

        $this->assertSame(['BOB ALTERNANCE'], $donnees['etudiants']->pluck('nom')->all());
        $this->assertFalse($donnees['contient_fm']);
    }

    public function test_header_fields_are_derived_from_the_salle(): void
    {
        [$salle, $semaine] = $this->salleEtSemaine(FormationType::FI, 'Génie Informatique', 'L2');
        Carbon::setTestNow('2026-10-01');

        $donnees = $this->liste->pour($salle, $semaine);

        $this->assertSame('GI', $donnees['option']);
        $this->assertSame('II', $donnees['niveau_romain']);
        $this->assertSame('GI2 – FI', $donnees['groupe']);
        $this->assertSame(3, $donnees['semestre']);
        $this->assertSame('2026-2027', $donnees['annee_academique']);
        $this->assertSame('02/02/2026', $donnees['semaine_du']);
        $this->assertSame('08/02/2026', $donnees['semaine_au']);
    }

    public function test_explicit_semester_and_year_override_the_defaults(): void
    {
        [$salle, $semaine] = $this->salleEtSemaine(FormationType::FI);

        $donnees = $this->liste->pour($salle, $semaine, 4, '2025-2026');

        $this->assertSame(4, $donnees['semestre']);
        $this->assertSame('2025-2026', $donnees['annee_academique']);
    }

    /**
     * Le tableau du bas est prérempli depuis l'emploi du temps de la
     * semaine, deux lignes par jour au minimum comme sur la maquette.
     */
    public function test_week_sessions_prefill_the_bottom_table(): void
    {
        [$salle, $semaine] = $this->salleEtSemaine(FormationType::FI);

        Seance::factory()->create([
            'salle_id' => $salle->id,
            'date_seance' => '2026-02-04', // mercredi
            'jour' => 'MERCREDI',
            'heure_debut' => '08:00',
            'heure_fin' => '10:30',
        ]);

        $donnees = $this->liste->pour($salle, $semaine);
        $mercredi = $donnees['jours']['MERCREDI'];

        $this->assertSame('Mercredi', $mercredi['label']);
        $this->assertCount(2, $mercredi['seances']);
        $this->assertSame('08:00', $mercredi['seances'][0]['debut']);
        $this->assertSame('2h30', $mercredi['seances'][0]['duree']);
        $this->assertNull($mercredi['seances'][1]);
        $this->assertCount(2, $donnees['jours']['LUNDI']['seances']);
    }

    public function test_endpoint_returns_a_pdf_to_admin_only(): void
    {
        [$salle, $semaine] = $this->salleEtSemaine(FormationType::FI);
        $admin = User::factory()->admin()->create();
        $enseignant = User::factory()->enseignant()->create();

        $this->actingAs($admin, 'sanctum')
            ->get("/api/salles/{$salle->id}/liste-presence.pdf?semaine_id={$semaine->id}")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->actingAs($enseignant, 'sanctum')
            ->get("/api/salles/{$salle->id}/liste-presence.pdf?semaine_id={$semaine->id}")
            ->assertForbidden();
    }

    public function test_endpoint_requires_a_week(): void
    {
        [$salle] = $this->salleEtSemaine(FormationType::FI);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin, 'sanctum')
            ->getJson("/api/salles/{$salle->id}/liste-presence.pdf")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('semaine_id');
    }

    /** @return array{0: Salle, 1: Semaine} */
    private function salleEtSemaine(FormationType $formation, string $filiere = 'Génie Informatique', string $niveau = 'L3'): array
    {
        $niveauModel = Niveau::factory()->create(['nom' => $niveau]);
        $filiereModel = Filiere::factory()->create(['nom' => $filiere, 'niveau_id' => $niveauModel->id]);
        $salle = Salle::factory()->create(['filiere_id' => $filiereModel->id, 'formation' => $formation]);
        $semaine = Semaine::factory()->create(['numero' => 1, 'date_debut' => '2026-02-02', 'date_fin' => '2026-02-08']);

        return [$salle, $semaine];
    }
}
