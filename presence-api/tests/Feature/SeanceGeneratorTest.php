<?php

namespace Tests\Feature;

use App\Enums\Weekday;
use App\Models\CourseTemplate;
use App\Models\Salle;
use App\Models\Seance;
use App\Models\Semaine;
use App\Models\User;
use App\Services\SeanceGenerator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeanceGeneratorTest extends TestCase
{
    use RefreshDatabase;

    private function semaines(int $count, Carbon $firstMonday): void
    {
        foreach (range(0, $count - 1) as $i) {
            $debut = $firstMonday->clone()->addWeeks($i);
            Semaine::factory()->create([
                'numero' => $i + 1,
                'date_debut' => $debut->toDateString(),
                'date_fin' => $debut->clone()->addDays(6)->toDateString(),
            ]);
        }
    }

    public function test_generates_one_seance_per_week_in_range(): void
    {
        $firstMonday = Carbon::parse('2026-01-05');
        $this->semaines(4, $firstMonday);

        $template = CourseTemplate::factory()->create([
            'jour' => Weekday::Mercredi->value,
            'date_debut' => '2026-01-05',
            'date_fin' => '2026-01-31', // couvre les semaines 1 à 4
        ]);

        $result = (new SeanceGenerator)->generate($template);

        $this->assertCount(4, $result->created);
        $this->assertCount(0, $result->skipped);
        $this->assertDatabaseCount('seances', 4);

        // Le mercredi de la semaine 1 (lundi 05/01) est le 07/01.
        $this->assertDatabaseHas('seances', ['date_seance' => '2026-01-07', 'course_template_id' => $template->id]);
    }

    public function test_regenerating_is_idempotent(): void
    {
        $firstMonday = Carbon::parse('2026-01-05');
        $this->semaines(2, $firstMonday);

        $template = CourseTemplate::factory()->create([
            'jour' => Weekday::Lundi->value,
            'date_debut' => '2026-01-05',
            'date_fin' => '2026-01-18',
        ]);

        $generator = new SeanceGenerator;
        $first = $generator->generate($template);
        $second = $generator->generate($template);

        $this->assertCount(2, $first->created);
        $this->assertCount(0, $second->created);
        $this->assertCount(2, $second->skipped);
        $this->assertDatabaseCount('seances', 2);
    }

    public function test_skips_week_with_room_conflict_on_same_groupe(): void
    {
        $firstMonday = Carbon::parse('2026-01-05');
        $this->semaines(1, $firstMonday);
        $semaine = Semaine::first();

        $salle = Salle::factory()->create();

        // Séance déjà existante dans la même salle/créneau/groupe, sans lien
        // avec le template qu'on va générer (simule une séance saisie à la main).
        Seance::factory()->create([
            'salle_id' => $salle->id,
            'semaine_id' => $semaine->id,
            'groupe' => 'G1',
            'jour' => Weekday::Mardi->value,
            'heure_debut' => '08:00',
            'heure_fin' => '10:00',
            'course_template_id' => null,
        ]);

        $template = CourseTemplate::factory()->create([
            'salle_id' => $salle->id,
            'groupe' => 'G1',
            'jour' => Weekday::Mardi->value,
            'heure_debut' => '09:00', // chevauche 08:00-10:00
            'heure_fin' => '11:00',
            'date_debut' => '2026-01-05',
            'date_fin' => '2026-01-11',
        ]);

        $result = (new SeanceGenerator)->generate($template);

        $this->assertCount(0, $result->created);
        $this->assertCount(1, $result->skipped);
        $this->assertStringContainsString('Conflit de salle', $result->skipped->first()['reason']);
    }

    public function test_skips_week_when_teacher_double_booked_in_another_room(): void
    {
        $firstMonday = Carbon::parse('2026-01-05');
        $this->semaines(1, $firstMonday);
        $semaine = Semaine::first();

        $enseignant = User::factory()->enseignant()->create();
        $autreSalle = Salle::factory()->create();

        Seance::factory()->create([
            'enseignant_id' => $enseignant->id,
            'salle_id' => $autreSalle->id,
            'semaine_id' => $semaine->id,
            'jour' => Weekday::Jeudi->value,
            'heure_debut' => '14:00',
            'heure_fin' => '16:00',
            'course_template_id' => null,
        ]);

        $template = CourseTemplate::factory()->create([
            'enseignant_id' => $enseignant->id,
            'jour' => Weekday::Jeudi->value,
            'heure_debut' => '15:00', // chevauche, mais dans une AUTRE salle
            'heure_fin' => '17:00',
            'date_debut' => '2026-01-05',
            'date_fin' => '2026-01-11',
        ]);

        $result = (new SeanceGenerator)->generate($template);

        $this->assertCount(0, $result->created);
        $this->assertStringContainsString('déjà une séance', $result->skipped->first()['reason']);
    }
}
