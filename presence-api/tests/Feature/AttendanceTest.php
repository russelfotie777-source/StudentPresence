<?php

namespace Tests\Feature;

use App\Enums\FormationType;
use App\Enums\PresenceState;
use App\Enums\PushStatus;
use App\Models\Filiere;
use App\Models\Niveau;
use App\Models\Salle;
use App\Models\Seance;
use App\Models\Semaine;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceTest extends TestCase
{
    use RefreshDatabase;

    private Salle $salle;

    private Niveau $niveau;

    private Filiere $filiere;

    protected function setUp(): void
    {
        parent::setUp();

        $this->niveau = Niveau::factory()->create();
        $this->filiere = Filiere::factory()->create(['niveau_id' => $this->niveau->id]);
        $this->salle = Salle::factory()->create(['filiere_id' => $this->filiere->id, 'formation' => FormationType::FI]);

        Semaine::factory()->create([
            'numero' => 1,
            'date_debut' => '2026-02-02', // lundi
            'date_fin' => '2026-02-08',
        ]);
    }

    /** Séance active mercredi 04/02/2026 08:30-10:00, "maintenant" figé à 09:00. */
    private function activeSeance(): Seance
    {
        Carbon::setTestNow('2026-02-04 09:00:00');

        return Seance::factory()->create([
            'salle_id' => $this->salle->id,
            'semaine_id' => Semaine::first()->id,
            'date_seance' => '2026-02-04',
            'jour' => 'MERCREDI',
            'heure_debut' => '08:30',
            'heure_fin' => '10:00',
        ]);
    }

    private function delegue(): User
    {
        return User::factory()->delegue($this->salle)->create(['niveau_id' => $this->niveau->id]);
    }

    private function etudiant(): User
    {
        return User::factory()->etudiant($this->salle)->create(['niveau_id' => $this->niveau->id]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_student_check_in_fails_without_delegue_position(): void
    {
        $seance = $this->activeSeance();
        $etudiant = $this->etudiant();

        $this->actingAs($etudiant, 'sanctum')
            ->postJson("/api/seances/{$seance->id}/check-in", ['latitude' => 4.05, 'longitude' => 9.7])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('position');
    }

    public function test_student_check_in_fails_outside_geofence(): void
    {
        $seance = $this->activeSeance();
        $delegue = $this->delegue();
        $etudiant = $this->etudiant();

        $this->actingAs($delegue, 'sanctum')
            ->postJson("/api/seances/{$seance->id}/position", ['latitude' => 4.0500, 'longitude' => 9.7000])
            ->assertCreated();

        // ~1.1km plus loin (0.01° de latitude ≈ 1.1km) — largement au-delà des 120m.
        $this->actingAs($etudiant, 'sanctum')
            ->postJson("/api/seances/{$seance->id}/check-in", ['latitude' => 4.0600, 'longitude' => 9.7000])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('position');

        $this->assertDatabaseMissing('presences_etudiants', ['etudiant_id' => $etudiant->id]);
    }

    public function test_student_check_in_succeeds_within_geofence(): void
    {
        $seance = $this->activeSeance();
        $delegue = $this->delegue();
        $etudiant = $this->etudiant();

        $this->actingAs($delegue, 'sanctum')
            ->postJson("/api/seances/{$seance->id}/position", ['latitude' => 4.0500, 'longitude' => 9.7000])
            ->assertCreated();

        // ~11m plus loin (0.0001° de latitude ≈ 11m) — dans le rayon de 120m.
        $this->actingAs($etudiant, 'sanctum')
            ->postJson("/api/seances/{$seance->id}/check-in", ['latitude' => 4.0501, 'longitude' => 9.7000])
            ->assertOk();

        $this->assertDatabaseHas('presences_etudiants', [
            'etudiant_id' => $etudiant->id,
            'seance_id' => $seance->id,
            'etat' => PresenceState::Present->value,
        ]);
    }

    public function test_confirm_roster_requires_a_push(): void
    {
        $seance = $this->activeSeance();
        $delegue = $this->delegue();

        $this->actingAs($delegue, 'sanctum')
            ->postJson("/api/seances/{$seance->id}/confirm-roster", ['etudiants' => []])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('seance');
    }

    public function test_confirm_roster_respects_push_headcount_cap(): void
    {
        $seance = $this->activeSeance();
        $delegue = $this->delegue();
        $seance->pushRequest()->create(['etudiants_presents' => 1, 'status' => PushStatus::Pending]);

        $etudiants = User::factory()->count(2)->create([
            'role' => 'Etudiant', 'salle_id' => $this->salle->id, 'niveau_id' => $this->niveau->id,
            'formation' => FormationType::FI,
        ]);

        $this->actingAs($delegue, 'sanctum')
            ->postJson("/api/seances/{$seance->id}/confirm-roster", ['etudiants' => $etudiants->pluck('id')])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('etudiants');
    }

    public function test_confirm_roster_locks_and_overrides_student_self_checkin(): void
    {
        $seance = $this->activeSeance();
        $delegue = $this->delegue();
        $seance->pushRequest()->create(['etudiants_presents' => 2, 'status' => PushStatus::Pending]);

        [$etudiantA, $etudiantB] = User::factory()->count(2)->create([
            'role' => 'Etudiant', 'salle_id' => $this->salle->id, 'niveau_id' => $this->niveau->id,
            'formation' => FormationType::FI,
        ]);

        // L'étudiant A s'est auto-pointé présent, mais le délégué ne le
        // sélectionne pas dans sa confirmation finale : le choix du délégué
        // doit l'emporter (comportement hérité de l'ancienne app).
        $seance->presences()->create(['etudiant_id' => $etudiantA->id, 'etat' => 'present']);

        $response = $this->actingAs($delegue, 'sanctum')
            ->postJson("/api/seances/{$seance->id}/confirm-roster", ['etudiants' => [$etudiantB->id]]);

        $response->assertOk();

        $this->assertDatabaseHas('presences_etudiants', ['etudiant_id' => $etudiantA->id, 'etat' => 'absent']);
        $this->assertDatabaseHas('presences_etudiants', ['etudiant_id' => $etudiantB->id, 'etat' => 'present']);
        $this->assertDatabaseHas('seances', ['id' => $seance->id, 'presences_locked' => true]);
        $this->assertDatabaseHas('pushes', ['seance_id' => $seance->id, 'status' => PushStatus::Approved->value]);

        // Séance verrouillée : le pointage étudiant doit maintenant être refusé.
        $this->actingAs($etudiantA, 'sanctum')
            ->postJson("/api/seances/{$seance->id}/check-in", ['latitude' => 4.05, 'longitude' => 9.7])
            ->assertUnprocessable();
    }

    public function test_quota_is_credited_only_once_even_if_delegue_resubmits_present(): void
    {
        $seance = $this->activeSeance(); // "now" figé à 2026-02-04 09:00:00
        $delegue = $this->delegue();
        $enseignant = $seance->enseignant;
        $quotaBefore = $enseignant->quota;

        $this->actingAs($delegue, 'sanctum')
            ->postJson("/api/seances/{$seance->id}/mark-delegue", [
                'etat' => 'present',
                'set_debut_reel' => true,
            ])->assertOk();

        // ~55 min plus tard, toujours dans la fenêtre active (08:30-10:00 +15min).
        Carbon::setTestNow('2026-02-04 09:55:00');

        $this->actingAs($delegue, 'sanctum')
            ->postJson("/api/seances/{$seance->id}/mark-delegue", [
                'etat' => 'present',
                'set_fin_reelle' => true,
            ])->assertOk();

        $this->assertEquals($quotaBefore + 1, $enseignant->fresh()->quota);

        // Re-soumission "présent" sans rien changer : ne doit pas re-créditer.
        $this->actingAs($delegue, 'sanctum')
            ->postJson("/api/seances/{$seance->id}/mark-delegue", ['etat' => 'present'])
            ->assertOk();

        $this->assertEquals($quotaBefore + 1, $enseignant->fresh()->quota);
    }
}
