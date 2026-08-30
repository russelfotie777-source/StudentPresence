<?php

namespace Tests\Feature;

use App\Models\Niveau;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Le formulaire d'inscription (register.php dans l'ancienne app) a besoin de
 * lire niveaux/filières/salles SANS authentification, pour peupler ses
 * listes déroulantes — seule la création/modification doit rester réservée
 * à l'admin.
 */
class CatalogAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_niveaux_index_is_publicly_readable(): void
    {
        Niveau::factory()->create(['nom' => 'L3']);

        $this->getJson('/api/niveaux')->assertOk()->assertJsonCount(1);
    }

    public function test_creating_a_niveau_still_requires_admin(): void
    {
        $this->postJson('/api/niveaux', ['nom' => 'L3'])->assertUnauthorized();

        $enseignant = User::factory()->enseignant()->create();
        $this->actingAs($enseignant, 'sanctum')
            ->postJson('/api/niveaux', ['nom' => 'L3'])
            ->assertForbidden();
    }
}
