<?php

namespace Tests\Feature;

use App\Enums\FormationType;
use App\Models\DemandeFormation;
use App\Models\Departement;
use App\Models\Filiere;
use App\Models\Niveau;
use App\Models\Salle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Migration FA → FI : l'étudiant en alternance demande une salle de jour de
 * son département et de son niveau (premières années seulement), l'admin
 * valide — et c'est seulement là que le compte bascule en FM.
 */
class FormationRequestTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Un département avec, au même niveau, une salle FA (celle de l'étudiant)
     * et une salle FI (celle qu'il vise).
     *
     * @return array{0: User, 1: Salle, 2: Salle}
     */
    private function etudiantFAAvecSalleFI(string $niveau = 'L2'): array
    {
        $departement = Departement::factory()->create();
        $niveauModel = Niveau::factory()->create(['nom' => $niveau]);
        $filiere = Filiere::factory()->create(['departement_id' => $departement->id, 'niveau_id' => $niveauModel->id]);
        $salleFA = Salle::factory()->create(['filiere_id' => $filiere->id, 'formation' => FormationType::FA]);
        $salleFI = Salle::factory()->create(['filiere_id' => $filiere->id, 'formation' => FormationType::FI]);
        $etudiant = User::factory()->etudiant($salleFA)->create(['formation' => FormationType::FA]);

        return [$etudiant, $salleFA, $salleFI];
    }

    public function test_fa_student_requests_a_fi_salle_of_their_departement_and_niveau(): void
    {
        [$etudiant, , $salleFI] = $this->etudiantFAAvecSalleFI();

        $response = $this->actingAs($etudiant, 'sanctum')
            ->postJson('/api/formation-requests', ['salle_cible_id' => $salleFI->id, 'motif' => 'Je travaille désormais en journée.']);

        $response->assertCreated()
            ->assertJsonPath('statut', 'en_attente')
            ->assertJsonPath('salle_cible.id', $salleFI->id);
        $this->assertDatabaseHas('demandes_formation', [
            'etudiant_id' => $etudiant->id,
            'salle_cible_id' => $salleFI->id,
            'statut' => 'en_attente',
        ]);
        // Rien ne change tant que l'admin n'a pas validé.
        $this->assertDatabaseHas('users', ['id' => $etudiant->id, 'formation' => 'FA']);
    }

    public function test_the_target_must_be_a_fi_salle_of_the_same_departement_and_niveau(): void
    {
        [$etudiant] = $this->etudiantFAAvecSalleFI();
        $ailleurs = Salle::factory()->create(['formation' => FormationType::FI]); // autre département, autre niveau

        $this->actingAs($etudiant, 'sanctum')
            ->postJson('/api/formation-requests', ['salle_cible_id' => $ailleurs->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('salle_cible_id');
    }

    public function test_migration_is_closed_in_third_year(): void
    {
        [$etudiant, , $salleFI] = $this->etudiantFAAvecSalleFI('L3');

        $this->actingAs($etudiant, 'sanctum')
            ->getJson('/api/me/migration')
            ->assertOk()
            ->assertJsonPath('eligible', false)
            ->assertJsonPath('empechement', "La migration n'est pas ouverte en L3.")
            ->assertJsonCount(0, 'salles');

        $this->actingAs($etudiant, 'sanctum')
            ->postJson('/api/formation-requests', ['salle_cible_id' => $salleFI->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('demande');
    }

    public function test_situation_lists_the_eligible_salles_and_the_pending_request(): void
    {
        [$etudiant, $salleFA, $salleFI] = $this->etudiantFAAvecSalleFI('L1');
        // Une salle FI d'un autre niveau du même département ne compte pas.
        $autreNiveau = Niveau::factory()->create(['nom' => 'L2']);
        $autreFiliere = Filiere::factory()->create(['departement_id' => $salleFA->filiere->departement_id, 'niveau_id' => $autreNiveau->id]);
        Salle::factory()->create(['filiere_id' => $autreFiliere->id, 'formation' => FormationType::FI]);

        $this->actingAs($etudiant, 'sanctum')
            ->getJson('/api/me/migration')
            ->assertOk()
            ->assertJsonPath('formation', 'FA')
            ->assertJsonPath('eligible', true)
            ->assertJsonPath('empechement', null)
            ->assertJsonCount(1, 'salles')
            ->assertJsonPath('salles.0.id', $salleFI->id)
            ->assertJsonPath('demande_en_attente', null);

        $this->actingAs($etudiant, 'sanctum')
            ->postJson('/api/formation-requests', ['salle_cible_id' => $salleFI->id])
            ->assertCreated();

        $this->actingAs($etudiant, 'sanctum')
            ->getJson('/api/me/migration')
            ->assertOk()
            ->assertJsonPath('demande_en_attente.salle_cible.id', $salleFI->id);
    }

    public function test_student_can_withdraw_a_pending_request_but_not_someone_elses(): void
    {
        [$etudiant, , $salleFI] = $this->etudiantFAAvecSalleFI();
        $demande = DemandeFormation::create(['etudiant_id' => $etudiant->id, 'salle_cible_id' => $salleFI->id, 'statut' => 'en_attente', 'date_creation' => now()]);

        $autre = User::factory()->etudiant()->create();
        $this->actingAs($autre, 'sanctum')
            ->deleteJson("/api/formation-requests/{$demande->id}")
            ->assertForbidden();

        $this->actingAs($etudiant, 'sanctum')
            ->deleteJson("/api/formation-requests/{$demande->id}")
            ->assertNoContent();
        $this->assertDatabaseMissing('demandes_formation', ['id' => $demande->id]);
    }

    public function test_fi_student_cannot_request_migration(): void
    {
        $salle = Salle::factory()->create(['formation' => FormationType::FI]);
        $etudiant = User::factory()->etudiant($salle)->create(['formation' => FormationType::FI]);

        $this->actingAs($etudiant, 'sanctum')
            ->postJson('/api/formation-requests', ['salle_cible_id' => $salle->id])
            ->assertStatus(422);

        $this->actingAs($etudiant, 'sanctum')
            ->getJson('/api/me/migration')
            ->assertOk()
            ->assertJsonPath('eligible', false);
    }

    public function test_non_student_cannot_request_migration(): void
    {
        $enseignant = User::factory()->enseignant()->create();

        $this->actingAs($enseignant, 'sanctum')
            ->postJson('/api/formation-requests')
            ->assertForbidden();
    }

    public function test_student_cannot_have_two_pending_requests(): void
    {
        [$etudiant, , $salleFI] = $this->etudiantFAAvecSalleFI();
        DemandeFormation::create(['etudiant_id' => $etudiant->id, 'salle_cible_id' => $salleFI->id, 'statut' => 'en_attente', 'date_creation' => now()]);

        $this->actingAs($etudiant, 'sanctum')
            ->postJson('/api/formation-requests', ['salle_cible_id' => $salleFI->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors('demande');
    }

    public function test_admin_can_list_and_filter_requests(): void
    {
        $admin = User::factory()->admin()->create();
        $salle = Salle::factory()->create(['formation' => FormationType::FA]);
        $etudiant = User::factory()->etudiant($salle)->create(['formation' => FormationType::FA]);
        DemandeFormation::create(['etudiant_id' => $etudiant->id, 'statut' => 'en_attente', 'date_creation' => now()]);
        DemandeFormation::create(['etudiant_id' => $etudiant->id, 'statut' => 'rejetee', 'date_creation' => now()]);

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/formation-requests?statut=en_attente');

        $response->assertOk();
        $this->assertCount(1, $response->json());
    }

    /**
     * L'admin choisit une salle FI cible parmi celles de l'établissement.
     * Sans le niveau et la filière du demandeur, il arbitre à l'aveugle entre
     * des salles dont les noms se répètent d'une filière à l'autre.
     */
    public function test_listing_exposes_the_student_niveau_and_filiere(): void
    {
        $admin = User::factory()->admin()->create();
        $salle = Salle::factory()->create(['formation' => FormationType::FA]);
        $etudiant = User::factory()->etudiant($salle)->create(['formation' => FormationType::FA]);
        DemandeFormation::create(['etudiant_id' => $etudiant->id, 'statut' => 'en_attente', 'date_creation' => now()]);

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/formation-requests');

        $response->assertOk();
        $this->assertSame($etudiant->niveau->nom, $response->json('0.etudiant.niveau'));
        $this->assertSame($etudiant->niveau_id, $response->json('0.etudiant.niveau_id'));
        $this->assertSame($etudiant->filiere->nom, $response->json('0.etudiant.filiere'));
    }

    /** Sans salle explicite, l'admin valide la salle demandée par l'étudiant. */
    public function test_admin_approval_defaults_to_the_requested_salle(): void
    {
        $admin = User::factory()->admin()->create();
        [$etudiant, , $salleFI] = $this->etudiantFAAvecSalleFI();
        $demande = DemandeFormation::create(['etudiant_id' => $etudiant->id, 'salle_cible_id' => $salleFI->id, 'statut' => 'en_attente', 'date_creation' => now()]);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/formation-requests/{$demande->id}/approve")
            ->assertOk()
            ->assertJsonPath('statut', 'acceptee')
            ->assertJsonPath('salle_cible.id', $salleFI->id);

        $this->assertDatabaseHas('users', ['id' => $etudiant->id, 'formation' => 'FM', 'salle_id' => $salleFI->id]);
    }

    public function test_admin_can_approve_and_it_reassigns_the_student(): void
    {
        $admin = User::factory()->admin()->create();
        $salleFA = Salle::factory()->create(['formation' => FormationType::FA]);
        $salleFI = Salle::factory()->create(['formation' => FormationType::FI]);
        $etudiant = User::factory()->etudiant($salleFA)->create(['formation' => FormationType::FA]);
        $demande = DemandeFormation::create(['etudiant_id' => $etudiant->id, 'statut' => 'en_attente', 'date_creation' => now()]);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/formation-requests/{$demande->id}/approve", ['salle_id' => $salleFI->id]);

        $response->assertOk()->assertJsonPath('statut', 'acceptee');

        $this->assertDatabaseHas('demandes_formation', [
            'id' => $demande->id,
            'statut' => 'acceptee',
            'salle_cible_id' => $salleFI->id,
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $etudiant->id,
            'formation' => 'FM',
            'salle_id' => $salleFI->id,
            'filiere_id' => $salleFI->filiere_id,
            'niveau_id' => $salleFI->filiere->niveau_id,
        ]);
    }

    public function test_admin_approve_requires_an_fi_target_salle(): void
    {
        $admin = User::factory()->admin()->create();
        $salleFA = Salle::factory()->create(['formation' => FormationType::FA]);
        $autreSalleFA = Salle::factory()->create(['formation' => FormationType::FA]);
        $etudiant = User::factory()->etudiant($salleFA)->create(['formation' => FormationType::FA]);
        $demande = DemandeFormation::create(['etudiant_id' => $etudiant->id, 'statut' => 'en_attente', 'date_creation' => now()]);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/formation-requests/{$demande->id}/approve", ['salle_id' => $autreSalleFA->id])
            ->assertStatus(422);
    }

    public function test_admin_can_reject_with_a_comment(): void
    {
        $admin = User::factory()->admin()->create();
        $salle = Salle::factory()->create(['formation' => FormationType::FA]);
        $etudiant = User::factory()->etudiant($salle)->create(['formation' => FormationType::FA]);
        $demande = DemandeFormation::create(['etudiant_id' => $etudiant->id, 'statut' => 'en_attente', 'date_creation' => now()]);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/formation-requests/{$demande->id}/reject", ['commentaire' => 'Effectif FI déjà complet.']);

        $response->assertOk()->assertJsonPath('statut', 'rejetee');
        $this->assertDatabaseHas('demandes_formation', [
            'id' => $demande->id,
            'statut' => 'rejetee',
            'commentaire_admin' => 'Effectif FI déjà complet.',
        ]);
        $this->assertDatabaseHas('users', ['id' => $etudiant->id, 'formation' => 'FA']);
    }

    public function test_cannot_process_an_already_treated_request(): void
    {
        $admin = User::factory()->admin()->create();
        $salle = Salle::factory()->create(['formation' => FormationType::FA]);
        $etudiant = User::factory()->etudiant($salle)->create(['formation' => FormationType::FA]);
        $demande = DemandeFormation::create(['etudiant_id' => $etudiant->id, 'statut' => 'rejetee', 'date_creation' => now(), 'date_traitement' => now()]);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/formation-requests/{$demande->id}/reject")
            ->assertStatus(422);
    }

    public function test_non_admin_cannot_process_requests(): void
    {
        $enseignant = User::factory()->enseignant()->create();
        $salle = Salle::factory()->create(['formation' => FormationType::FA]);
        $etudiant = User::factory()->etudiant($salle)->create(['formation' => FormationType::FA]);
        $demande = DemandeFormation::create(['etudiant_id' => $etudiant->id, 'statut' => 'en_attente', 'date_creation' => now()]);

        $this->actingAs($enseignant, 'sanctum')
            ->getJson('/api/formation-requests')
            ->assertForbidden();

        $this->actingAs($enseignant, 'sanctum')
            ->postJson("/api/formation-requests/{$demande->id}/reject")
            ->assertForbidden();
    }
}
