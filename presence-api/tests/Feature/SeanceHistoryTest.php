<?php

namespace Tests\Feature;

use App\Enums\FormationType;
use App\Models\Filiere;
use App\Models\Niveau;
use App\Models\Salle;
use App\Models\Seance;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeanceHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-02-10 10:00:00');
    }

    public function test_student_sees_past_seances_of_their_salle_with_their_own_presence(): void
    {
        $niveau = Niveau::factory()->create();
        $filiere = Filiere::factory()->create(['niveau_id' => $niveau->id]);
        $salle = Salle::factory()->create(['filiere_id' => $filiere->id, 'formation' => FormationType::FI]);
        $etudiant = User::factory()->etudiant($salle)->create();

        $past = Seance::factory()->create(['salle_id' => $salle->id, 'date_seance' => '2026-02-04']);
        $past->presences()->create(['etudiant_id' => $etudiant->id, 'etat' => 'present']);
        Seance::factory()->create(['salle_id' => $salle->id, 'date_seance' => '2026-02-10']); // aujourd'hui : hors historique
        Seance::factory()->create(['date_seance' => '2026-02-03']); // autre salle : ne doit pas apparaître

        $response = $this->actingAs($etudiant, 'sanctum')->getJson('/api/seances/history');

        $response->assertOk();
        $this->assertCount(1, $response->json());
        $this->assertSame($past->id, $response->json('0.id'));
        $this->assertSame('present', $response->json('0.ma_presence'));
    }

    public function test_teacher_sees_only_their_own_past_seances(): void
    {
        $enseignant = User::factory()->enseignant()->create();
        $autreEnseignant = User::factory()->enseignant()->create();
        $salle = Salle::factory()->create();

        $mine = Seance::factory()->create([
            'salle_id' => $salle->id,
            'enseignant_id' => $enseignant->id,
            'date_seance' => '2026-02-04',
        ]);
        Seance::factory()->create([
            'salle_id' => $salle->id,
            'enseignant_id' => $autreEnseignant->id,
            'date_seance' => '2026-02-04',
        ]);

        $response = $this->actingAs($enseignant, 'sanctum')->getJson('/api/seances/history');

        $response->assertOk();
        $this->assertCount(1, $response->json());
        $this->assertSame($mine->id, $response->json('0.id'));
    }

    public function test_delegue_sees_their_salle_history(): void
    {
        $niveau = Niveau::factory()->create();
        $filiere = Filiere::factory()->create(['niveau_id' => $niveau->id]);
        $salle = Salle::factory()->create(['filiere_id' => $filiere->id]);
        $delegue = User::factory()->delegue($salle)->create();

        Seance::factory()->create(['salle_id' => $salle->id, 'date_seance' => '2026-02-04']);

        $response = $this->actingAs($delegue, 'sanctum')->getJson('/api/seances/history');

        $response->assertOk();
        $this->assertCount(1, $response->json());
    }

    public function test_history_is_ordered_most_recent_first(): void
    {
        $niveau = Niveau::factory()->create();
        $filiere = Filiere::factory()->create(['niveau_id' => $niveau->id]);
        $salle = Salle::factory()->create(['filiere_id' => $filiere->id]);
        $etudiant = User::factory()->etudiant($salle)->create();

        $older = Seance::factory()->create(['salle_id' => $salle->id, 'date_seance' => '2026-01-20']);
        $newer = Seance::factory()->create(['salle_id' => $salle->id, 'date_seance' => '2026-02-05']);

        $response = $this->actingAs($etudiant, 'sanctum')->getJson('/api/seances/history');

        $response->assertOk();
        $this->assertSame($newer->id, $response->json('0.id'));
        $this->assertSame($older->id, $response->json('1.id'));
    }
}
