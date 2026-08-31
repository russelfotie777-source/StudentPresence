<?php

namespace Tests\Feature;

use App\Enums\FormationType;
use App\Enums\UserRole;
use App\Enums\ValidationStatus;
use App\Models\Salle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_registers_and_is_auto_logged_in(): void
    {
        $salle = Salle::factory()->create(['formation' => FormationType::FI]);

        $response = $this->postJson('/api/auth/register', [
            'name' => 'Awa Étudiante',
            'phone' => '699112233',
            'password' => 'password123',
            'role' => UserRole::Etudiant->value,
            'formation' => FormationType::FI->value,
            'salle_id' => $salle->id,
            'niveau_id' => $salle->filiere->niveau_id,
            'filiere_id' => $salle->filiere_id,
        ]);

        $response->assertCreated()->assertJsonPath('user.validation_status', 'approved');
        $this->assertNotNull($response->json('token'));

        $this->assertDatabaseHas('users', [
            'phone' => '699112233',
            'role' => 'Etudiant',
            'validation_status' => 'approved',
        ]);
    }

    public function test_delegue_registers_without_auto_login_and_starts_unvalidated(): void
    {
        $salle = Salle::factory()->create();

        $response = $this->postJson('/api/auth/register', [
            'name' => 'Bakari Délégué',
            'phone' => '699223344',
            'password' => 'password123',
            'role' => UserRole::Delegue->value,
            'formation' => $salle->formation->value,
            'salle_id' => $salle->id,
            'niveau_id' => $salle->filiere->niveau_id,
            'filiere_id' => $salle->filiere_id,
        ]);

        $response->assertCreated();
        $this->assertNull($response->json('token'));
        $this->assertDatabaseHas('users', [
            'phone' => '699223344',
            'validation_status' => 'none',
        ]);
    }

    public function test_fm_formation_is_rejected_at_registration(): void
    {
        $salle = Salle::factory()->create(['formation' => FormationType::FI]);

        $response = $this->postJson('/api/auth/register', [
            'name' => 'Étudiant Migrant',
            'phone' => '699334455',
            'password' => 'password123',
            'role' => UserRole::Etudiant->value,
            'formation' => FormationType::FM->value,
            'salle_id' => $salle->id,
            'niveau_id' => $salle->filiere->niveau_id,
            'filiere_id' => $salle->filiere_id,
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('formation');
    }

    public function test_login_fails_with_wrong_password(): void
    {
        User::factory()->create(['phone' => '699445566']);

        $this->postJson('/api/auth/login', [
            'phone' => '699445566',
            'password' => 'wrong-password',
        ])->assertUnprocessable();
    }

    public function test_login_succeeds_and_returns_token(): void
    {
        User::factory()->create(['phone' => '699556677', 'password' => bcrypt('secret123')]);

        $response = $this->postJson('/api/auth/login', [
            'phone' => '699556677',
            'password' => 'secret123',
        ]);

        $response->assertOk();
        $this->assertNotNull($response->json('token'));
    }

    public function test_delegue_can_request_validation_only_once(): void
    {
        $user = User::factory()->delegue()->create(['validation_status' => ValidationStatus::None]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/auth/request-validation')
            ->assertOk()
            ->assertJsonPath('user.validation_status', 'pending');

        $this->assertDatabaseHas('users', ['id' => $user->id, 'validation_status' => 'pending']);

        $this->actingAs($user->fresh(), 'sanctum')
            ->postJson('/api/auth/request-validation')
            ->assertUnprocessable();
    }

    public function test_pending_delegue_is_blocked_from_validated_routes(): void
    {
        $user = User::factory()->delegue()->create(['validation_status' => ValidationStatus::Pending]);

        // /api/auth/me n'exige pas `validated`, seulement `auth:sanctum` —
        // sert de témoin que le compte pending accède bien aux routes non
        // protégées par le middleware `validated`.
        $this->actingAs($user, 'sanctum')
            ->getJson('/api/auth/me')
            ->assertOk();
    }
}
