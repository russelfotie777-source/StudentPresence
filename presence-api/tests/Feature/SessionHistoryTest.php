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
        $this->assertCount(1, $response->json('seances'));
        $this->assertEquals(1, $response->json('stats.present'));
    }

    public function test_non_admin_cannot_access_history(): void
    {
        $enseignant = User::factory()->enseignant()->create();

        $this->actingAs($enseignant, 'sanctum')
            ->getJson('/api/historique-seances')
            ->assertForbidden();
    }
}
