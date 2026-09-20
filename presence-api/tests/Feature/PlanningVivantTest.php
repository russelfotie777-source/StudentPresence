<?php

namespace Tests\Feature;

use App\Models\ConversationIA;
use App\Models\CourseTemplate;
use App\Models\Matiere;
use App\Models\Salle;
use App\Models\Seance;
use App\Models\Semaine;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Un emploi du temps qu'on n'a pas à refaire : modifier un cours change
 * ses séances à venir, et allonger le calendrier prolonge les cours qui
 * allaient jusqu'au bout.
 */
class PlanningVivantTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Salle $salle;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-09-16 10:00:00', 'Africa/Douala')); // mercredi de S1
        $this->admin = User::factory()->admin()->create();
        $this->salle = Salle::factory()->create();
        foreach ([1 => '2026-09-14', 2 => '2026-09-21', 3 => '2026-09-28'] as $n => $debut) {
            Semaine::factory()->create(['numero' => $n, 'date_debut' => $debut, 'date_fin' => Carbon::parse($debut)->addDays(6)->toDateString()]);
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // --- modifier un cours ---------------------------------------------------

    public function test_editing_a_course_moves_its_future_sessions_and_leaves_held_ones(): void
    {
        $cours = $this->cours(); // jeudis 14:00–16:00, S1 à S3 : 17/09, 24/09, 01/10
        $tenue = $cours->seances()->whereDate('date_seance', '2026-09-17')->first();
        $tenue->update(['etat_delegue' => 'present']);
        $autreProf = User::factory()->enseignant()->create();

        $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/course-templates/{$cours->id}", ['jour' => 'VENDREDI', 'heure_debut' => '08:00', 'heure_fin' => '10:00', 'enseignant_id' => $autreProf->id])
            ->assertOk()
            ->assertJsonPath('modifiees', 2)
            ->assertJsonPath('ignorees', [])
            ->assertJsonPath('template.jour', 'VENDREDI');

        $this->assertSame('2026-09-17', $tenue->fresh()->date_seance->toDateString(), 'La séance tenue ne bouge pas.');
        $this->assertSame('14:00:00', $tenue->fresh()->heure_debut);
        $dates = $cours->seances()->whereDate('date_seance', '>', '2026-09-17')->orderBy('date_seance')->get();
        $this->assertSame(['2026-09-25', '2026-10-02'], $dates->map(fn (Seance $s) => $s->date_seance->toDateString())->all(), 'Replacées au vendredi de leur semaine.');
        $this->assertSame(['08:00:00', '08:00:00'], $dates->pluck('heure_debut')->all());
        $this->assertSame([$autreProf->id, $autreProf->id], $dates->pluck('enseignant_id')->all());
    }

    public function test_a_session_that_would_clash_stays_and_is_reported(): void
    {
        $cours = $this->cours();
        // Un autre cours occupe déjà la salle le vendredi 25/09 de 08:00 à 10:00.
        Seance::factory()->create([
            'salle_id' => $this->salle->id, 'semaine_id' => Semaine::where('numero', 2)->value('id'),
            'date_seance' => '2026-09-25', 'jour' => 'VENDREDI', 'heure_debut' => '08:00', 'heure_fin' => '10:00',
        ]);

        $reponse = $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/course-templates/{$cours->id}", ['jour' => 'VENDREDI', 'heure_debut' => '08:00', 'heure_fin' => '10:00'])
            ->assertOk()
            ->assertJsonPath('modifiees', 2)
            ->assertJsonCount(1, 'ignorees');

        $this->assertSame('2026-09-24', $reponse->json('ignorees.0.date'), 'La séance en conflit garde sa date.');
        $this->assertDatabaseHas('seances', ['course_template_id' => $cours->id, 'date_seance' => '2026-09-24', 'heure_debut' => '14:00:00']);
    }

    public function test_a_partial_edit_is_checked_against_the_whole_course(): void
    {
        $cours = $this->cours();

        $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/course-templates/{$cours->id}", ['heure_fin' => '13:00'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['heure_fin']);

        $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/course-templates/{$cours->id}", ['jour' => 'DIMANCHE', 'date_debut' => '2026-09-14', 'date_fin' => '2026-09-19'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['jour']);
    }

    // --- prolonger sur les nouvelles semaines ----------------------------------

    public function test_adding_weeks_carries_the_running_courses_over_but_not_the_ones_that_ended(): void
    {
        $jusquAuBout = $this->cours();                                   // S1 → S3
        $arrete = $this->cours(['jour' => 'LUNDI', 'date_fin' => '2026-09-20']); // S1 seulement

        $reponse = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/semaines/generate-semester', ['date_debut' => '2026-10-05', 'nombre_semaines' => 2, 'prolonger_cours' => true])
            ->assertCreated()
            ->assertJsonCount(2, 'semaines')
            ->assertJsonPath('prolongation.cours', 1)
            ->assertJsonPath('prolongation.seances', 2)
            ->assertJsonPath('prolongation.ignorees', []);

        $this->assertSame('2026-10-18', $jusquAuBout->fresh()->date_fin->toDateString());
        $this->assertSame(5, $jusquAuBout->seances()->count(), 'Une séance de plus par nouvelle semaine.');
        $this->assertSame('2026-09-20', $arrete->fresh()->date_fin->toDateString(), 'Un cours arrêté plus tôt ne repart pas.');
        $this->assertSame(1, $arrete->seances()->count());
        $this->assertSame(5, count($reponse->json('semaines')) + 3);
    }

    public function test_the_extension_can_also_be_asked_afterwards_and_replays_safely(): void
    {
        $cours = $this->cours();
        Semaine::factory()->create(['numero' => 4, 'date_debut' => '2026-10-05', 'date_fin' => '2026-10-11']);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/course-templates/prolonger')
            ->assertOk()
            ->assertJsonPath('cours', 1)
            ->assertJsonPath('seances', 1);
        $this->assertSame(4, $cours->seances()->count());

        // Rejouer ne crée rien de plus, et ne signale pas les semaines déjà faites.
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/course-templates/prolonger')
            ->assertOk()
            ->assertJsonPath('cours', 0)
            ->assertJsonPath('seances', 0);
    }

    public function test_extension_reports_the_weeks_a_course_could_not_take(): void
    {
        $cours = $this->cours();
        $s4 = Semaine::factory()->create(['numero' => 4, 'date_debut' => '2026-10-05', 'date_fin' => '2026-10-11']);
        Seance::factory()->create([
            'salle_id' => $this->salle->id, 'semaine_id' => $s4->id,
            'date_seance' => '2026-10-08', 'jour' => 'JEUDI', 'heure_debut' => '14:00', 'heure_fin' => '16:00',
        ]);

        $reponse = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/course-templates/prolonger')
            ->assertOk()
            ->assertJsonPath('cours', 1)
            ->assertJsonPath('seances', 0)
            ->assertJsonCount(1, 'ignorees');

        $this->assertSame($cours->id, $reponse->json('ignorees.0.cours_id'));
        $this->assertSame('2026-10-08', $reponse->json('ignorees.0.date'));
    }

    // --- par l'assistant ---------------------------------------------------------

    public function test_the_assistant_can_propose_the_same_course_change(): void
    {
        $cours = $this->cours();
        $conversation = ConversationIA::create(['admin_id' => $this->admin->id, 'actions' => [[
            'id' => 'a1', 'type' => 'modifier_cours', 'resume' => 'Passer le cours au vendredi',
            'parametres' => ['cours_id' => $cours->id, 'jour' => 'VENDREDI', 'heure_debut' => null, 'heure_fin' => null, 'enseignant_id' => null, 'salle_id' => null],
            'statut' => 'en_attente', 'resultat' => null, 'proposee_le' => now()->toIso8601String(),
        ]]]);

        $reponse = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/assistant/conversations/{$conversation->id}/appliquer", ['ids' => ['a1']])
            ->assertOk()
            ->assertJsonPath('appliquees', 1);

        $this->assertStringContainsString('3 séances à venir mises à jour', $reponse->json('actions.0.resultat.message'));
        $this->assertSame('VENDREDI', $cours->fresh()->jour->value);
        $this->assertSame(0, $cours->seances()->where('jour', 'JEUDI')->count());
    }

    /** Un cours du jeudi 14:00–16:00 sur S1 → S3, avec ses trois séances. */
    private function cours(array $extra = []): CourseTemplate
    {
        $payload = [
            'matiere_id' => Matiere::factory()->create()->id,
            'enseignant_id' => User::factory()->enseignant()->create()->id,
            'salle_id' => $this->salle->id,
            'jour' => 'JEUDI', 'heure_debut' => '14:00', 'heure_fin' => '16:00',
            'date_debut' => '2026-09-14', 'date_fin' => '2026-10-04',
            'generer' => true,
            ...$extra,
        ];
        $id = $this->actingAs($this->admin, 'sanctum')->postJson('/api/course-templates', $payload)->assertCreated()->json('template.id');

        return CourseTemplate::findOrFail($id);
    }
}
