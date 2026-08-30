<?php

namespace Database\Factories;

use App\Models\CourseTemplate;
use App\Models\Seance;
use App\Models\Semaine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Seance>
 */
class SeanceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $template = CourseTemplate::factory()->create();

        return [
            'course_template_id' => $template->id,
            'semaine_id' => Semaine::factory(),
            'salle_id' => $template->salle_id,
            'enseignant_id' => $template->enseignant_id,
            'groupe' => $template->groupe,
            'date_seance' => $template->date_debut,
            'jour' => $template->jour,
            'heure_debut' => $template->heure_debut,
            'heure_fin' => $template->heure_fin,
        ];
    }
}
