<?php

namespace Tests\Feature;

use App\Enums\ValidationStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_list_pending_delegates_and_teachers(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->delegue()->create(['validation_status' => ValidationStatus::Pending]);
        User::factory()->enseignant()->create(['validation_status' => ValidationStatus::Pending]);
        User::factory()->etudiant()->create(); // ne doit jamais apparaître ici

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/validations?statut=pending');

        $response->assertOk();
        $this->assertCount(2, $response->json());
    }

    public function test_admin_can_approve_a_pending_delegate(): void
    {
        $admin = User::factory()->admin()->create();
        $delegue = User::factory()->delegue()->create(['validation_status' => ValidationStatus::Pending]);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/validations/{$delegue->id}/approve")
            ->assertOk()
            ->assertJsonPath('validation_status', 'approved');

        $this->assertDatabaseHas('users', ['id' => $delegue->id, 'validation_status' => 'approved']);
    }

    public function test_admin_can_reject_a_pending_teacher(): void
    {
        $admin = User::factory()->admin()->create();
        $enseignant = User::factory()->enseignant()->create(['validation_status' => ValidationStatus::Pending]);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/validations/{$enseignant->id}/reject")
            ->assertOk()
            ->assertJsonPath('validation_status', 'none');
    }

    public function test_cannot_validate_a_student_account(): void
    {
        $admin = User::factory()->admin()->create();
        $etudiant = User::factory()->etudiant()->create();

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/validations/{$etudiant->id}/approve")
            ->assertStatus(422);
    }

    public function test_non_admin_cannot_access_validations(): void
    {
        $enseignant = User::factory()->enseignant()->create();

        $this->actingAs($enseignant, 'sanctum')
            ->getJson('/api/validations')
            ->assertForbidden();
    }
}
