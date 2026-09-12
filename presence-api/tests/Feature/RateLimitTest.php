<?php

namespace Tests\Feature;

use App\Models\Salle;
use App\Models\Seance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Depuis Laravel 11, aucune limite de débit n'est appliquée à l'API par
 * défaut : chaque route sensible doit porter la sienne explicitement.
 *
 * Les limites des routes authentifiées sont comptées par utilisateur, pas
 * par adresse IP — indispensable ici, où toute une classe pointe au même
 * moment depuis le même Wi-Fi de campus, donc la même adresse publique.
 */
class RateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_check_in_is_limited_to_twenty_per_minute_per_student(): void
    {
        $salle = Salle::factory()->create();
        $seance = Seance::factory()->create(['salle_id' => $salle->id]);
        $etudiant = User::factory()->etudiant($salle)->create();

        // Un corps vide échoue vite en validation, mais compte quand même :
        // le limiteur s'exécute avant le contrôleur.
        for ($i = 0; $i < 20; $i++) {
            $this->actingAs($etudiant, 'sanctum')
                ->postJson("/api/seances/{$seance->id}/check-in")
                ->assertStatus(422);
        }

        $this->actingAs($etudiant, 'sanctum')
            ->postJson("/api/seances/{$seance->id}/check-in")
            ->assertStatus(429);
    }

    public function test_position_is_limited_to_twenty_per_minute_per_delegue(): void
    {
        $salle = Salle::factory()->create();
        $seance = Seance::factory()->create(['salle_id' => $salle->id]);
        $delegue = User::factory()->delegue($salle)->create();

        for ($i = 0; $i < 20; $i++) {
            $this->actingAs($delegue, 'sanctum')
                ->postJson("/api/seances/{$seance->id}/position")
                ->assertStatus(422);
        }

        $this->actingAs($delegue, 'sanctum')
            ->postJson("/api/seances/{$seance->id}/position")
            ->assertStatus(429);
    }

    /**
     * Toute une classe pointe depuis la même adresse : la limite d'un
     * étudiant ne doit pas entamer celle de son voisin.
     */
    public function test_check_in_limit_is_per_student_not_per_address(): void
    {
        $salle = Salle::factory()->create();
        $seance = Seance::factory()->create(['salle_id' => $salle->id]);
        $premier = User::factory()->etudiant($salle)->create();
        $second = User::factory()->etudiant($salle)->create();

        for ($i = 0; $i < 20; $i++) {
            $this->actingAs($premier, 'sanctum')->postJson("/api/seances/{$seance->id}/check-in");
        }
        $this->actingAs($premier, 'sanctum')
            ->postJson("/api/seances/{$seance->id}/check-in")
            ->assertStatus(429);

        // Même adresse (même client de test), autre compte : pas bloqué.
        $this->actingAs($second, 'sanctum')
            ->postJson("/api/seances/{$seance->id}/check-in")
            ->assertStatus(422);
    }
}
