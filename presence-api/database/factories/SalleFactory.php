<?php

namespace Database\Factories;

use App\Enums\FormationType;
use App\Models\Filiere;
use App\Models\Salle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Salle>
 */
class SalleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nom' => strtoupper(fake()->unique()->bothify('Salle-??##')),
            'filiere_id' => Filiere::factory(),
            'formation' => fake()->randomElement([FormationType::FI, FormationType::FA]),
        ];
    }
}
