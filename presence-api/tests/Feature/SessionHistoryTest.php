<?php

namespace Tests\Feature;

use App\Models\Filiere;
use App\Models\Niveau;
use App\Models\Salle;
use App\Models\Seance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SessionHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_filter_history_by_enseignant(): void
    {
        $admin = User::factory()->admin()->create();
        $niveau = Niveau::factory()->create();
        $filiere = Filiere::factory()->create(['niveau_id' => $niveau->id]);
        $salle = Salle::factory()->create(['filiere_id' => $filiere->id]);

        $prof1 = User::factory()->enseignant()->create();
        $prof2 = User::factory()->enseignant()->create();

        Seance::factory()->create(['salle_id' => $salle->id, 'enseignant_id' => $prof1->id, 'etat_delegue' => 'present', 'etat_prof' => 'present']);
        Seance::factory()->create(['salle_id' => $salle->id, 'enseignant_id' => $prof2->id]);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson("/api/historique-seances?enseignant_id={$prof1->id}");

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals(1, $response->json('stats.present'));
    }

    /**
     * Les totaux portent sur l'ensemble filtré, pas sur la page affichée :
     * un compteur qui changerait à chaque « voir plus » ne voudrait rien dire.
     */
    public function test_stats_cover_the_whole_filtered_set_not_just_the_page(): void
    {
        $admin = User::factory()->admin()->create();
        $salle = Salle::factory()->create();

        Seance::factory()->count(3)->create([
            'salle_id' => $salle->id, 'etat_delegue' => 'present', 'etat_prof' => 'present',
        ]);
        Seance::factory()->count(2)->create(['salle_id' => $salle->id]);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/historique-seances?per_page=2');

        $response->assertOk();
        $this->assertCount(2, $response->json('data'), 'La page respecte per_page.');
        $this->assertSame(5, $response->json('stats.total'), 'Les totaux couvrent tout le filtre.');
        $this->assertSame(3, $response->json('stats.present'));
        $this->assertSame(2, $response->json('stats.absent'));
        $this->assertSame(3, $response->json('meta.last_page'));
    }

    public function test_non_admin_cannot_access_history(): void
    {
        $enseignant = User::factory()->enseignant()->create();

        $this->actingAs($enseignant, 'sanctum')
            ->getJson('/api/historique-seances')
            ->assertForbidden();
    }
}
