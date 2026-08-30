<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Enums\ValidationStatus;
use App\Models\Salle;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'phone' => fake()->unique()->numerify('6#########'),
            'password' => static::$password ??= Hash::make('password'),
            'role' => UserRole::Etudiant,
            'validation_status' => ValidationStatus::Approved,
        ];
    }

    public function etudiant(?Salle $salle = null): static
    {
        return $this->state(function () use ($salle) {
            $salle ??= Salle::factory()->create();

            return [
                'role' => UserRole::Etudiant,
                'validation_status' => ValidationStatus::Approved,
                'formation' => $salle->formation,
                'salle_id' => $salle->id,
                'filiere_id' => $salle->filiere_id,
                'niveau_id' => $salle->filiere->niveau_id,
            ];
        });
    }

    public function delegue(?Salle $salle = null): static
    {
        return $this->state(function () use ($salle) {
            $salle ??= Salle::factory()->create();

            return [
                'role' => UserRole::Delegue,
                'validation_status' => ValidationStatus::Approved,
                'formation' => $salle->formation,
                'salle_id' => $salle->id,
                'filiere_id' => $salle->filiere_id,
                'niveau_id' => $salle->filiere->niveau_id,
            ];
        });
    }

    public function enseignant(): static
    {
        return $this->state(fn () => [
            'role' => UserRole::Enseignant,
            'validation_status' => ValidationStatus::Approved,
        ]);
    }

    public function admin(): static
    {
        return $this->state(fn () => [
            'role' => UserRole::Admin,
            'validation_status' => ValidationStatus::Approved,
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn () => ['validation_status' => ValidationStatus::None]);
    }
}
