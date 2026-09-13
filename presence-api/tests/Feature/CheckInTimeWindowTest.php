<?php

namespace Tests\Feature;

use App\Enums\FormationType;
use App\Models\Filiere;
use App\Models\Niveau;
use App\Models\Salle;
use App\Models\Seance;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Le pointage étudiant n'est accepté que dans la fenêtre ±15 min autour de
 * l'horaire prévu — la même que celle du marquage délégué (Seance::isActive).
 * Sans ce garde-fou, un étudiant pouvait se pointer sur n'importe quelle
 * séance passée de sa salle tant qu'elle n'était pas verrouillée.
 */
class CheckInTimeWindowTest extends TestCase
{
    use RefreshDatabase;

    private Salle $salle;

    private Niveau $niveau;

    protected function setUp(): void
    {
        parent::setUp();

        $this->niveau = Niveau::factory()->create();
        $filiere = Filiere::factory()->create(['niveau_id' => $this->niveau->id]);
        $this->salle = Salle::factory()->create(['filiere_id' => $filiere->id, 'formation' => FormationType::FI]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** Séance 08:30–10:00 le 04/02/2026, avec la position du délégué déjà posée. */
    private function seanceAvecPosition(): Seance
    {
        $seance = Seance::factory()->create([
            'salle_id' => $this->salle->id,
            'date_seance' => '2026-02-04',
            'jour' => 'MERCREDI',
            'heure_debut' => '08:30',
            'heure_fin' => '10:00',
        ]);

        $delegue = User::factory()->delegue($this->salle)->create(['niveau_id' => $this->niveau->id]);
        $seance->position()->create([
            'delegue_id' => $delegue->id,
            'latitude' => 4.05,
            'longitude' => 9.7,
            'precision_metres' => 10,
        ]);

        return $seance;
    }

    private function pointer(User $etudiant, Seance $seance)
    {
        return $this->actingAs($etudiant, 'sanctum')
            ->postJson("/api/seances/{$seance->id}/check-in", [
                'latitude' => 4.05, 'longitude' => 9.7, 'accuracy' => 10,
            ]);
    }

    public function test_check_in_is_refused_before_the_window_opens(): void
    {
        $seance = $this->seanceAvecPosition();
        $etudiant = User::factory()->etudiant($this->salle)->create(['niveau_id' => $this->niveau->id]);

        // 16 min avant le début : la fenêtre ouvre à 08:15.
        Carbon::setTestNow('2026-02-04 08:14:00');

        $this->pointer($etudiant, $seance)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('seance');

        $this->assertDatabaseMissing('presences_etudiants', ['etudiant_id' => $etudiant->id]);
    }

    public function test_check_in_is_accepted_at_the_edges_of_the_window(): void
    {
        $seance = $this->seanceAvecPosition();
        $avant = User::factory()->etudiant($this->salle)->create(['niveau_id' => $this->niveau->id]);
        $apres = User::factory()->etudiant($this->salle)->create(['niveau_id' => $this->niveau->id]);

        // Juste après l'ouverture (08:15) et juste avant la fermeture (10:15).
        Carbon::setTestNow('2026-02-04 08:16:00');
        $this->pointer($avant, $seance)->assertOk();

        Carbon::setTestNow('2026-02-04 10:14:00');
        $this->pointer($apres, $seance)->assertOk();
    }

    /**
     * C'est le trou d'origine : bien après la fin du cours, sur une séance
     * jamais verrouillée par le délégué.
     */
    public function test_check_in_is_refused_long_after_the_session_ended(): void
    {
        $seance = $this->seanceAvecPosition();
        $etudiant = User::factory()->etudiant($this->salle)->create(['niveau_id' => $this->niveau->id]);

        Carbon::setTestNow('2026-02-06 15:00:00');

        $this->pointer($etudiant, $seance)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('seance');

        $this->assertDatabaseMissing('presences_etudiants', ['etudiant_id' => $etudiant->id]);
    }

    /**
     * Le contrôle temporel passe avant le verrou : hors fenêtre, c'est la
     * raison la plus utile à donner, même si la séance est aussi verrouillée.
     */
    public function test_window_message_takes_precedence_over_the_lock(): void
    {
        $seance = $this->seanceAvecPosition();
        $seance->update(['presences_locked' => true]);
        $etudiant = User::factory()->etudiant($this->salle)->create(['niveau_id' => $this->niveau->id]);

        Carbon::setTestNow('2026-02-06 15:00:00');

        $reponse = $this->pointer($etudiant, $seance)->assertUnprocessable();

        $this->assertStringContainsString('fenêtre', $reponse->json('errors.seance.0'));
    }
}
