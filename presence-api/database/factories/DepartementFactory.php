<?php

namespace Database\Factories;

use App\Models\Departement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Departement>
 */
class DepartementFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Pool généré plutôt que fixe, pour la même raison que les autres
        // fabriques du catalogue : l'état "unique" de Faker n'est jamais
        // remis à zéro entre les tests.
        return [
            'nom' => fake()->unique()->lexify('Département ????'),
            'code' => strtoupper(fake()->unique()->lexify('???')),
            'nom_en' => null,
        ];
    }
}
