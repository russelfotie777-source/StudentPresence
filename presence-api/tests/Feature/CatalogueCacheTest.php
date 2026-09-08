<?php

namespace Tests\Feature;

use App\Models\Filiere;
use App\Models\Niveau;
use App\Models\Salle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Le catalogue académique est lu à chaque ouverture du formulaire
 * d'inscription et des écrans admin, mais ne change que quelques fois par an :
 * il est servi depuis le cache, périmé par version dès qu'une entité change.
 */
class CatalogueCacheTest extends TestCase
{
    use RefreshDatabase;

    public function test_niveaux_are_served_from_cache_on_the_second_call(): void
    {
        Niveau::factory()->count(3)->create();

        $this->assertGreaterThan(0, $this->countQueries(fn () => $this->getJson('/api/niveaux')->assertOk()));
        $this->assertSame(0, $this->countQueries(fn () => $this->getJson('/api/niveaux')->assertOk()));
    }

    public function test_creating_a_niveau_invalidates_the_cache(): void
    {
        Niveau::factory()->create(['nom' => 'L1']);
        $this->getJson('/api/niveaux')->assertOk();

        Niveau::factory()->create(['nom' => 'L2']);

        $this->getJson('/api/niveaux')->assertOk()->assertJsonCount(2);
    }

    public function test_renaming_a_niveau_invalidates_the_salle_listing_that_embeds_it(): void
    {
        $niveau = Niveau::factory()->create(['nom' => 'L2']);
        $filiere = Filiere::factory()->create(['niveau_id' => $niveau->id]);
        Salle::factory()->create(['filiere_id' => $filiere->id]);

        $this->getJson('/api/salles')->assertOk()->assertJsonPath('0.filiere.niveau.nom', 'L2');

        // Une entité liée change : la liste des salles embarque filiere.niveau,
        // elle doit donc être périmée elle aussi.
        $niveau->update(['nom' => 'L3']);

        $this->getJson('/api/salles')->assertOk()->assertJsonPath('0.filiere.niveau.nom', 'L3');
    }

    public function test_deleting_a_salle_invalidates_the_cache(): void
    {
        $salle = Salle::factory()->create();
        $this->getJson('/api/salles')->assertOk()->assertJsonCount(1);

        $salle->delete();

        $this->getJson('/api/salles')->assertOk()->assertJsonCount(0);
    }

    /**
     * Les listes filtrées ont leur propre clé : servir la liste d'une filière
     * à la place d'une autre serait pire qu'un cache absent.
     */
    public function test_filtered_listings_do_not_leak_across_filieres(): void
    {
        $filiereA = Filiere::factory()->create();
        $filiereB = Filiere::factory()->create();
        Salle::factory()->create(['filiere_id' => $filiereA->id, 'nom' => 'A23']);
        Salle::factory()->create(['filiere_id' => $filiereB->id, 'nom' => 'B12']);

        $this->getJson("/api/salles?filiere_id={$filiereA->id}")
            ->assertOk()->assertJsonCount(1)->assertJsonPath('0.nom', 'A23');

        $this->getJson("/api/salles?filiere_id={$filiereB->id}")
            ->assertOk()->assertJsonCount(1)->assertJsonPath('0.nom', 'B12');
    }

    /**
     * Garde-fou du bug qui a réellement cassé la production : les entrées
     * étaient mises en cache sous forme de collections Eloquent. Avec le store
     * `array` des tests, les objets restent en mémoire et tout fonctionne ;
     * avec un store qui sérialise (database en production), la relecture
     * rendait des __PHP_Incomplete_Class et l'endpoint répondait 500 dès le
     * deuxième appel. Ce test force un store sérialisant, seul moyen de voir
     * la panne.
     */
    public function test_catalogue_survives_a_serializing_cache_store(): void
    {
        config(['cache.default' => 'database']);
        Cache::store('database')->clear();

        $niveau = Niveau::factory()->create(['nom' => 'L3']);
        $filiere = Filiere::factory()->create(['niveau_id' => $niveau->id]);
        Salle::factory()->create(['filiere_id' => $filiere->id, 'nom' => 'A23']);

        foreach (['/api/niveaux', '/api/filieres', '/api/salles'] as $route) {
            $premier = $this->getJson($route)->assertOk();
            // Le deuxième appel est celui qui relit le cache : c'est lui qui
            // échouait.
            $second = $this->getJson($route)->assertOk();

            $this->assertSame(
                $premier->json(),
                $second->json(),
                "La réponse de {$route} doit être identique une fois servie depuis le cache.",
            );
        }

        $this->getJson('/api/salles')->assertOk()->assertJsonPath('0.filiere.niveau.nom', 'L3');
    }

    private function countQueries(callable $callback): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $callback();

        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }
}
