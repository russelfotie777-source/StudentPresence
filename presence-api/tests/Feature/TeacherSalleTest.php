<?php

namespace Tests\Feature;

use App\Models\Salle;
use App\Models\Seance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeacherSalleTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_sees_distinct_salles_they_teach(): void
    {
        $enseignant = User::factory()->enseignant()->create();
        $salleA = Salle::factory()->create(['nom' => 'A23']);
        $salleB = Salle::factory()->create(['nom' => 'B12']);

        Seance::factory()->create(['enseignant_id' => $enseignant->id, 'salle_id' => $salleA->id]);
        Seance::factory()->create(['enseignant_id' => $enseignant->id, 'salle_id' => $salleA->id]);
        Seance::factory()->create(['enseignant_id' => $enseignant->id, 'salle_id' => $salleB->id]);

        $response = $this->actingAs($enseignant, 'sanctum')->getJson('/api/me/salles-enseignees');

        $response->assertOk();
        $this->assertCount(2, $response->json());
        $this->assertEquals(['A23', 'B12'], array_column($response->json(), 'nom'));
    }

    public function test_teacher_with_no_seances_sees_an_empty_list(): void
    {
        $enseignant = User::factory()->enseignant()->create();

        $response = $this->actingAs($enseignant, 'sanctum')->getJson('/api/me/salles-enseignees');

        $response->assertOk();
        $this->assertCount(0, $response->json());
    }

    public function test_non_teacher_cannot_list_taught_salles(): void
    {
        $delegue = User::factory()->delegue()->create();

        $this->actingAs($delegue, 'sanctum')->getJson('/api/me/salles-enseignees')->assertForbidden();
    }
}
