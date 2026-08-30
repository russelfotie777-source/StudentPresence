<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\PromotionTemporaire;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromotionTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_can_promote_a_student(): void
    {
        $enseignant = User::factory()->enseignant()->create();
        $etudiant = User::factory()->etudiant()->create();

        $response = $this->actingAs($enseignant, 'sanctum')->postJson('/api/promotions', [
            'etudiant_id' => $etudiant->id,
            'duree_minutes' => 120,
        ]);

        $response->assertCreated();
        $this->assertTrue($etudiant->fresh()->hasActivePromotion());
        $this->assertEquals(UserRole::Delegue, $etudiant->fresh()->effectiveRole());
    }

    public function test_cannot_promote_a_student_with_an_active_promotion(): void
    {
        $enseignant = User::factory()->enseignant()->create();
        $etudiant = User::factory()->etudiant()->create();

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
        ])->assertUnprocessable();
    }

    public function test_non_teacher_cannot_promote(): void
    {
        $delegue = User::factory()->delegue()->create();
        $etudiant = User::factory()->etudiant()->create();

        $this->actingAs($delegue, 'sanctum')->postJson('/api/promotions', [
            'etudiant_id' => $etudiant->id,
            'duree_minutes' => 60,
        ])->assertForbidden();
    }

    public function test_teacher_can_search_students(): void
    {
        $enseignant = User::factory()->enseignant()->create();
        User::factory()->etudiant()->create(['name' => 'Awa Ngono']);
        User::factory()->etudiant()->create(['name' => 'Paul Biya']);

        $response = $this->actingAs($enseignant, 'sanctum')
            ->getJson('/api/students/search?search=Awa');

        $response->assertOk();
        $this->assertCount(1, $response->json());
        $this->assertEquals('Awa Ngono', $response->json()[0]['name']);
    }
}
