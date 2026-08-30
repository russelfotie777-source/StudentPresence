<?php

namespace Tests\Feature;

use App\Enums\PresenceState;
use App\Models\Filiere;
use App\Models\Niveau;
use App\Models\Salle;
use App\Models\Seance;
use App\Models\TarifHeure;
use App\Models\User;
use App\Services\PayrollCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollCalculatorTest extends TestCase
{
    use RefreshDatabase;

    private function seanceWithTiming(User $enseignant, float $tarifHeure, string $heureDebut, string $debutReel, string $finReelle): Seance
    {
        $niveau = Niveau::factory()->create();
        TarifHeure::create(['niveau_id' => $niveau->id, 'tarif_heure' => $tarifHeure]);
        $filiere = Filiere::factory()->create(['niveau_id' => $niveau->id]);
        $salle = Salle::factory()->create(['filiere_id' => $filiere->id]);

        return Seance::factory()->create([
            'salle_id' => $salle->id,
            'enseignant_id' => $enseignant->id,
            'heure_debut' => $heureDebut,
            'heure_fin' => '23:59', // large, non-limitant pour le test
            'debut_reel' => $debutReel,
            'fin_reelle' => $finReelle,
            'etat_delegue' => PresenceState::Present->value,
            'etat_prof' => PresenceState::Present->value,
        ]);
    }

    public function test_full_hour_on_time_pays_full_rate(): void
    {
        $enseignant = User::factory()->enseignant()->create();
        // Pile à l'heure, séance d'1h -> salaire = tarif plein.
        $this->seanceWithTiming($enseignant, 2000, '08:00', '08:00', '09:00');

        $result = (new PayrollCalculator)->forTeacher($enseignant);

        $this->assertEquals(2000.0, $result->totalSalaire);
        $this->assertEquals(0.0, $result->totalPenaliteRetard);
        $this->assertEquals(0, $result->lines->first()->retardMinutes);
    }

    public function test_short_session_under_45_minutes_is_prorated(): void
    {
        $enseignant = User::factory()->enseignant()->create();
        // 30 min, à l'heure -> 2000 * 30/60 = 1000.
        $this->seanceWithTiming($enseignant, 2000, '08:00', '08:00', '08:30');

        $result = (new PayrollCalculator)->forTeacher($enseignant);

        $this->assertEquals(1000.0, $result->totalSalaire);
    }

    public function test_retard_between_15_and_30_minutes_uses_strict_prorata(): void
    {
        $enseignant = User::factory()->enseignant()->create();
        // 20 min de retard, séance d'1h effective -> prorata strict = tarif plein
        // (contrairement au palier <15 qui arrondirait au plein dès 45min).
        $this->seanceWithTiming($enseignant, 3000, '08:00', '08:20', '09:20');

        $line = (new PayrollCalculator)->forTeacher($enseignant)->lines->first();

        $this->assertEquals(20, $line->retardMinutes);
        $this->assertEquals(3000.0, $line->salaire); // 60min pile -> plein tarif au prorata aussi
    }

    public function test_retard_between_30_and_40_minutes_halves_the_rate(): void
    {
        $enseignant = User::factory()->enseignant()->create();
        // 35 min de retard, séance de 45min+ -> demi-tarif.
        $this->seanceWithTiming($enseignant, 2000, '08:00', '08:35', '09:35');

        $line = (new PayrollCalculator)->forTeacher($enseignant)->lines->first();

        $this->assertEquals(35, $line->retardMinutes);
        $this->assertEquals(1000.0, $line->salaire);
        $this->assertEquals(1000.0, $line->penaliteRetard);
    }

    public function test_retard_of_40_minutes_or_more_is_unpaid(): void
    {
        $enseignant = User::factory()->enseignant()->create();
        $this->seanceWithTiming($enseignant, 2000, '08:00', '08:40', '09:40');

        $line = (new PayrollCalculator)->forTeacher($enseignant)->lines->first();

        $this->assertEquals(0.0, $line->salaire);
        $this->assertEquals(2000.0, $line->penaliteRetard);
    }

    public function test_early_arrival_is_not_penalized(): void
    {
        $enseignant = User::factory()->enseignant()->create();
        // Arrivé 10 min EN AVANCE -> ne doit jamais être traité comme "retard".
        $this->seanceWithTiming($enseignant, 2000, '08:10', '08:00', '09:10');

        $line = (new PayrollCalculator)->forTeacher($enseignant)->lines->first();

        $this->assertEquals(-10, $line->retardMinutes);
        $this->assertEquals(2000.0, $line->salaire);
        $this->assertEquals(0.0, $line->penaliteRetard);
    }

    public function test_seances_not_marked_present_are_excluded(): void
    {
        $enseignant = User::factory()->enseignant()->create();
        $seance = $this->seanceWithTiming($enseignant, 2000, '08:00', '08:00', '09:00');
        $seance->update(['etat_delegue' => 'absent']); // etat_final devient "absent"

        $result = (new PayrollCalculator)->forTeacher($enseignant);

        $this->assertCount(0, $result->lines);
        $this->assertEquals(0.0, $result->totalSalaire);
    }
}
