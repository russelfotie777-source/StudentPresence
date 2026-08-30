<?php

namespace Database\Factories;

use App\Enums\Weekday;
use App\Models\CourseTemplate;
use App\Models\Matiere;
use App\Models\Salle;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CourseTemplate>
 */
class CourseTemplateFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'matiere_id' => Matiere::factory(),
            'enseignant_id' => User::factory()->enseignant(),
            'salle_id' => Salle::factory(),
            'groupe' => 'G1',
            'jour' => fake()->randomElement(Weekday::cases())->value,
            'heure_debut' => '08:00',
            'heure_fin' => '10:00',
            'date_debut' => '2026-01-05',
            'date_fin' => '2026-04-05',
            'actif' => true,
        ];
    }
}
