<?php

namespace Tests\Feature;

use App\Enums\FormationType;
use App\Models\DemandeFormation;
use App\Models\Salle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FormationRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_fa_student_can_request_formation_migration(): void
    {
        $salle = Salle::factory()->create(['formation' => FormationType::FA]);
        $etudiant = User::factory()->etudiant($salle)->create(['formation' => FormationType::FA]);

        $response = $this->actingAs($etudiant, 'sanctum')
            ->postJson('/api/formation-requests', ['motif' => 'Je travaille désormais en journée.']);

        $response->assertCreated()->assertJsonPath('statut', 'en_attente');
        $this->assertDatabaseHas('demandes_formation', [
            'etudiant_id' => $etudiant->id,
            'statut' => 'en_attente',
        ]);
    }

    public function test_fi_student_cannot_request_migration(): void
    {
        $salle = Salle::factory()->create(['formation' => FormationType::FI]);
        $etudiant = User::factory()->etudiant($salle)->create(['formation' => FormationType::FI]);

        $this->actingAs($etudiant, 'sanctum')
            ->postJson('/api/formation-requests')
            ->assertStatus(422);
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
        $salle = Salle::factory()->create(['formation' => FormationType::FA]);
        $etudiant = User::factory()->etudiant($salle)->create(['formation' => FormationType::FA]);
        DemandeFormation::create(['etudiant_id' => $etudiant->id, 'statut' => 'en_attente', 'date_creation' => now()]);

        $this->actingAs($etudiant, 'sanctum')
            ->postJson('/api/formation-requests')
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
