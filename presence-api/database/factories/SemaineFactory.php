<?php

namespace Database\Factories;

use App\Models\Semaine;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Semaine>
 */
class SemaineFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $numero = fake()->unique()->numberBetween(1, 999);
        $debut = Carbon::parse('2026-01-05')->addWeeks($numero - 1); // un lundi

        return [
            'numero' => $numero,
            'date_debut' => $debut->toDateString(),
            'date_fin' => $debut->clone()->addDays(6)->toDateString(),
        ];
    }
}
