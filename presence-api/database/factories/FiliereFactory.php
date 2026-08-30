<?php

namespace Database\Factories;

use App\Models\Filiere;
use App\Models\Niveau;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Filiere>
 */
class FiliereFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nom' => fake()->unique()->randomElement(['Informatique', 'Génie Civil', 'Génie Électrique', 'Gestion']),
            'niveau_id' => Niveau::factory(),
        ];
    }
}
