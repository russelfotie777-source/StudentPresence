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
        // Un pool de 4 valeurs fixes s'épuise vite sur une grande suite de
        // tests (l'état "unique" de Faker n'est jamais reset entre tests
        // sans le trait WithFaker) — la vraie contrainte DB est de toute
        // façon (nom, niveau_id), pas nom seul.
        return [
            'nom' => fake()->unique()->lexify('Filière ????'),
            'niveau_id' => Niveau::factory(),
        ];
    }
}
