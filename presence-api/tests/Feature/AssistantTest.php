<?php

namespace Tests\Feature;

use App\Models\ConversationIA;
use App\Models\Matiere;
use App\Models\Salle;
use App\Models\Seance;
use App\Models\Semaine;
use App\Models\User;
use App\Services\Assistant\Modele;
use App\Services\Assistant\Outils;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Support\ModeleFictif;
use Tests\TestCase;

/**
 * L'assistant IA du back-office, joué contre un modèle scénarisé : la
 * boucle d'outils, l'enregistrement des propositions et leur application
 * explicite par l'admin.
 */
class AssistantTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Salle $salle;

    private User $prof;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-09-16 10:00:00', 'Africa/Douala'));
        $this->admin = User::factory()->admin()->create();
        $this->salle = Salle::factory()->create(['nom' => 'A23-FI']);
        $this->prof = User::factory()->enseignant()->create(['name' => 'Étienne Mballa']);
        Semaine::factory()->create(['numero' => 1, 'date_debut' => '2026-09-14', 'date_fin' => '2026-09-20']);
        Semaine::factory()->create(['numero' => 2, 'date_debut' => '2026-09-21', 'date_fin' => '2026-09-27']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function scenario(ModeleFictif $modele): ModeleFictif
    {
        $this->app->instance(Modele::class, $modele);

        return $modele;
    }

    private function conversation(): ConversationIA
    {
        return ConversationIA::create(['admin_id' => $this->admin->id]);
    }

    // --- disponibilité et accès ---------------------------------------------

    public function test_assistant_reports_when_no_api_key_is_configured(): void
    {
        $this->scenario(new ModeleFictif([], disponible: false));
        $conversation = $this->conversation();

        $this->actingAs($this->admin, 'sanctum')->getJson('/api/assistant')->assertOk()->assertJsonPath('disponible', false);
        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/assistant/conversations/{$conversation->id}/messages", ['texte' => 'Bonjour'])
            ->assertStatus(503);
    }

    public function test_assistant_is_admin_only_and_conversations_are_private(): void
    {
        $this->scenario(new ModeleFictif([ModeleFictif::texte('Bonjour !')]));
        $autreAdmin = User::factory()->admin()->create();
        $conversation = ConversationIA::create(['admin_id' => $autreAdmin->id]);

        $this->actingAs(User::factory()->etudiant($this->salle)->create(), 'sanctum')
            ->postJson('/api/assistant/conversations')->assertForbidden();

        $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/assistant/conversations/{$conversation->id}")->assertNotFound();
        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/assistant/conversations/{$conversation->id}/messages", ['texte' => 'x'])->assertNotFound();
    }

    // --- boucle d'outils ----------------------------------------------------

    public function test_a_timetable_request_becomes_pending_actions_after_consulting_the_referential(): void
    {
        $modele = $this->scenario(new ModeleFictif([
            ModeleFictif::outils([['name' => 'consulter_referentiel', 'input' => ['partie' => 'tout']]]),
            ModeleFictif::outils([
                ['name' => 'proposer_creer_cours', 'input' => [
                    'resume' => 'Maths Discrètes avec Étienne Mballa, lundi 08:00–10:00 en A23-FI',
                    'salle_id' => $this->salle->id, 'matiere_id' => null, 'matiere_nom' => 'Maths Discrètes', 'matiere_code' => 'INF321',
                    'enseignant_id' => $this->prof->id, 'enseignant_nom' => null,
                    'jour' => 'LUNDI', 'heure_debut' => '08:00', 'heure_fin' => '10:00', 'date_debut' => null, 'date_fin' => null,
                ]],
                ['name' => 'proposer_creer_cours', 'input' => [
                    'resume' => 'Réseaux avec un inconnu, mardi 10:00–12:00',
                    'salle_id' => $this->salle->id, 'matiere_id' => null, 'matiere_nom' => 'Réseaux', 'matiere_code' => null,
                    'enseignant_id' => null, 'enseignant_nom' => 'Pr. Inconnu',
                    'jour' => 'MARDI', 'heure_debut' => '10:00', 'heure_fin' => '12:00', 'date_debut' => null, 'date_fin' => null,
                ]],
            ], "J'ai lu l'emploi du temps."),
            ModeleFictif::texte("Deux cours proposés. L'enseignant de Réseaux n'est pas dans le référentiel."),
        ]));
        $conversation = $this->conversation();

        $reponse = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/assistant/conversations/{$conversation->id}/messages", ['texte' => 'Crée cet emploi du temps pour A23-FI']);

        $reponse->assertOk();
        $this->assertStringContainsString('Deux cours proposés', $reponse->json('reponse'));
        $this->assertCount(2, $reponse->json('actions'));
        $this->assertSame(['creer_cours', 'creer_cours'], $reponse->json('actions.*.type'));
        $this->assertSame(['en_attente', 'en_attente'], $reponse->json('actions.*.statut'));
        $this->assertSame('Maths Discrètes avec Étienne Mballa, lundi 08:00–10:00 en A23-FI', $reponse->json('actions.0.resume'));
        $this->assertArrayNotHasKey('resume', $reponse->json('actions.0.parametres'));
        $this->assertCount(2, $reponse->json('nouvelles'));

        // Rien n'a été écrit : les propositions attendent l'admin.
        $this->assertDatabaseCount('course_templates', 0);
        $this->assertDatabaseCount('seances', 0);

        // Le modèle a reçu le référentiel réel en résultat d'outil, puis les accusés de proposition.
        $this->assertCount(3, $modele->appels);
        $resultatReferentiel = $modele->appels[1]['messages'][2]['content'][0];
        $this->assertSame('tool_result', $resultatReferentiel['type']);
        $this->assertStringContainsString('"A23-FI"', $resultatReferentiel['content']);
        $this->assertStringContainsString('"Étienne Mballa"', $resultatReferentiel['content']);
        $accuses = $modele->appels[2]['messages'][4]['content'];
        $this->assertCount(2, $accuses, 'Les deux résultats d\'outils tiennent dans un seul message.');
        $this->assertStringContainsString('en attente de confirmation', $accuses[0]['content']);

        // Historique persisté, titre déduit du premier message.
        $conversation->refresh();
        $this->assertSame('Crée cet emploi du temps pour A23-FI', $conversation->titre);
        $this->assertCount(6, $conversation->messages);
        $this->assertCount(2, $conversation->actions);
    }

    public function test_attachments_reach_the_model_but_are_not_persisted(): void
    {
        $modele = $this->scenario(new ModeleFictif([ModeleFictif::texte('Document lu.')]));
        $conversation = $this->conversation();
        $pdf = base64_encode('%PDF-1.4 faux document');

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/assistant/conversations/{$conversation->id}/messages", [
                'texte' => '',
                'fichiers' => [['nom' => 'edt.pdf', 'type' => 'application/pdf', 'base64' => $pdf]],
            ])
            ->assertOk();

        $envoye = $modele->appels[0]['messages'][0]['content'];
        $this->assertSame('document', $envoye[0]['type']);
        $this->assertSame(['type' => 'base64', 'mediaType' => 'application/pdf', 'data' => $pdf], $envoye[0]['source']);
        $this->assertSame('edt.pdf', $envoye[0]['title']);
        $this->assertSame('text', $envoye[1]['type']);

        $persiste = $conversation->fresh()->messages[0]['content'];
        $this->assertSame('text', $persiste[0]['type']);
        $this->assertStringContainsString('edt.pdf', $persiste[0]['text']);
        $this->assertStringNotContainsString($pdf, json_encode($conversation->fresh()->messages));
    }

    public function test_attachments_are_validated(): void
    {
        $this->scenario(new ModeleFictif([]));
        $conversation = $this->conversation();
        $this->actingAs($this->admin, 'sanctum');

        $this->postJson("/api/assistant/conversations/{$conversation->id}/messages", [
            'fichiers' => [['nom' => 'x.exe', 'type' => 'application/octet-stream', 'base64' => base64_encode('x')]],
        ])->assertUnprocessable();

        $this->postJson("/api/assistant/conversations/{$conversation->id}/messages", [
            'fichiers' => [['nom' => 'x.pdf', 'type' => 'application/pdf', 'base64' => '%%%pas du base64%%%']],
        ])->assertUnprocessable();

        $this->postJson("/api/assistant/conversations/{$conversation->id}/messages", [])->assertUnprocessable();
    }

    public function test_the_loop_stops_after_too_many_tool_turns(): void
    {
        $boucle = array_fill(0, 20, ModeleFictif::outils([['name' => 'consulter_referentiel', 'input' => ['partie' => 'salles']]]));
        $modele = $this->scenario(new ModeleFictif($boucle));
        $conversation = $this->conversation();

        $reponse = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/assistant/conversations/{$conversation->id}/messages", ['texte' => 'Tourne en rond']);

        $reponse->assertOk();
        $this->assertCount(12, $modele->appels);
        $this->assertStringContainsString("limite d'étapes", $reponse->json('reponse'));
    }

    // --- application des actions ---------------------------------------------

    public function test_applying_actions_creates_the_courses_and_their_sessions(): void
    {
        $this->scenario(new ModeleFictif([]));
        $conversation = $this->conversation();
        $conversation->fill(['actions' => [
            $this->action('creer_cours', [
                'salle_id' => $this->salle->id, 'matiere_id' => null, 'matiere_nom' => 'Maths Discrètes', 'matiere_code' => 'INF321',
                'enseignant_id' => $this->prof->id, 'enseignant_nom' => null,
                'jour' => 'LUNDI', 'heure_debut' => '08:00', 'heure_fin' => '10:00', 'date_debut' => null, 'date_fin' => null,
            ], 'a1'),
            $this->action('creer_cours', [
                'salle_id' => $this->salle->id, 'matiere_id' => null, 'matiere_nom' => 'Réseaux', 'matiere_code' => null,
                'enseignant_id' => null, 'enseignant_nom' => 'Pr. Inconnu',
                'jour' => 'MARDI', 'heure_debut' => '10:00', 'heure_fin' => '12:00', 'date_debut' => null, 'date_fin' => null,
            ], 'a2'),
            $this->action('creer_cours', ['salle_id' => $this->salle->id], 'a3'), // non cochée
        ]])->save();

        $reponse = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/assistant/conversations/{$conversation->id}/appliquer", ['ids' => ['a1', 'a2']]);

        $reponse->assertOk()->assertJsonPath('appliquees', 1)->assertJsonPath('echouees', 1);
        $actions = collect($reponse->json('actions'))->keyBy('id');
        $this->assertSame('appliquee', $actions['a1']['statut']);
        $this->assertSame(2, $actions['a1']['resultat']['details']['seances_creees'], 'Une séance par semaine du semestre.');
        $this->assertSame('echouee', $actions['a2']['statut']);
        $this->assertStringContainsString('Pr. Inconnu', $actions['a2']['resultat']['message']);
        $this->assertSame('en_attente', $actions['a3']['statut']);

        $this->assertDatabaseHas('matieres', ['code' => 'INF321', 'nom' => 'Maths Discrètes']);
        $this->assertDatabaseCount('course_templates', 1);
        $this->assertDatabaseCount('seances', 2);
        $this->assertSame('2026-09-14', Seance::orderBy('date_seance')->first()->date_seance->toDateString());

        // Le modèle est informé de ce qui a été fait.
        $dernier = collect($conversation->fresh()->messages)->reverse()->values();
        $this->assertStringContainsString('[Système] Actions traitées', $dernier[1]['content'][0]['text']);
        $this->assertStringContainsString('ÉCHEC', $dernier[1]['content'][0]['text']);
    }

    public function test_an_existing_subject_is_reused_rather_than_duplicated(): void
    {
        $existante = Matiere::factory()->create(['nom' => 'Algorithmique', 'code' => 'INF101']);
        $this->scenario(new ModeleFictif([]));
        $conversation = $this->conversation();
        $conversation->fill(['actions' => [$this->action('creer_cours', [
            'salle_id' => $this->salle->id, 'matiere_id' => null, 'matiere_nom' => 'algorithmique', 'matiere_code' => null,
            'enseignant_id' => null, 'enseignant_nom' => 'étienne mballa',
            'jour' => 'JEUDI', 'heure_debut' => '14:00', 'heure_fin' => '16:00', 'date_debut' => '2026-09-21', 'date_fin' => '2026-09-27',
        ], 'a1')]])->save();

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/assistant/conversations/{$conversation->id}/appliquer", ['ids' => ['a1']])
            ->assertJsonPath('appliquees', 1);

        $this->assertDatabaseCount('matieres', 1);
        $this->assertDatabaseHas('course_templates', ['matiere_id' => $existante->id, 'enseignant_id' => $this->prof->id]);
        $this->assertDatabaseCount('seances', 1);
    }

    public function test_applying_a_student_enrolment_creates_the_account_with_an_initial_password(): void
    {
        $this->scenario(new ModeleFictif([]));
        $conversation = $this->conversation();
        $conversation->fill(['actions' => [
            $this->action('inscrire_etudiant', ['nom' => 'Awa Ndiaye', 'matricule' => '24I09999', 'salle_id' => $this->salle->id, 'formation' => 'FI', 'email' => null], 'a1'),
            $this->action('inscrire_etudiant', ['nom' => 'Doublon', 'matricule' => '24I09999', 'salle_id' => $this->salle->id, 'formation' => 'FI', 'email' => null], 'a2'),
            $this->action('inscrire_etudiant', ['nom' => 'Mauvaise formation', 'matricule' => '24I09998', 'salle_id' => $this->salle->id, 'formation' => 'FA', 'email' => null], 'a3'),
        ]])->save();

        $reponse = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/assistant/conversations/{$conversation->id}/appliquer", ['ids' => ['a1', 'a2', 'a3']]);

        $reponse->assertOk()->assertJsonPath('appliquees', 1)->assertJsonPath('echouees', 2);
        $actions = collect($reponse->json('actions'))->keyBy('id');
        $this->assertMatchesRegularExpression('/^\d{8}$/', $actions['a1']['resultat']['details']['mot_de_passe_initial']);
        $this->assertStringContainsString('matricule', $actions['a2']['resultat']['message']);
        $this->assertStringContainsString("n'accueille pas", $actions['a3']['resultat']['message']);

        $etudiant = User::where('phone', '24I09999')->first();
        $this->assertNotNull($etudiant);
        $this->assertSame($this->salle->id, $etudiant->salle_id);
        $this->assertSame($this->salle->filiere_id, $etudiant->filiere_id);
        $this->assertTrue(Hash::check($actions['a1']['resultat']['details']['mot_de_passe_initial'], $etudiant->password));
    }

    public function test_session_changes_go_through_the_planning_rules(): void
    {
        $semaine = Semaine::first();
        $seance = Seance::factory()->create([
            'salle_id' => $this->salle->id, 'semaine_id' => $semaine->id, 'enseignant_id' => $this->prof->id,
            'date_seance' => '2026-09-17', 'jour' => 'JEUDI', 'heure_debut' => '08:00', 'heure_fin' => '10:00',
        ]);
        $tenue = Seance::factory()->create([
            'salle_id' => $this->salle->id, 'semaine_id' => $semaine->id,
            'date_seance' => '2026-09-15', 'jour' => 'MARDI', 'heure_debut' => '08:00', 'heure_fin' => '10:00', 'etat_delegue' => 'present',
        ]);
        $this->scenario(new ModeleFictif([]));
        $conversation = $this->conversation();
        $conversation->fill(['actions' => [
            $this->action('modifier_seance', ['seance_id' => $seance->id, 'date_seance' => null, 'heure_debut' => '09:00', 'heure_fin' => '11:00', 'enseignant_id' => null, 'salle_id' => null], 'a1'),
            $this->action('supprimer_seance', ['seance_id' => $tenue->id, 'motif' => null], 'a2'),
        ]])->save();

        $reponse = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/assistant/conversations/{$conversation->id}/appliquer", ['ids' => ['a1', 'a2']]);

        $reponse->assertJsonPath('appliquees', 1)->assertJsonPath('echouees', 1);
        $this->assertSame('09:00:00', $seance->fresh()->heure_debut);
        $this->assertModelExists($tenue);
        $this->assertStringContainsString('déjà été tenue', collect($reponse->json('actions'))->firstWhere('id', 'a2')['resultat']['message']);
    }

    public function test_pending_actions_can_be_dismissed(): void
    {
        $this->scenario(new ModeleFictif([]));
        $conversation = $this->conversation();
        $conversation->fill(['actions' => [$this->action('supprimer_cours', ['cours_id' => 1, 'motif' => null], 'a1')]])->save();

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/assistant/conversations/{$conversation->id}/ignorer", ['ids' => ['a1']])
            ->assertOk()
            ->assertJsonPath('actions.0.statut', 'ignoree');

        $this->assertDatabaseCount('course_templates', 0);
    }

    // --- définitions d'outils ------------------------------------------------

    public function test_every_proposal_tool_is_strict_and_matches_an_executable_action(): void
    {
        $definitions = app(Outils::class)->definitions();
        $propositions = collect($definitions)->filter(fn ($d) => Outils::estProposition($d['name']));

        $this->assertCount(count(Outils::ACTIONS), $propositions);
        foreach ($propositions as $outil) {
            $this->assertContains(Outils::typeAction($outil['name']), Outils::ACTIONS);
            $this->assertTrue($outil['strict']);
            $this->assertFalse($outil['inputSchema']['additionalProperties']);
            $this->assertSame(array_keys($outil['inputSchema']['properties']), $outil['inputSchema']['required'], 'strict : toutes les propriétés sont requises');
            $this->assertArrayHasKey('resume', $outil['inputSchema']['properties']);
        }
    }

    /**
     * @param  array<string, mixed>  $parametres
     * @return array<string, mixed>
     */
    private function action(string $type, array $parametres, string $id): array
    {
        return ['id' => $id, 'type' => $type, 'resume' => $type, 'parametres' => $parametres, 'statut' => 'en_attente', 'resultat' => null, 'proposee_le' => now()->toIso8601String()];
    }
}
