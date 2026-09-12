<?php

namespace Tests\Feature;

use App\Models\CourseTemplate;
use App\Models\Matiere;
use App\Models\PresenceEtudiant;
use App\Models\Salle;
use App\Models\Seance;
use App\Models\Semaine;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmploiDuTempsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Salle $salle;

    private Semaine $s1;

    private Semaine $s2;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-09-16 10:00:00', 'Africa/Douala')); // mercredi, semaine 1

        $this->admin = User::factory()->admin()->create();
        $this->salle = Salle::factory()->create();
        $this->s1 = Semaine::factory()->create(['numero' => 1, 'date_debut' => '2026-09-14', 'date_fin' => '2026-09-20']);
        $this->s2 = Semaine::factory()->create(['numero' => 2, 'date_debut' => '2026-09-21', 'date_fin' => '2026-09-27']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function seance(array $attributs = []): Seance
    {
        return Seance::factory()->create([
            'salle_id' => $this->salle->id,
            'semaine_id' => $this->s1->id,
            'date_seance' => '2026-09-17',
            'jour' => 'JEUDI',
            'heure_debut' => '08:00',
            'heure_fin' => '10:00',
            ...$attributs,
        ]);
    }

    // --- grille -------------------------------------------------------------

    public function test_grid_lists_the_room_sessions_of_the_requested_week_only(): void
    {
        $cette = $this->seance();
        $this->seance(['semaine_id' => $this->s2->id, 'date_seance' => '2026-09-24']);
        $this->seance(['salle_id' => Salle::factory()->create()->id]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/emploi-du-temps?salle_id={$this->salle->id}&semaine_id={$this->s1->id}");

        $response->assertOk();
        $this->assertSame([$cette->id], $response->json('data.*.id'));
        $this->assertSame(1, $response->json('semaine.numero'));
        $this->assertSame('2026-09-14', $response->json('semaine.date_debut'), 'Dates sérialisées en Y-m-d, sans fuseau.');
        $this->assertStringEndsWith('+01:00', $response->json('maintenant'));
    }

    public function test_grid_defaults_to_the_current_week(): void
    {
        $cette = $this->seance();
        $this->seance(['semaine_id' => $this->s2->id, 'date_seance' => '2026-09-24']);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/emploi-du-temps?salle_id={$this->salle->id}");

        $this->assertSame([$cette->id], $response->json('data.*.id'));
        $this->assertSame($this->s1->id, $response->json('semaine.id'));
    }

    public function test_grid_can_be_read_per_teacher(): void
    {
        $prof = User::factory()->enseignant()->create();
        $sienne = $this->seance(['enseignant_id' => $prof->id, 'salle_id' => Salle::factory()->create()->id]);
        $this->seance();

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/emploi-du-temps?enseignant_id={$prof->id}&semaine_id={$this->s1->id}");

        $this->assertSame([$sienne->id], $response->json('data.*.id'));
    }

    public function test_grid_is_admin_only(): void
    {
        $etudiant = User::factory()->etudiant($this->salle)->create();

        $this->actingAs($etudiant, 'sanctum')
            ->getJson("/api/emploi-du-temps?salle_id={$this->salle->id}")
            ->assertForbidden();
    }

    // --- programmer un cours ------------------------------------------------

    private function payload(array $extra = []): array
    {
        return [
            'matiere_id' => Matiere::factory()->create()->id,
            'enseignant_id' => User::factory()->enseignant()->create()->id,
            'salle_id' => $this->salle->id,
            'jour' => 'JEUDI',
            'heure_debut' => '14:00',
            'heure_fin' => '16:00',
            'date_debut' => '2026-09-14',
            'date_fin' => '2026-09-27',
            'generer' => true,
            ...$extra,
        ];
    }

    public function test_scheduling_a_course_creates_its_sessions_in_one_step(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/course-templates', $this->payload());

        $response->assertCreated();
        $this->assertCount(2, $response->json('created'));
        $this->assertSame(['2026-09-17', '2026-09-24'], $response->json('created.*.date_seance'));
        $this->assertSame([], $response->json('skipped'));
        $this->assertDatabaseCount('seances', 2);
    }

    public function test_one_off_session_is_a_course_valid_for_a_single_day(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/course-templates', $this->payload([
                'jour' => 'VENDREDI', 'date_debut' => '2026-09-18', 'date_fin' => '2026-09-18',
            ]));

        $response->assertCreated();
        $this->assertSame(['2026-09-18'], $response->json('created.*.date_seance'));
    }

    public function test_weekday_outside_the_range_is_rejected_upfront(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/course-templates', $this->payload([
                'jour' => 'LUNDI', 'date_debut' => '2026-09-15', 'date_fin' => '2026-09-17',
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['jour']);

        $this->assertDatabaseCount('course_templates', 0);
    }

    public function test_nothing_schedulable_rolls_the_course_back(): void
    {
        // Aucune semaine en octobre.
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/course-templates', $this->payload([
                'date_debut' => '2026-10-05', 'date_fin' => '2026-10-11',
            ]))
            ->assertUnprocessable()
            ->assertJsonPath('errors.seances.0', fn (string $m) => str_contains($m, 'Aucune semaine'));

        $this->assertDatabaseCount('course_templates', 0);
        $this->assertDatabaseCount('seances', 0);
    }

    public function test_conflicts_are_reported_and_the_rest_is_scheduled(): void
    {
        // La salle est déjà prise le jeudi 17 de 14h à 16h (semaine 1).
        $this->seance(['heure_debut' => '14:00', 'heure_fin' => '16:00']);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/course-templates', $this->payload());

        $response->assertCreated();
        $this->assertSame(['2026-09-24'], $response->json('created.*.date_seance'));
        $this->assertSame(1, $response->json('skipped.0.numero'));
        $this->assertStringContainsString('Conflit de salle', $response->json('skipped.0.reason'));
    }

    public function test_course_creation_without_generer_keeps_the_old_behaviour(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/course-templates', $this->payload(['generer' => false]));

        $response->assertCreated()->assertJsonPath('salle_id', $this->salle->id);
        $this->assertDatabaseCount('seances', 0);
    }

    // --- retoucher une séance -----------------------------------------------

    public function test_admin_can_move_a_session_within_or_across_weeks(): void
    {
        $seance = $this->seance();

        $response = $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/seances/{$seance->id}", [
                'date_seance' => '2026-09-25', 'heure_debut' => '09:00', 'heure_fin' => '11:00',
            ]);

        $response->assertOk();
        $seance->refresh();
        $this->assertSame('2026-09-25', $seance->date_seance->toDateString());
        $this->assertSame('VENDREDI', $seance->jour->value);
        $this->assertSame($this->s2->id, $seance->semaine_id, 'La semaine suit la nouvelle date.');
        $this->assertSame('09:00:00', $seance->heure_debut);
    }

    public function test_moving_a_session_onto_a_busy_slot_is_refused(): void
    {
        $this->seance(['heure_debut' => '10:00', 'heure_fin' => '12:00']);
        $seance = $this->seance();

        $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/seances/{$seance->id}", ['heure_debut' => '11:00', 'heure_fin' => '13:00'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['creneau']);

        $this->assertSame('08:00:00', $seance->fresh()->heure_debut);
    }

    public function test_a_session_does_not_conflict_with_itself(): void
    {
        $seance = $this->seance();

        $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/seances/{$seance->id}", ['heure_debut' => '08:30', 'heure_fin' => '10:00'])
            ->assertOk();
    }

    public function test_moving_outside_any_week_is_refused(): void
    {
        $seance = $this->seance();

        $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/seances/{$seance->id}", ['date_seance' => '2026-12-01'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['date_seance']);
    }

    public function test_a_held_session_can_neither_be_edited_nor_deleted(): void
    {
        $tenue = $this->seance(['etat_delegue' => 'present']);
        $pointee = $this->seance(['heure_debut' => '10:00', 'heure_fin' => '12:00']);
        PresenceEtudiant::create([
            'seance_id' => $pointee->id,
            'etudiant_id' => User::factory()->etudiant($this->salle)->create()->id,
            'etat' => 'present',
        ]);

        $this->actingAs($this->admin, 'sanctum');
        $this->putJson("/api/seances/{$tenue->id}", ['heure_debut' => '09:00'])->assertUnprocessable();
        $this->deleteJson("/api/seances/{$tenue->id}")->assertUnprocessable();
        $this->deleteJson("/api/seances/{$pointee->id}")->assertUnprocessable();
        $this->assertDatabaseCount('seances', 2);
    }

    public function test_admin_can_cancel_a_single_upcoming_session(): void
    {
        $seance = $this->seance();

        $this->actingAs($this->admin, 'sanctum')->deleteJson("/api/seances/{$seance->id}")->assertNoContent();

        $this->assertModelMissing($seance);
    }

    public function test_deleting_a_course_removes_its_upcoming_sessions_but_keeps_held_ones(): void
    {
        $template = CourseTemplate::factory()->create(['salle_id' => $this->salle->id]);
        $passee = $this->seance(['course_template_id' => $template->id, 'date_seance' => '2026-09-14', 'jour' => 'LUNDI', 'etat_delegue' => 'present']);
        $aVenir = $this->seance(['course_template_id' => $template->id]);
        $aVenir2 = $this->seance(['course_template_id' => $template->id, 'semaine_id' => $this->s2->id, 'date_seance' => '2026-09-24']);

        $response = $this->actingAs($this->admin, 'sanctum')->deleteJson("/api/course-templates/{$template->id}");

        $response->assertOk()->assertJsonPath('seances_supprimees', 2);
        $this->assertModelMissing($aVenir);
        $this->assertModelMissing($aVenir2);
        $this->assertModelExists($passee);
        $this->assertNull($passee->fresh()->course_template_id);
    }

    // --- semaines -------------------------------------------------------------

    public function test_weeks_carry_their_session_count_and_cannot_be_deleted_while_used(): void
    {
        $this->seance();

        $this->actingAs($this->admin, 'sanctum');

        $liste = $this->getJson('/api/semaines')->assertOk();
        $this->assertSame(1, $liste->json('0.seances_count'));
        $this->assertSame(0, $liste->json('1.seances_count'));

        $this->deleteJson("/api/semaines/{$this->s1->id}")->assertUnprocessable();
        $this->deleteJson("/api/semaines/{$this->s2->id}")->assertNoContent();
    }
}
