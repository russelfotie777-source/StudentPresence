<?php

namespace Tests\Feature;

use App\Models\PromotionTemporaire;
use App\Models\Salle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Gardes anti-N+1 : on ne vérifie pas un nombre de requêtes absolu (trop
 * fragile, il bouge à la moindre jointure ajoutée), mais le fait que ce
 * nombre reste constant quand le nombre de lignes retournées augmente.
 * C'est exactement ce qui distingue une requête groupée d'un N+1.
 */
class QueryPerformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_validation_list_query_count_does_not_grow_with_users(): void
    {
        $admin = User::factory()->admin()->create();

        User::factory()->delegue()->count(2)->create();
        $withTwo = $this->countQueries(
            fn () => $this->actingAs($admin, 'sanctum')->getJson('/api/validations')->assertOk()
        );

        User::factory()->delegue()->count(8)->create();
        $withTen = $this->countQueries(
            fn () => $this->actingAs($admin, 'sanctum')->getJson('/api/validations')->assertOk()
        );

        $this->assertSame(
            $withTwo,
            $withTen,
            "Le nombre de requêtes doit être constant : {$withTwo} pour 2 comptes, {$withTen} pour 10 — ".
            'UserResource rappelle hasActivePromotion()/effectiveRole() sans chargement anticipé.'
        );
    }

    public function test_student_search_query_count_does_not_grow_with_students(): void
    {
        $salle = Salle::factory()->create();
        $delegue = User::factory()->delegue($salle)->create();

        User::factory()->etudiant($salle)->count(2)->create();
        $withTwo = $this->countQueries(
            fn () => $this->actingAs($delegue, 'sanctum')->getJson('/api/students/search')->assertOk()
        );

        User::factory()->etudiant($salle)->count(8)->create();
        $withTen = $this->countQueries(
            fn () => $this->actingAs($delegue, 'sanctum')->getJson('/api/students/search')->assertOk()
        );

        $this->assertSame($withTwo, $withTen);
    }

    /**
     * La promotion active doit rester correctement détectée une fois la
     * relation chargée en amont : sans ça, l'optimisation renverrait
     * silencieusement de faux "pas de promotion".
     */
    public function test_eager_loaded_active_promotion_is_still_detected(): void
    {
        $salle = Salle::factory()->create();
        $promu = User::factory()->etudiant($salle)->create();
        $enseignant = User::factory()->enseignant()->create();

        PromotionTemporaire::create([
            'etudiant_id' => $promu->id,
            'promoteur_id' => $enseignant->id,
            'date_debut' => now(),
            'date_fin' => now()->addHour(),
            'duree_minutes' => 60,
        ]);

        $charge = User::query()->withActivePromotions()->find($promu->id);

        $this->assertTrue($charge->relationLoaded('promotionsRecues'));
        $this->assertTrue($charge->hasActivePromotion());
    }

    public function test_eager_loaded_expired_promotion_is_not_counted_as_active(): void
    {
        $salle = Salle::factory()->create();
        $etudiant = User::factory()->etudiant($salle)->create();
        $enseignant = User::factory()->enseignant()->create();

        PromotionTemporaire::create([
            'etudiant_id' => $etudiant->id,
            'promoteur_id' => $enseignant->id,
            'date_debut' => now()->subHours(3),
            'date_fin' => now()->subHour(),
            'duree_minutes' => 120,
        ]);

        $charge = User::query()->withActivePromotions()->find($etudiant->id);

        $this->assertFalse($charge->hasActivePromotion());
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
