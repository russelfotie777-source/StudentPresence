<?php

namespace Tests\Feature;

use App\Models\Filiere;
use App\Models\Niveau;
use App\Models\Salle;
use App\Models\Seance;
use App\Models\Semaine;
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

    public function test_computes_weekly_attendance_trend(): void
    {
        $niveau = Niveau::factory()->create();
        $filiere = Filiere::factory()->create(['niveau_id' => $niveau->id]);
        $salle = Salle::factory()->create(['filiere_id' => $filiere->id]);
        $etudiant = User::factory()->etudiant($salle)->create();

        $semaine1 = Semaine::factory()->create(['numero' => 1]);
        $semaine2 = Semaine::factory()->create(['numero' => 2]);

        $s1a = Seance::factory()->create(['salle_id' => $salle->id, 'semaine_id' => $semaine1->id]);
        $s1b = Seance::factory()->create(['salle_id' => $salle->id, 'semaine_id' => $semaine1->id]);
        $s2a = Seance::factory()->create(['salle_id' => $salle->id, 'semaine_id' => $semaine2->id]);

        $s1a->presences()->create(['etudiant_id' => $etudiant->id, 'etat' => 'present']);
        $s1b->presences()->create(['etudiant_id' => $etudiant->id, 'etat' => 'absent']);
        $s2a->presences()->create(['etudiant_id' => $etudiant->id, 'etat' => 'present']);

        $response = $this->actingAs($etudiant, 'sanctum')->getJson('/api/me/attendance-trend');

        $response->assertOk();
        $response->assertJson([
            ['semaine' => 1, 'label' => 'S1', 'taux' => 50],
            ['semaine' => 2, 'label' => 'S2', 'taux' => 100],
        ]);
    }

    public function test_trend_is_empty_when_no_history(): void
    {
        $etudiant = User::factory()->etudiant()->create();

        $response = $this->actingAs($etudiant, 'sanctum')->getJson('/api/me/attendance-trend');

        $response->assertOk()->assertJson([]);
    }

    public function test_non_student_cannot_access_trend(): void
    {
        $enseignant = User::factory()->enseignant()->create();

        $this->actingAs($enseignant, 'sanctum')
            ->getJson('/api/me/attendance-trend')
            ->assertForbidden();
    }
}
