<?php

namespace Tests\Feature;

use App\Enums\FormationType;
use App\Models\Filiere;
use App\Models\Parametre;
use App\Models\Salle;
use App\Models\Seance;
use App\Models\Semaine;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Le délégué confirme la présence de l'enseignant à sa place, pour les
 * enseignants qui n'utilisent pas l'application. Pouvoir accordé par
 * l'admin, désactivé par défaut, tracé sur la séance, et que l'enseignant
 * peut toujours reprendre.
 */
class ConfirmationEnseignantTest extends TestCase
{
    use RefreshDatabase;

    private Salle $salle;

    protected function setUp(): void
    {
        parent::setUp();

        $this->salle = Salle::factory()->create(['filiere_id' => Filiere::factory()->create()->id, 'formation' => FormationType::FI]);
        Semaine::factory()->create(['numero' => 1, 'date_debut' => '2026-02-02', 'date_fin' => '2026-02-08']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_disabled_by_default_the_delegue_cannot_confirm(): void
    {
        $seance = $this->seanceActive();

        $this->actingAs($this->delegue(), 'sanctum')
            ->postJson("/api/seances/{$seance->id}/confirmer-enseignant")
            ->assertForbidden();

        $this->assertNull($seance->fresh()->etat_prof);
    }

    public function test_enabled_the_delegue_confirms_for_the_teacher_and_the_seance_counts_as_held(): void
    {
        Parametre::setDelegueConfirmeEnseignant(true);
        $seance = $this->seanceActive();
        $delegue = $this->delegue();

        $this->actingAs($delegue, 'sanctum')
            ->postJson("/api/seances/{$seance->id}/confirmer-enseignant")
            ->assertOk()
            ->assertJsonPath('etat_prof', 'present')
            ->assertJsonPath('etat_prof_par_delegue', true)
            ->assertJsonPath('etat_delegue', 'present')
            ->assertJsonPath('etat_final', 'present')
            ->assertJsonPath('confirmation_enseignant_par_delegue', true);

        $seance->refresh();
        $this->assertSame($delegue->id, $seance->etat_prof_marque_par_id);
        $this->assertNotNull($seance->debut_reel, "Confirmer vaut constat d'arrivée de l'enseignant.");
    }

    /**
     * Le « Présent » ordinaire du délégué ne parle que pour lui : même règle
     * activée, c'est un geste distinct et volontaire qui confirme pour
     * l'enseignant — lequel peut très bien venir répondre lui-même.
     */
    public function test_marking_present_as_delegue_never_answers_for_the_teacher(): void
    {
        Parametre::setDelegueConfirmeEnseignant(true);
        $seance = $this->seanceActive();

        $this->actingAs($this->delegue(), 'sanctum')
            ->postJson("/api/seances/{$seance->id}/mark-delegue", ['etat' => 'present', 'set_debut_reel' => true])
            ->assertOk()
            ->assertJsonPath('etat_delegue', 'present')
            ->assertJsonPath('etat_prof', null)
            ->assertJsonPath('etat_prof_par_delegue', false)
            ->assertJsonPath('etat_final', 'absent');

        $this->assertNull($seance->fresh()->etat_prof_marque_par_id);
    }

    public function test_the_teacher_who_answers_takes_over_the_delegue_confirmation(): void
    {
        Parametre::setDelegueConfirmeEnseignant(true);
        $seance = $this->seanceActive();

        $this->actingAs($this->delegue(), 'sanctum')
            ->postJson("/api/seances/{$seance->id}/confirmer-enseignant")
            ->assertOk();

        $this->actingAs($seance->enseignant, 'sanctum')
            ->postJson("/api/seances/{$seance->id}/mark-prof", ['etat' => 'absent'])
            ->assertOk()
            ->assertJsonPath('etat_prof', 'absent')
            ->assertJsonPath('etat_prof_par_delegue', false);

        $this->assertNull($seance->fresh()->etat_prof_marque_par_id);
    }

    public function test_the_delegue_cannot_override_a_teacher_who_already_answered(): void
    {
        Parametre::setDelegueConfirmeEnseignant(true);
        $seance = $this->seanceActive();
        $seance->update(['etat_prof' => 'absent']);

        $this->actingAs($this->delegue(), 'sanctum')
            ->postJson("/api/seances/{$seance->id}/confirmer-enseignant")
            ->assertUnprocessable();

        $this->assertSame('absent', $seance->fresh()->etat_prof->value);
    }

    public function test_the_delegue_cannot_confirm_a_teacher_they_marked_absent(): void
    {
        Parametre::setDelegueConfirmeEnseignant(true);
        $seance = $this->seanceActive();
        $seance->update(['etat_delegue' => 'absent']);

        $this->actingAs($this->delegue(), 'sanctum')
            ->postJson("/api/seances/{$seance->id}/confirmer-enseignant")
            ->assertUnprocessable();
    }

    public function test_only_the_delegue_of_the_salle_within_the_active_window(): void
    {
        Parametre::setDelegueConfirmeEnseignant(true);
        $seance = $this->seanceActive();

        $autreSalle = Salle::factory()->create();
        $this->actingAs(User::factory()->delegue($autreSalle)->create(), 'sanctum')
            ->postJson("/api/seances/{$seance->id}/confirmer-enseignant")
            ->assertForbidden();

        $this->actingAs(User::factory()->etudiant($this->salle)->create(), 'sanctum')
            ->postJson("/api/seances/{$seance->id}/confirmer-enseignant")
            ->assertForbidden();

        Carbon::setTestNow('2026-02-04 12:00:00');
        $this->actingAs($this->delegue(), 'sanctum')
            ->postJson("/api/seances/{$seance->id}/confirmer-enseignant")
            ->assertUnprocessable();
    }

    public function test_admin_toggles_the_rule(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/parametres/pointage')
            ->assertOk()
            ->assertJsonPath('delegue_confirme_enseignant', false);

        $this->actingAs($admin, 'sanctum')
            ->putJson('/api/parametres/pointage', ['delegue_confirme_enseignant' => true])
            ->assertOk()
            ->assertJsonPath('delegue_confirme_enseignant', true);

        $this->assertTrue(Parametre::delegueConfirmeEnseignant());

        $this->actingAs(User::factory()->enseignant()->create(), 'sanctum')
            ->putJson('/api/parametres/pointage', ['delegue_confirme_enseignant' => false])
            ->assertForbidden();
    }

    /** Séance active mercredi 04/02/2026 08:30-10:00, « maintenant » figé à 09:00. */
    private function seanceActive(): Seance
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
        return User::factory()->delegue($this->salle)->create();
    }
}
