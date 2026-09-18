<?php

namespace Tests\Feature;

use App\Enums\PresenceState;
use App\Models\Niveau;
use App\Models\Parametre;
use App\Models\Salle;
use App\Models\Seance;
use App\Models\Semaine;
use App\Models\TarifHeure;
use App\Models\User;
use App\Services\ClotureSeances;
use App\Services\PayrollCalculator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Une séance marquée présente sans fin réelle n'entre jamais dans la paie.
 * Une fois la fenêtre de pointage fermée, elle est clôturée à l'heure
 * prévue et les heures créditées — sans dépendre d'un oubli du délégué.
 */
class ClotureSeancesTest extends TestCase
{
    use RefreshDatabase;

    private Salle $salle;

    protected function setUp(): void
    {
        parent::setUp();

        $this->salle = Salle::factory()->create();
        Semaine::factory()->create(['numero' => 1, 'date_debut' => '2026-02-02', 'date_fin' => '2026-02-08']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_a_present_seance_without_real_end_is_closed_at_scheduled_end_once_the_window_is_over(): void
    {
        $seance = $this->seance(['etat_delegue' => 'present', 'debut_reel' => '08:35:00']);
        $quotaAvant = $seance->enseignant->quota;

        // Fenêtre encore ouverte (fin prévue 10:00 + 15 min) : on laisse le délégué faire.
        $this->assertSame(0, app(ClotureSeances::class)->tick(Carbon::parse('2026-02-04 10:10:00')));
        $this->assertNull($seance->fresh()->fin_reelle);

        Carbon::setTestNow('2026-02-04 10:16:00');
        $this->assertSame(1, app(ClotureSeances::class)->tick());

        $seance->refresh();
        $this->assertSame('10:00:00', $seance->fin_reelle);
        $this->assertSame('08:35:00', $seance->debut_reel, "L'heure d'arrivée relevée par le délégué est conservée.");
        $this->assertNotNull($seance->quota_credited_at);
        $this->assertSame($quotaAvant + 1, $seance->enseignant->fresh()->quota);

        // Rejouable sans double crédit.
        $this->assertSame(0, app(ClotureSeances::class)->tick());
        $this->assertSame($quotaAvant + 1, $seance->enseignant->fresh()->quota);
    }

    public function test_a_present_seance_without_any_real_time_is_closed_on_schedule(): void
    {
        $seance = $this->seance(['etat_delegue' => 'present']);

        Carbon::setTestNow('2026-02-05 08:00:00');
        app(ClotureSeances::class)->tick();

        $seance->refresh();
        $this->assertSame('08:30:00', $seance->debut_reel);
        $this->assertSame('10:00:00', $seance->fin_reelle);
    }

    public function test_absent_or_unmarked_seances_are_left_alone(): void
    {
        $absente = $this->seance(['etat_delegue' => 'absent']);
        $sansReponse = $this->seance(['etat_delegue' => null, 'heure_debut' => '10:30', 'heure_fin' => '12:00']);

        Carbon::setTestNow('2026-02-05 08:00:00');
        $this->assertSame(0, app(ClotureSeances::class)->tick());

        $this->assertNull($absente->fresh()->fin_reelle);
        $this->assertNull($sansReponse->fresh()->fin_reelle);
    }

    public function test_a_closed_seance_enters_the_payroll(): void
    {
        $niveau = Niveau::factory()->create(['nom' => 'L3']);
        $this->salle->filiere->update(['niveau_id' => $niveau->id]);
        TarifHeure::create(['niveau_id' => $niveau->id, 'tarif_heure' => 3000]);

        $seance = $this->seance(['etat_delegue' => 'present', 'etat_prof' => 'present', 'debut_reel' => '08:30:00', 'heure_fin' => '09:30']);

        Carbon::setTestNow('2026-02-05 08:00:00');
        $this->assertSame(0.0, app(PayrollCalculator::class)->forTeacher($seance->enseignant)->totalSalaire, 'Sans fin réelle, rien n\'est payé.');

        app(ClotureSeances::class)->tick();

        $paie = app(PayrollCalculator::class)->forTeacher($seance->enseignant->fresh());
        $this->assertSame(60, $paie->totalMinutes);
        $this->assertSame(3000.0, $paie->totalSalaire);
    }

    /** Une séance confirmée par le délégué à la place de l'enseignant suit le même chemin. */
    public function test_a_seance_confirmed_for_the_teacher_by_the_delegue_is_closed_too(): void
    {
        Parametre::setDelegueConfirmeEnseignant(true);
        $seance = $this->seance([]);
        Carbon::setTestNow('2026-02-04 09:00:00');

        $this->actingAs(User::factory()->delegue($this->salle)->create(), 'sanctum')
            ->postJson("/api/seances/{$seance->id}/confirmer-enseignant")
            ->assertOk();

        Carbon::setTestNow('2026-02-04 10:20:00');
        $this->artisan('presence:cloturer')->assertSuccessful();

        $seance->refresh();
        $this->assertSame('10:00:00', $seance->fin_reelle);
        $this->assertSame(PresenceState::Present, $seance->etat_final);
        $this->assertNotNull($seance->quota_credited_at);
    }

    /** Mercredi 04/02/2026, 08:30–10:00 par défaut. */
    private function seance(array $attributs): Seance
    {
        return Seance::factory()->create(array_merge([
            'salle_id' => $this->salle->id,
            'semaine_id' => Semaine::first()->id,
            'date_seance' => '2026-02-04',
            'jour' => 'MERCREDI',
            'heure_debut' => '08:30',
            'heure_fin' => '10:00',
        ], $attributs));
    }
}
