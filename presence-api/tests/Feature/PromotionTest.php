<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\PromotionTemporaire;
use App\Models\Salle;
use App\Models\Seance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromotionTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_can_promote_a_student_in_a_salle_they_teach(): void
    {
        $salle = Salle::factory()->create();
        $enseignant = User::factory()->enseignant()->create();
        Seance::factory()->create(['enseignant_id' => $enseignant->id, 'salle_id' => $salle->id]);
        $etudiant = User::factory()->etudiant($salle)->create();

        $response = $this->actingAs($enseignant, 'sanctum')->postJson('/api/promotions', [
            'etudiant_id' => $etudiant->id,
            'duree_minutes' => 120,
            'salle_id' => $salle->id,
        ]);

        $response->assertCreated();
        $this->assertTrue($etudiant->fresh()->hasActivePromotion());
        $this->assertEquals(UserRole::Delegue, $etudiant->fresh()->effectiveRole());
    }

    public function test_teacher_must_provide_salle_id(): void
    {
        $enseignant = User::factory()->enseignant()->create();
        $etudiant = User::factory()->etudiant()->create();

        $this->actingAs($enseignant, 'sanctum')->postJson('/api/promotions', [
            'etudiant_id' => $etudiant->id,
            'duree_minutes' => 60,
        ])->assertUnprocessable()->assertJsonValidationErrors('salle_id');
    }

    public function test_teacher_cannot_promote_a_student_in_a_salle_they_do_not_teach(): void
    {
        $salleEnseignee = Salle::factory()->create();
        $autreSalle = Salle::factory()->create();
        $enseignant = User::factory()->enseignant()->create();
        Seance::factory()->create(['enseignant_id' => $enseignant->id, 'salle_id' => $salleEnseignee->id]);
        $etudiant = User::factory()->etudiant($autreSalle)->create();

        $this->actingAs($enseignant, 'sanctum')->postJson('/api/promotions', [
            'etudiant_id' => $etudiant->id,
            'duree_minutes' => 60,
            'salle_id' => $autreSalle->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('salle_id');
    }

    public function test_teacher_cannot_promote_student_whose_salle_differs_from_provided_salle_id(): void
    {
        $salleEnseignee = Salle::factory()->create();
        $autreSalle = Salle::factory()->create();
        $enseignant = User::factory()->enseignant()->create();
        Seance::factory()->create(['enseignant_id' => $enseignant->id, 'salle_id' => $salleEnseignee->id]);
        $etudiant = User::factory()->etudiant($autreSalle)->create();

        $this->actingAs($enseignant, 'sanctum')->postJson('/api/promotions', [
            'etudiant_id' => $etudiant->id,
            'duree_minutes' => 60,
            'salle_id' => $salleEnseignee->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('etudiant_id');
    }

    public function test_delegue_can_promote_a_student_in_their_own_salle(): void
    {
        $salle = Salle::factory()->create();
        $delegue = User::factory()->delegue($salle)->create();
        $etudiant = User::factory()->etudiant($salle)->create();

        $response = $this->actingAs($delegue, 'sanctum')->postJson('/api/promotions', [
            'etudiant_id' => $etudiant->id,
            'duree_minutes' => 60,
        ]);

        $response->assertCreated();
        $this->assertTrue($etudiant->fresh()->hasActivePromotion());
    }

    public function test_delegue_cannot_promote_a_student_outside_their_salle(): void
    {
        $delegue = User::factory()->delegue()->create();
        $etudiant = User::factory()->etudiant()->create();

        $this->actingAs($delegue, 'sanctum')->postJson('/api/promotions', [
            'etudiant_id' => $etudiant->id,
            'duree_minutes' => 60,
        ])->assertUnprocessable()->assertJsonValidationErrors('etudiant_id');
    }

    public function test_delegue_cannot_bypass_own_salle_restriction_with_a_client_supplied_salle_id(): void
    {
        $delegue = User::factory()->delegue()->create();
        $autreSalle = Salle::factory()->create();
        $etudiant = User::factory()->etudiant($autreSalle)->create();

        $this->actingAs($delegue, 'sanctum')->postJson('/api/promotions', [
            'etudiant_id' => $etudiant->id,
            'duree_minutes' => 60,
            'salle_id' => $autreSalle->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('etudiant_id');
    }

    public function test_plain_student_cannot_promote(): void
    {
        $etudiant = User::factory()->etudiant()->create();
        $autreEtudiant = User::factory()->etudiant()->create();

        $this->actingAs($etudiant, 'sanctum')->postJson('/api/promotions', [
            'etudiant_id' => $autreEtudiant->id,
            'duree_minutes' => 60,
        ])->assertForbidden();
    }

    public function test_promoted_student_acting_as_delegate_can_promote_a_substitute(): void
    {
        $salle = Salle::factory()->create();
        $enseignant = User::factory()->enseignant()->create();
        $premierDelegue = User::factory()->etudiant($salle)->create();
        $second = User::factory()->etudiant($salle)->create();

        PromotionTemporaire::create([
            'etudiant_id' => $premierDelegue->id,
            'promoteur_id' => $enseignant->id,
            'date_debut' => now(),
            'date_fin' => now()->addHour(),
            'duree_minutes' => 60,
        ]);

        $this->assertEquals(UserRole::Delegue, $premierDelegue->fresh()->effectiveRole());

        $response = $this->actingAs($premierDelegue, 'sanctum')->postJson('/api/promotions', [
            'etudiant_id' => $second->id,
            'duree_minutes' => 30,
        ]);

        $response->assertCreated();
        $this->assertTrue($second->fresh()->hasActivePromotion());
    }

    public function test_cannot_promote_a_student_with_an_active_promotion(): void
    {
        $salle = Salle::factory()->create();
        $enseignant = User::factory()->enseignant()->create();
        Seance::factory()->create(['enseignant_id' => $enseignant->id, 'salle_id' => $salle->id]);
        $etudiant = User::factory()->etudiant($salle)->create();

        PromotionTemporaire::create([
            'etudiant_id' => $etudiant->id,
            'promoteur_id' => $enseignant->id,
            'date_debut' => now(),
            'date_fin' => now()->addHour(),
            'duree_minutes' => 60,
        ]);

        $this->actingAs($enseignant, 'sanctum')->postJson('/api/promotions', [
            'etudiant_id' => $etudiant->id,
            'duree_minutes' => 60,
            'salle_id' => $salle->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('etudiant_id');
    }

    public function test_index_returns_all_active_promotions_for_teacher(): void
    {
        $enseignant = User::factory()->enseignant()->create();
        $salleA = Salle::factory()->create();
        $salleB = Salle::factory()->create();
        $etudiantA = User::factory()->etudiant($salleA)->create();
        $etudiantB = User::factory()->etudiant($salleB)->create();

        PromotionTemporaire::create([
            'etudiant_id' => $etudiantA->id,
            'promoteur_id' => $enseignant->id,
            'date_debut' => now(),
            'date_fin' => now()->addHour(),
            'duree_minutes' => 60,
        ]);
        PromotionTemporaire::create([
            'etudiant_id' => $etudiantB->id,
            'promoteur_id' => $enseignant->id,
            'date_debut' => now(),
            'date_fin' => now()->addHour(),
            'duree_minutes' => 60,
        ]);

        $response = $this->actingAs($enseignant, 'sanctum')->getJson('/api/promotions');

        $response->assertOk();
        $this->assertCount(2, $response->json());
    }

    public function test_index_scopes_active_promotions_to_own_salle_for_delegue(): void
    {
        $salle = Salle::factory()->create();
        $delegue = User::factory()->delegue($salle)->create();
        $etudiantMemeSalle = User::factory()->etudiant($salle)->create();
        $etudiantAutreSalle = User::factory()->etudiant()->create();
        $enseignant = User::factory()->enseignant()->create();

        PromotionTemporaire::create([
            'etudiant_id' => $etudiantMemeSalle->id,
            'promoteur_id' => $enseignant->id,
            'date_debut' => now(),
            'date_fin' => now()->addHour(),
            'duree_minutes' => 60,
        ]);
        PromotionTemporaire::create([
            'etudiant_id' => $etudiantAutreSalle->id,
            'promoteur_id' => $enseignant->id,
            'date_debut' => now(),
            'date_fin' => now()->addHour(),
            'duree_minutes' => 60,
        ]);

        $response = $this->actingAs($delegue, 'sanctum')->getJson('/api/promotions');

        $response->assertOk();
        $this->assertCount(1, $response->json());
        $this->assertEquals($etudiantMemeSalle->id, $response->json()[0]['etudiant']['id']);
    }
}
