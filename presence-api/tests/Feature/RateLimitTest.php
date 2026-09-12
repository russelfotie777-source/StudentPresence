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
     * La cible la plus importante : sans limite, un mot de passe se devine
     * à l'infini sur des identifiants prévisibles (matricules 24I01234).
     */
    public function test_login_is_limited_to_five_attempts_per_account(): void
    {
        $etudiant = User::factory()->etudiant()->create(['password' => bcrypt('bon-mot-de-passe')]);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/login', ['phone' => $etudiant->phone, 'password' => 'faux'])
                ->assertStatus(422);
        }

        // Même le bon mot de passe est refusé une fois la limite atteinte :
        // c'est ce qui rend la devinette inopérante.
        $this->postJson('/api/auth/login', ['phone' => $etudiant->phone, 'password' => 'bon-mot-de-passe'])
            ->assertStatus(429);
    }

    /**
     * Tout le campus sort par la même adresse publique : cinq échecs sur un
     * compte ne doivent pas bloquer la connexion d'une autre personne.
     */
    public function test_login_limit_is_per_account_not_per_address(): void
    {
        $cible = User::factory()->etudiant()->create();
        $voisin = User::factory()->etudiant()->create(['password' => bcrypt('son-mot-de-passe')]);

        for ($i = 0; $i < 6; $i++) {
            $this->postJson('/api/auth/login', ['phone' => $cible->phone, 'password' => 'faux']);
        }
        $this->postJson('/api/auth/login', ['phone' => $cible->phone, 'password' => 'faux'])
            ->assertStatus(429);

        // Même adresse (même client de test), autre compte : connexion normale.
        $this->postJson('/api/auth/login', ['phone' => $voisin->phone, 'password' => 'son-mot-de-passe'])
            ->assertOk();
    }

    /**
     * L'identifiant est normalisé avant de compter : varier la casse ou les
     * espaces ne doit pas offrir cinq essais supplémentaires.
     */
    public function test_login_limit_cannot_be_dodged_by_varying_the_identifier(): void
    {
        $cible = User::factory()->etudiant()->create(['phone' => '24I09001']);

        foreach (['24I09001', '24i09001', ' 24I09001', '24I09001 ', '24i09001 '] as $variante) {
            $this->postJson('/api/auth/login', ['phone' => $variante, 'password' => 'faux']);
        }

        $this->postJson('/api/auth/login', ['phone' => '24I09001', 'password' => 'faux'])
            ->assertStatus(429);
    }

    public function test_registration_is_limited_per_address(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $this->postJson('/api/auth/register')->assertStatus(422);
        }

        $this->postJson('/api/auth/register')->assertStatus(429);
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
