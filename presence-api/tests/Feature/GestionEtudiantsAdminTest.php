<?php

namespace Tests\Feature;

use App\Enums\FormationType;
use App\Models\DemandeFormation;
use App\Models\Departement;
use App\Models\Filiere;
use App\Models\Niveau;
use App\Models\Push;
use App\Models\Salle;
use App\Models\Seance;
use App\Models\Semaine;
use App\Models\TarifHeure;
use App\Models\User;
use App\Services\ListeHebdomadaire;
use App\Services\PresenceAutomatique;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * L'onglet Gestion des étudiants de l'admin : les migrants par salle
 * d'accueil et leur liste, le privilège « toujours présent », la
 * comptabilité générale.
 */
class GestionEtudiantsAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_admin_grants_the_always_present_privilege_with_a_reason(): void
    {
        $admin = User::factory()->admin()->create();
        $etudiant = User::factory()->etudiant()->create();

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/etudiants/{$etudiant->id}/presence-automatique", ['actif' => true])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('motif');

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/etudiants/{$etudiant->id}/presence-automatique", ['actif' => true, 'motif' => 'Stage en entreprise validé par la direction.'])
            ->assertOk()
            ->assertJsonPath('presence_automatique', true)
            ->assertJsonPath('motif', 'Stage en entreprise validé par la direction.');

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/etudiants/presence-automatique')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $etudiant->id);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/etudiants/{$etudiant->id}/presence-automatique", ['actif' => false])
            ->assertOk()
            ->assertJsonPath('presence_automatique', false);

        $this->actingAs(User::factory()->enseignant()->create(), 'sanctum')
            ->postJson("/api/etudiants/{$etudiant->id}/presence-automatique", ['actif' => true, 'motif' => 'x'])
            ->assertForbidden();
    }

    public function test_a_privileged_student_is_present_when_the_delegue_confirms_the_roster_without_them(): void
    {
        [$salle, $seance] = $this->seanceActive();
        $delegue = User::factory()->delegue($salle)->create();
        $privilegie = User::factory()->etudiant($salle)->create(['presence_automatique' => true]);
        $autre = User::factory()->etudiant($salle)->create();
        Push::create(['seance_id' => $seance->id, 'etudiants_presents' => 1, 'status' => 'pending']);

        $this->actingAs($delegue, 'sanctum')
            ->postJson("/api/seances/{$seance->id}/confirm-roster", ['etudiants' => [$autre->id]])
            ->assertOk();

        $this->assertDatabaseHas('presences_etudiants', ['seance_id' => $seance->id, 'etudiant_id' => $privilegie->id, 'etat' => 'present', 'automatique' => true]);
        $this->assertDatabaseHas('presences_etudiants', ['seance_id' => $seance->id, 'etudiant_id' => $autre->id, 'etat' => 'present', 'automatique' => false]);
    }

    public function test_the_scheduler_marks_privileged_students_present_once_the_seance_has_started(): void
    {
        [$salle, $seance] = $this->seanceActive();
        $privilegie = User::factory()->etudiant($salle)->create(['presence_automatique' => true]);
        $ordinaire = User::factory()->etudiant($salle)->create();

        $this->assertSame(1, app(PresenceAutomatique::class)->tick());
        $this->assertDatabaseHas('presences_etudiants', ['seance_id' => $seance->id, 'etudiant_id' => $privilegie->id, 'etat' => 'present', 'automatique' => true]);
        $this->assertDatabaseMissing('presences_etudiants', ['seance_id' => $seance->id, 'etudiant_id' => $ordinaire->id]);

        // Rejouable sans doublon ; une absence posée par l'admin est respectée.
        $this->assertSame(0, app(PresenceAutomatique::class)->tick());
        $seance->presences()->where('etudiant_id', $privilegie->id)->update(['etat' => 'absent', 'forcee_par_id' => User::factory()->admin()->create()->id]);
        $this->assertSame(0, app(PresenceAutomatique::class)->tick());
        $this->assertDatabaseHas('presences_etudiants', ['seance_id' => $seance->id, 'etudiant_id' => $privilegie->id, 'etat' => 'absent']);
    }

    public function test_migrants_are_listed_by_host_salle_with_their_origin(): void
    {
        $admin = User::factory()->admin()->create();
        $departement = Departement::factory()->create(['code' => 'GI']);
        $niveau = Niveau::factory()->create(['nom' => 'L2']);
        $filiere = Filiere::factory()->create(['departement_id' => $departement->id, 'niveau_id' => $niveau->id]);
        $salleFA = Salle::factory()->create(['filiere_id' => $filiere->id, 'formation' => FormationType::FA, 'nom' => 'B12-FA']);
        $salleFI = Salle::factory()->create(['filiere_id' => $filiere->id, 'formation' => FormationType::FI, 'nom' => 'B12-FI']);
        $etudiant = User::factory()->etudiant($salleFA)->create(['formation' => FormationType::FA]);
        $demande = DemandeFormation::create(['etudiant_id' => $etudiant->id, 'salle_cible_id' => $salleFI->id, 'statut' => 'en_attente', 'date_creation' => now()]);

        $this->actingAs($admin, 'sanctum')->postJson("/api/formation-requests/{$demande->id}/approve")->assertOk();

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/migrants')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('salles.0.salle.nom', 'B12-FI')
            ->assertJsonPath('salles.0.salle.departement', 'GI')
            ->assertJsonPath('salles.0.etudiants.0.id', $etudiant->id)
            ->assertJsonPath('salles.0.etudiants.0.salle_origine', 'B12-FA');
    }

    public function test_the_migrants_only_list_keeps_fm_students_and_titles_itself(): void
    {
        $admin = User::factory()->admin()->create();
        $salle = Salle::factory()->create(['formation' => FormationType::FI]);
        User::factory()->etudiant($salle)->create(['formation' => FormationType::FI, 'name' => 'Ordinaire']);
        User::factory()->etudiant($salle)->create(['formation' => FormationType::FM, 'name' => 'Migrant']);
        $semaine = Semaine::factory()->create(['numero' => 1, 'date_debut' => '2026-02-02', 'date_fin' => '2026-02-08']);

        $donnees = app(ListeHebdomadaire::class)->pour($salle, $semaine, null, null, null, true);
        $this->assertSame(['MIGRANT'], $donnees['etudiants']->pluck('nom')->all());
        $this->assertStringContainsString('MIGRANTS', $donnees['titre']);

        $this->actingAs($admin, 'sanctum')
            ->get("/api/salles/{$salle->id}/liste-presence.pdf?semaine_id={$semaine->id}&migrants=1")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_the_accounting_sums_effectifs_seances_and_pay(): void
    {
        Carbon::setTestNow('2026-03-10 12:00:00');
        $admin = User::factory()->admin()->create();
        $niveau = Niveau::factory()->create(['nom' => 'L2']);
        $filiere = Filiere::factory()->create(['niveau_id' => $niveau->id]);
        $salle = Salle::factory()->create(['filiere_id' => $filiere->id, 'formation' => FormationType::FI]);
        TarifHeure::create(['niveau_id' => $niveau->id, 'tarif_heure' => 3000]);
        User::factory()->etudiant($salle)->count(2)->create();
        $tenue = Seance::factory()->create([
            'salle_id' => $salle->id, 'date_seance' => '2026-03-02', 'jour' => 'LUNDI',
            'heure_debut' => '08:00', 'heure_fin' => '09:00', 'debut_reel' => '08:00:00', 'fin_reelle' => '09:00:00',
            'etat_delegue' => 'present', 'etat_prof' => 'present',
        ]);
        Seance::factory()->create(['salle_id' => $salle->id, 'date_seance' => '2026-03-03', 'jour' => 'MARDI', 'heure_debut' => '08:00', 'heure_fin' => '09:00']);
        $tenue->presences()->create(['etudiant_id' => User::where('role', 'Etudiant')->first()->id, 'etat' => 'present', 'date_marquage' => now()]);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/comptabilite')
            ->assertOk()
            ->assertJsonPath('periode.du', '2025-09-01')
            ->assertJsonPath('effectifs.etudiants', 2)
            ->assertJsonPath('effectifs.par_formation.FI', 2)
            ->assertJsonPath('seances.passees', 2)
            ->assertJsonPath('seances.tenues', 1)
            ->assertJsonPath('seances.heures_effectuees', 1)
            ->assertJsonPath('assiduite.appels', 1)
            ->assertJsonPath('assiduite.taux', 100)
            ->assertJsonPath('paie.total_salaire', 3000)
            ->assertJsonPath('paie.enseignants.0.seances', 1);

        $this->actingAs(User::factory()->enseignant()->create(), 'sanctum')
            ->getJson('/api/comptabilite')
            ->assertForbidden();
    }

    /**
     * Séance active mercredi 04/02/2026 08:30–10:00, « maintenant » figé à 09:00.
     *
     * @return array{0: Salle, 1: Seance}
     */
    private function seanceActive(): array
    {
        Carbon::setTestNow('2026-02-04 09:00:00');
        $salle = Salle::factory()->create(['formation' => FormationType::FI]);
        $semaine = Semaine::factory()->create(['numero' => 1, 'date_debut' => '2026-02-02', 'date_fin' => '2026-02-08']);
        $seance = Seance::factory()->create([
            'salle_id' => $salle->id, 'semaine_id' => $semaine->id, 'date_seance' => '2026-02-04',
            'jour' => 'MERCREDI', 'heure_debut' => '08:30', 'heure_fin' => '10:00',
        ]);

        return [$salle, $seance];
    }
}
