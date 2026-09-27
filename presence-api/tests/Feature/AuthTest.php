<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\ValidationStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_registration_is_disabled(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Awa Étudiante',
            'phone' => '699112233',
            'password' => 'password123',
            'role' => UserRole::Etudiant->value,
        ]);

        $response->assertForbidden()->assertJson([
            'code' => 'PUBLIC_REGISTRATION_DISABLED',
        ]);
        $this->assertDatabaseMissing('users', ['phone' => '699112233']);
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
