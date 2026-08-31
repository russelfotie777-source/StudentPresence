<?php

namespace Tests\Feature;

use App\Models\Filiere;
use App\Models\Niveau;
use App\Models\Salle;
use App\Models\Seance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceStatsTest extends TestCase
{
    use RefreshDatabase;

    public function test_computes_personal_attendance_rate(): void
    {
        $niveau = Niveau::factory()->create();
        $filiere = Filiere::factory()->create(['niveau_id' => $niveau->id]);
        $salle = Salle::factory()->create(['filiere_id' => $filiere->id]);
        $etudiant = User::factory()->etudiant($salle)->create();

        $seances = Seance::factory()->count(4)->create(['salle_id' => $salle->id]);
        $seances[0]->presences()->create(['etudiant_id' => $etudiant->id, 'etat' => 'present']);
        $seances[1]->presences()->create(['etudiant_id' => $etudiant->id, 'etat' => 'present']);
        $seances[2]->presences()->create(['etudiant_id' => $etudiant->id, 'etat' => 'present']);
        $seances[3]->presences()->create(['etudiant_id' => $etudiant->id, 'etat' => 'absent']);

        $response = $this->actingAs($etudiant, 'sanctum')->getJson('/api/me/attendance-stats');

        $response->assertOk();
        $this->assertEquals(4, $response->json('total_seances'));
        $this->assertEquals(3, $response->json('presences'));
        $this->assertEquals(75, $response->json('taux'));
    }

    public function test_returns_null_rate_when_no_history(): void
    {
        $etudiant = User::factory()->etudiant()->create();

        $response = $this->actingAs($etudiant, 'sanctum')->getJson('/api/me/attendance-stats');

        $response->assertOk()->assertJsonPath('taux', null);
    }

    public function test_non_student_cannot_access(): void
    {
        $enseignant = User::factory()->enseignant()->create();

        $this->actingAs($enseignant, 'sanctum')
            ->getJson('/api/me/attendance-stats')
            ->assertForbidden();
    }
}
