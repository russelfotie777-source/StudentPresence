<?php

namespace Tests\Feature;

use App\Enums\ValidationStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnseignantListTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sees_only_approved_teachers(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->enseignant()->create(['name' => 'Prof Validé']);
        User::factory()->enseignant()->create(['name' => 'Prof En Attente', 'validation_status' => ValidationStatus::Pending]);

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/enseignants');

        $response->assertOk();
        $this->assertCount(1, $response->json());
        $this->assertEquals('Prof Validé', $response->json()[0]['name']);
    }

    public function test_non_admin_cannot_list_teachers(): void
    {
        $enseignant = User::factory()->enseignant()->create();

        $this->actingAs($enseignant, 'sanctum')->getJson('/api/enseignants')->assertForbidden();
    }
}
