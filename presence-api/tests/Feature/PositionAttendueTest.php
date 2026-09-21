<?php

namespace Tests\Feature;

use App\Enums\FormationType;
use App\Models\Filiere;
use App\Models\PositionSeance;
use App\Models\PromotionTemporaire;
use App\Models\Salle;
use App\Models\Seance;
use App\Models\Semaine;
use App\Models\User;
use App\Notifications\PositionAttendue;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * L'étudiant qui veut pointer avant que la position soit envoyée fait
 * signe au délégué : une notification, une seule par séance.
 */
class PositionAttendueTest extends TestCase
{
    use RefreshDatabase;

    private Salle $salle;

    private Seance $seance;

    private User $delegue;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-02-04 09:00:00');
        $this->salle = Salle::factory()->create(['filiere_id' => Filiere::factory()->create()->id, 'formation' => FormationType::FI]);
        Semaine::factory()->create(['numero' => 1, 'date_debut' => '2026-02-02', 'date_fin' => '2026-02-08']);
        $this->seance = Seance::factory()->create([
            'salle_id' => $this->salle->id, 'semaine_id' => Semaine::first()->id,
            'date_seance' => '2026-02-04', 'jour' => 'MERCREDI', 'heure_debut' => '08:30', 'heure_fin' => '10:00',
        ]);
        $this->delegue = User::factory()->delegue($this->salle)->create(['name' => 'NGO Anastasie']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_the_first_student_notifies_the_delegue_and_the_next_ones_learn_it_is_done(): void
    {
        $awa = User::factory()->etudiant($this->salle)->create(['name' => 'Awa Ndiaye']);
        $paul = User::factory()->etudiant($this->salle)->create(['name' => 'Paul Essomba']);

        $this->actingAs($awa, 'sanctum')->postJson("/api/seances/{$this->seance->id}/position-attendue")
            ->assertOk()
            ->assertJsonPath('deja_prevenu', false)
            ->assertJsonPath('delegues', ['Anastasie']);

        $notification = $this->delegue->notifications()->first();
        $this->assertNotNull($notification);
        $this->assertSame(PositionAttendue::class, $notification->type);
        $this->assertSame('position_attendue', $notification->data['type']);
        $this->assertSame($this->seance->id, $notification->data['seance_id']);
        $this->assertStringContainsString('Awa Ndiaye voudrait pointer', $notification->data['message']);

        $this->actingAs($paul, 'sanctum')->postJson("/api/seances/{$this->seance->id}/position-attendue")
            ->assertOk()
            ->assertJsonPath('deja_prevenu', true);
        $this->assertSame(1, $this->delegue->notifications()->count(), 'Une seule notification par séance.');
    }

    public function test_a_temporarily_promoted_student_is_notified_too(): void
    {
        Notification::fake();
        $remplacant = User::factory()->etudiant($this->salle)->create();
        PromotionTemporaire::create([
            'etudiant_id' => $remplacant->id, 'promoteur_id' => $this->seance->enseignant_id,
            'date_debut' => now()->subHour(), 'date_fin' => now()->addHours(2), 'duree_minutes' => 180,
        ]);

        $this->actingAs(User::factory()->etudiant($this->salle)->create(), 'sanctum')
            ->postJson("/api/seances/{$this->seance->id}/position-attendue")->assertOk();

        Notification::assertSentTo([$this->delegue, $remplacant], PositionAttendue::class);
    }

    public function test_it_is_refused_once_the_position_is_there_or_outside_the_window_or_from_another_salle(): void
    {
        Notification::fake();
        $etudiant = User::factory()->etudiant($this->salle)->create();

        $this->actingAs(User::factory()->etudiant(Salle::factory()->create())->create(), 'sanctum')
            ->postJson("/api/seances/{$this->seance->id}/position-attendue")->assertForbidden();
        $this->actingAs($this->delegue, 'sanctum')
            ->postJson("/api/seances/{$this->seance->id}/position-attendue")->assertForbidden();

        Carbon::setTestNow('2026-02-04 12:00:00');
        $this->actingAs($etudiant, 'sanctum')
            ->postJson("/api/seances/{$this->seance->id}/position-attendue")
            ->assertUnprocessable()->assertJsonValidationErrors(['seance']);

        Carbon::setTestNow('2026-02-04 09:00:00');
        PositionSeance::create(['seance_id' => $this->seance->id, 'delegue_id' => $this->delegue->id, 'latitude' => 4.05, 'longitude' => 9.76, 'precision_metres' => 10]);
        $this->actingAs($etudiant, 'sanctum')
            ->postJson("/api/seances/{$this->seance->id}/position-attendue")
            ->assertUnprocessable()->assertJsonValidationErrors(['position']);

        Notification::assertNothingSent();
    }
}
