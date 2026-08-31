<?php

namespace Database\Factories;

use App\Models\Niveau;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Niveau>
 */
class NiveauFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Un pool de 5 valeurs fixes ("L1".."M2") s'épuise vite sur une
        // grande suite de tests (l'état "unique" de Faker n'est jamais reset
        // entre tests sans le trait WithFaker) — les tests qui ont besoin
        // d'un vrai nom de niveau ("L3" etc.) le passent déjà en override.
        return [
            'nom' => fake()->unique()->lexify('Niveau-????'),
        ];
    }
}
