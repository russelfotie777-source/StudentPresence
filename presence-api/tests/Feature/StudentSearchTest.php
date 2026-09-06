<?php

namespace Tests\Feature;

use App\Models\Salle;
use App\Models\Seance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_can_search_students_scoped_to_a_salle_they_teach(): void
    {
        $salle = Salle::factory()->create();
        $enseignant = User::factory()->enseignant()->create();
        Seance::factory()->create(['enseignant_id' => $enseignant->id, 'salle_id' => $salle->id]);
        User::factory()->etudiant($salle)->create(['name' => 'Awa Ngono']);
        User::factory()->etudiant($salle)->create(['name' => 'Paul Biya']);

        $response = $this->actingAs($enseignant, 'sanctum')
            ->getJson("/api/students/search?salle_id={$salle->id}&search=Awa");

        $response->assertOk();
        $this->assertCount(1, $response->json());
        $this->assertEquals('Awa Ngono', $response->json()[0]['name']);
    }

    public function test_teacher_must_provide_salle_id(): void
    {
        $enseignant = User::factory()->enseignant()->create();

        $this->actingAs($enseignant, 'sanctum')
            ->getJson('/api/students/search')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('salle_id');
    }

    public function test_teacher_cannot_search_a_salle_they_do_not_teach(): void
    {
        $salle = Salle::factory()->create();
        $enseignant = User::factory()->enseignant()->create();

        $this->actingAs($enseignant, 'sanctum')
            ->getJson("/api/students/search?salle_id={$salle->id}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('salle_id');
    }

    public function test_teacher_search_does_not_return_students_from_another_salle(): void
    {
        $salle = Salle::factory()->create();
        $autreSalle = Salle::factory()->create();
        $enseignant = User::factory()->enseignant()->create();
        Seance::factory()->create(['enseignant_id' => $enseignant->id, 'salle_id' => $salle->id]);
        User::factory()->etudiant($salle)->create();
        User::factory()->etudiant($autreSalle)->create();

        $response = $this->actingAs($enseignant, 'sanctum')
            ->getJson("/api/students/search?salle_id={$salle->id}");

        $response->assertOk();
        $this->assertCount(1, $response->json());
    }

    public function test_delegue_can_search_their_own_salle_roster_without_a_query(): void
    {
        $salle = Salle::factory()->create();
        $delegue = User::factory()->delegue($salle)->create();
        User::factory()->etudiant($salle)->count(3)->create();

        $response = $this->actingAs($delegue, 'sanctum')->getJson('/api/students/search');

        $response->assertOk();
        $this->assertCount(3, $response->json());
    }

    /**
     * Régression : le frontend envoie littéralement `search=` (chaîne vide)
     * une fois le trousseau chargé automatiquement dès qu'une salle est
     * connue — Laravel convertit cette chaîne vide en `null`, qui doit
     * rester accepté par la règle `nullable` sur `search`.
     */
    public function test_search_with_an_empty_query_string_returns_the_full_roster(): void
    {
        $salle = Salle::factory()->create();
        $delegue = User::factory()->delegue($salle)->create();
        User::factory()->etudiant($salle)->count(3)->create();

        $response = $this->actingAs($delegue, 'sanctum')->getJson('/api/students/search?search=');

        $response->assertOk();
        $this->assertCount(3, $response->json());
    }

    public function test_delegue_cannot_see_another_salle_via_a_client_supplied_salle_id(): void
    {
        $salle = Salle::factory()->create();
        $autreSalle = Salle::factory()->create();
        $delegue = User::factory()->delegue($salle)->create();
        User::factory()->etudiant($salle)->create();
        User::factory()->etudiant($autreSalle)->count(2)->create();

        $response = $this->actingAs($delegue, 'sanctum')
            ->getJson("/api/students/search?salle_id={$autreSalle->id}");

        $response->assertOk();
        $this->assertCount(1, $response->json());
    }

    public function test_plain_student_cannot_search(): void
    {
        $etudiant = User::factory()->etudiant()->create();

        $this->actingAs($etudiant, 'sanctum')->getJson('/api/students/search')->assertForbidden();
    }
}
