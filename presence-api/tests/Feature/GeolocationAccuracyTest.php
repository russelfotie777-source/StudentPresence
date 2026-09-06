<?php

namespace Tests\Feature;

use App\Enums\Weekday;
use App\Models\Filiere;
use App\Models\Niveau;
use App\Models\Salle;
use App\Models\Seance;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Le navigateur livre chaque position avec un rayon d'incertitude
 * (coords.accuracy). Une mesure à ±800 m — cas courant d'un premier point
 * Wi-Fi/antenne avant que le GPS ne converge — ne permet de conclure ni dans
 * un sens ni dans l'autre face à un périmètre de 120 m : elle est refusée
 * plutôt qu'utilisée.
 */
class GeolocationAccuracyTest extends TestCase
{
    use RefreshDatabase;

    private Niveau $niveau;

    private Salle $salle;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-09-07 09:00:00');
        $this->niveau = Niveau::factory()->create();
        $filiere = Filiere::factory()->create(['niveau_id' => $this->niveau->id]);
        $this->salle = Salle::factory()->create(['filiere_id' => $filiere->id, 'formation' => 'FI']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function seance(): Seance
    {
        return Seance::factory()->create([
            'salle_id' => $this->salle->id,
            'date_seance' => '2026-09-07',
            'jour' => Weekday::Lundi->value,
            'heure_debut' => '08:30',
            'heure_fin' => '10:30',
        ]);
    }

    private function delegue(): User
    {
        return User::factory()->delegue($this->salle)->create(['niveau_id' => $this->niveau->id]);
    }

    private function etudiant(): User
    {
        return User::factory()->etudiant($this->salle)->create(['niveau_id' => $this->niveau->id]);
    }

    public function test_delegue_position_is_refused_when_too_imprecise(): void
    {
        $seance = $this->seance();

        $this->actingAs($this->delegue(), 'sanctum')
            ->postJson("/api/seances/{$seance->id}/position", [
                'latitude' => 4.05,
                'longitude' => 9.7,
                'accuracy' => 800,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('accuracy');

        $this->assertDatabaseMissing('positions_seances', ['seance_id' => $seance->id]);
    }

    public function test_delegue_position_stores_its_accuracy(): void
    {
        $seance = $this->seance();

        $this->actingAs($this->delegue(), 'sanctum')
            ->postJson("/api/seances/{$seance->id}/position", [
                'latitude' => 4.05,
                'longitude' => 9.7,
                'accuracy' => 11.4,
            ])
            ->assertCreated();

        $this->assertDatabaseHas('positions_seances', [
            'seance_id' => $seance->id,
            'precision_metres' => 11,
        ]);
    }

    public function test_check_in_is_refused_when_too_imprecise(): void
    {
        $seance = $this->seance();
        $etudiant = $this->etudiant();

        $this->actingAs($this->delegue(), 'sanctum')
            ->postJson("/api/seances/{$seance->id}/position", [
                'latitude' => 4.05, 'longitude' => 9.7, 'accuracy' => 10,
            ])->assertCreated();

        // Au même endroit que le délégué, mais avec une mesure inexploitable :
        // être réellement à côté ne suffit pas si on ne peut pas le prouver.
        $this->actingAs($etudiant, 'sanctum')
            ->postJson("/api/seances/{$seance->id}/check-in", [
                'latitude' => 4.05, 'longitude' => 9.7, 'accuracy' => 500,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('accuracy');

        $this->assertDatabaseMissing('presences_etudiants', ['etudiant_id' => $etudiant->id]);
    }

    public function test_check_in_records_distance_and_accuracy_for_later_disputes(): void
    {
        $seance = $this->seance();
        $etudiant = $this->etudiant();

        $this->actingAs($this->delegue(), 'sanctum')
            ->postJson("/api/seances/{$seance->id}/position", [
                'latitude' => 4.0500, 'longitude' => 9.7000, 'accuracy' => 10,
            ])->assertCreated();

        // 0.0001° de latitude ≈ 11 m.
        $this->actingAs($etudiant, 'sanctum')
            ->postJson("/api/seances/{$seance->id}/check-in", [
                'latitude' => 4.0501, 'longitude' => 9.7000, 'accuracy' => 18.7,
            ])
            ->assertOk();

        $this->assertDatabaseHas('presences_etudiants', [
            'etudiant_id' => $etudiant->id,
            'distance_metres' => 11,
            'precision_metres' => 19,
        ]);
    }

    public function test_accuracy_is_required(): void
    {
        $seance = $this->seance();

        $this->actingAs($this->delegue(), 'sanctum')
            ->postJson("/api/seances/{$seance->id}/position", [
                'latitude' => 4.05, 'longitude' => 9.7,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('accuracy');
    }

    /**
     * Le délégué est tenu plus strictement que les étudiants : son point sert
     * de référence à toute la classe.
     */
    public function test_delegue_threshold_is_stricter_than_the_student_one(): void
    {
        $this->assertLessThan(
            config('presence.max_check_in_accuracy_meters'),
            config('presence.max_position_accuracy_meters'),
        );

        $seance = $this->seance();
        $etudiant = $this->etudiant();
        $entreLesDeux = config('presence.max_position_accuracy_meters') + 1;

        $this->actingAs($this->delegue(), 'sanctum')
            ->postJson("/api/seances/{$seance->id}/position", [
                'latitude' => 4.05, 'longitude' => 9.7, 'accuracy' => $entreLesDeux,
            ])
            ->assertUnprocessable();

        $this->actingAs($this->delegue(), 'sanctum')
            ->postJson("/api/seances/{$seance->id}/position", [
                'latitude' => 4.05, 'longitude' => 9.7, 'accuracy' => 10,
            ])->assertCreated();

        // La même incertitude reste acceptable pour un étudiant.
        $this->actingAs($etudiant, 'sanctum')
            ->postJson("/api/seances/{$seance->id}/check-in", [
                'latitude' => 4.05, 'longitude' => 9.7, 'accuracy' => $entreLesDeux,
            ])
            ->assertOk();
    }
}
