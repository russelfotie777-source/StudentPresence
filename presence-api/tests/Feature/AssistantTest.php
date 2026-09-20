<?php

namespace Tests\Feature;

use App\Enums\FormationType;
use App\Models\ConversationIA;
use App\Models\Filiere;
use App\Models\Matiere;
use App\Models\Salle;
use App\Models\Seance;
use App\Models\Semaine;
use App\Models\User;
use App\Services\Assistant\Modele;
use App\Services\Assistant\Outils;
use App\Services\Assistant\PiecesJointes;
use App\Services\Assistant\ReponseModele;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
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
        Storage::fake('local');
        $this->admin = User::factory()->admin()->create();
        $this->salle = Salle::factory()->create(['nom' => 'A23-FI', 'formation' => FormationType::FI]);
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

    /**
     * Envoie un message (multipart) ; la file étant synchrone en test, la
     * réponse 202 porte déjà la conversation traitée.
     *
     * @param  list<UploadedFile>  $fichiers
     * @return array<string, mixed>
     */
    private function envoyer(ConversationIA $conversation, string $texte, array $fichiers = []): array
    {
        $reponse = $this->actingAs($this->admin, 'sanctum')
            ->post("/api/assistant/conversations/{$conversation->id}/messages", ['texte' => $texte, 'fichiers' => $fichiers], ['Accept' => 'application/json']);

        $reponse->assertStatus(202);

        return $reponse->json('conversation');
    }

    /** Un vrai PDF de N pages (dompdf), pour que Ghostscript ait quelque chose à compter et découper. */
    private function pdf(int $pages, string $nom = 'liste.pdf'): UploadedFile
    {
        $html = implode('<div style="page-break-after: always"></div>', array_map(fn ($i) => "<p>Page {$i}</p>", range(1, $pages)));
        $chemin = tempnam(sys_get_temp_dir(), 'pdf');
        file_put_contents($chemin, Pdf::loadHTML($html)->output());

        return new UploadedFile($chemin, $nom, 'application/pdf', null, true);
    }

    /**
     * @param  array<string, list<list<string|int>>>  $feuilles  nom → lignes
     */
    private function tableur(array $feuilles, string $nom = 'etudiants.xlsx'): UploadedFile
    {
        $classeur = new Spreadsheet;
        $premiere = true;
        foreach ($feuilles as $titre => $lignes) {
            $feuille = $premiere ? $classeur->getActiveSheet() : $classeur->createSheet();
            $premiere = false;
            $feuille->setTitle($titre);
            $feuille->fromArray($lignes, null, 'A1');
        }
        $chemin = tempnam(sys_get_temp_dir(), 'xlsx');
        (new Xlsx($classeur))->save($chemin);

        return new UploadedFile($chemin, $nom, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    // --- disponibilité et accès ---------------------------------------------

    public function test_assistant_reports_when_no_api_key_is_configured(): void
    {
        $this->scenario(new ModeleFictif([], disponible: false));
        $conversation = $this->conversation();

        $this->actingAs($this->admin, 'sanctum')->getJson('/api/assistant')->assertOk()->assertJsonPath('disponible', false);
        $this->actingAs($this->admin, 'sanctum')
            ->post("/api/assistant/conversations/{$conversation->id}/messages", ['texte' => 'Bonjour'], ['Accept' => 'application/json'])
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
            ->post("/api/assistant/conversations/{$conversation->id}/messages", ['texte' => 'x'], ['Accept' => 'application/json'])->assertNotFound();
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

        $c = $this->envoyer($conversation, 'Crée cet emploi du temps pour A23-FI');

        $this->assertSame('termine', $c['traitement']['statut']);
        $this->assertStringContainsString('Deux cours proposés', end($c['messages'])['texte']);
        $this->assertCount(2, $c['actions']);
        $this->assertSame(['creer_cours', 'creer_cours'], array_column($c['actions'], 'type'));
        $this->assertSame(['en_attente', 'en_attente'], array_column($c['actions'], 'statut'));
        $this->assertSame('Maths Discrètes avec Étienne Mballa, lundi 08:00–10:00 en A23-FI', $c['actions'][0]['resume']);
        $this->assertArrayNotHasKey('resume', $c['actions'][0]['parametres']);
        $this->assertCount(2, $c['traitement']['nouvelles_actions']);

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

    public function test_a_short_pdf_reaches_the_model_whole_but_is_not_persisted(): void
    {
        $modele = $this->scenario(new ModeleFictif([ModeleFictif::texte('Document lu.')]));
        $conversation = $this->conversation();

        $c = $this->envoyer($conversation, '', [$this->pdf(2, 'edt.pdf')]);

        $this->assertSame('termine', $c['traitement']['statut']);
        $this->assertSame([['nom' => 'edt.pdf', 'genre' => 'pdf', 'pages' => 2]], array_map(fn ($f) => ['nom' => $f['nom'], 'genre' => $f['genre'], 'pages' => $f['pages']], $c['fichiers']));

        $envoye = $modele->appels[0]['messages'][0]['content'];
        $this->assertSame('text', $envoye[0]['type']);
        $this->assertStringContainsString('edt.pdf', $envoye[0]['text']);
        $this->assertSame('document', $envoye[1]['type']);
        $this->assertSame('application/pdf', $envoye[1]['source']['mediaType']);
        $this->assertStringStartsWith('JVBERi', $envoye[1]['source']['data'], 'Le PDF part en base64.');
        $this->assertSame('text', $envoye[2]['type']);

        $persiste = json_encode($conversation->fresh()->messages);
        $this->assertStringNotContainsString('JVBERi', $persiste, 'Le fichier n\'est pas conservé dans l\'historique.');
        $this->assertTrue(Storage::disk('local')->exists($conversation->fresh()->fichiers[0]['chemin']), 'Mais il reste sur le disque, pour un import ultérieur.');
    }

    public function test_a_long_pdf_is_described_instead_of_sent_whole(): void
    {
        $modele = $this->scenario(new ModeleFictif([ModeleFictif::texte('Je vais l\'extraire.')]));
        $conversation = $this->conversation();

        $this->envoyer($conversation, 'Inscris ces étudiants', [$this->pdf(PiecesJointes::PAGES_LECTURE_DIRECTE + 1)]);

        $envoye = $modele->appels[0]['messages'][0]['content'];
        $this->assertCount(2, $envoye, 'Une description et le texte : pas de bloc document.');
        $this->assertStringContainsString('extraire_etudiants_pdf', $envoye[0]['text']);
        $this->assertStringContainsString('fichier = 1', $envoye[0]['text']);
    }

    public function test_attachments_are_validated(): void
    {
        $this->scenario(new ModeleFictif([]));
        $conversation = $this->conversation();
        $this->actingAs($this->admin, 'sanctum');
        $json = ['Accept' => 'application/json'];

        $this->post("/api/assistant/conversations/{$conversation->id}/messages", [
            'fichiers' => [UploadedFile::fake()->create('x.exe', 10, 'application/octet-stream')],
        ], $json)->assertUnprocessable();

        config(['services.anthropic.taille_max_fichier_mo' => 1]);
        $this->post("/api/assistant/conversations/{$conversation->id}/messages", [
            'fichiers' => [UploadedFile::fake()->create('gros.pdf', 2048, 'application/pdf')],
        ], $json)->assertUnprocessable();

        $this->post("/api/assistant/conversations/{$conversation->id}/messages", [], $json)->assertUnprocessable();
    }

    public function test_a_second_message_waits_for_the_running_one(): void
    {
        $this->scenario(new ModeleFictif([]));
        $conversation = $this->conversation();
        $conversation->forceFill(['traitement' => ['statut' => 'en_cours', 'etape' => 'Lecture']])->save();

        $this->actingAs($this->admin, 'sanctum')
            ->post("/api/assistant/conversations/{$conversation->id}/messages", ['texte' => 'encore'], ['Accept' => 'application/json'])
            ->assertStatus(409);
    }

    public function test_a_model_failure_is_reported_on_the_conversation(): void
    {
        $this->app->instance(Modele::class, new class implements Modele
        {
            public function repondre(string $systeme, array $messages, array $outils): ReponseModele
            {
                throw new \RuntimeException('panne');
            }

            public function structurer(string $consigne, string $pdfBase64, array $schema): ?array
            {
                return null;
            }

            public function disponible(): bool
            {
                return true;
            }
        });
        $conversation = $this->conversation();

        $c = $this->envoyer($conversation, 'Bonjour');

        $this->assertSame('erreur', $c['traitement']['statut']);
        $this->assertStringContainsString('panne', $c['traitement']['erreur']);
    }

    public function test_the_loop_stops_after_too_many_tool_turns(): void
    {
        $boucle = array_fill(0, 20, ModeleFictif::outils([['name' => 'consulter_referentiel', 'input' => ['partie' => 'salles']]]));
        $modele = $this->scenario(new ModeleFictif($boucle));
        $conversation = $this->conversation();

        $c = $this->envoyer($conversation, 'Tourne en rond');

        $this->assertCount(12, $modele->appels);
        $this->assertStringContainsString("limite d'étapes", end($c['messages'])['texte']);
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

        $reponse->assertOk()->assertJsonPath('appliquees', 2)->assertJsonPath('echouees', 0);
        $actions = collect($reponse->json('actions'))->keyBy('id');
        $this->assertSame('appliquee', $actions['a1']['statut']);
        $this->assertSame(2, $actions['a1']['resultat']['details']['seances_creees'], 'Une séance par semaine du semestre.');
        $this->assertNull($actions['a1']['resultat']['details']['enseignant_cree']);
        $this->assertSame('appliquee', $actions['a2']['statut']);
        $this->assertSame('en_attente', $actions['a3']['statut']);

        // L'enseignant inconnu a reçu un compte : sans titre, identifiant
        // provisoire, mot de passe initial commun — remis à l'admin.
        $this->assertSame(
            ['nom' => 'Inconnu', 'identifiant' => 'ENS0001', 'mot_de_passe_initial' => '12345678'],
            $actions['a2']['resultat']['details']['enseignant_cree'],
        );
        $this->assertStringContainsString('compte enseignant créé pour Inconnu (ENS0001)', $actions['a2']['resultat']['message']);
        $inconnu = User::where('phone', 'ENS0001')->first();
        $this->assertSame('Enseignant', $inconnu->role->value);
        $this->assertSame('approved', $inconnu->validation_status->value);
        $this->assertTrue(Hash::check('12345678', $inconnu->password));
        $this->assertTrue($inconnu->doit_changer_mot_de_passe, 'Le mot de passe initial est à remplacer à la première connexion.');

        $this->assertDatabaseHas('matieres', ['code' => 'INF321', 'nom' => 'Maths Discrètes']);
        $this->assertDatabaseHas('course_templates', ['enseignant_id' => $inconnu->id]);
        $this->assertDatabaseCount('course_templates', 2);
        $this->assertDatabaseCount('seances', 4);
        $this->assertSame('2026-09-14', Seance::orderBy('date_seance')->first()->date_seance->toDateString());

        // Le modèle est informé de ce qui a été fait.
        $dernier = collect($conversation->fresh()->messages)->reverse()->values();
        $this->assertStringContainsString('[Système] Actions traitées', $dernier[1]['content'][0]['text']);
    }

    /**
     * Un enseignant cité sous une autre forme que son nom enregistré —
     * titre, ordre des mots, nom de famille seul — est reconnu, pas
     * dupliqué. Deux homonymes possibles : on demande, on ne devine pas.
     */
    public function test_a_known_teacher_is_recognised_however_the_timetable_names_them(): void
    {
        $this->scenario(new ModeleFictif([]));
        $cours = fn (string $nom, string $id) => $this->action('creer_cours', [
            'salle_id' => $this->salle->id, 'matiere_id' => null, 'matiere_nom' => 'Réseaux', 'matiere_code' => null,
            'enseignant_id' => null, 'enseignant_nom' => $nom, 'enseignant_telephone' => null,
            'jour' => 'LUNDI', 'heure_debut' => '08:00', 'heure_fin' => '10:00', 'date_debut' => null, 'date_fin' => null,
        ], $id);

        $conversation = $this->conversation();
        $conversation->fill(['actions' => [$cours('Pr. MBALLA Etienne', 'a1'), $cours('mballa', 'a2'), $cours('M. Mballa', 'a3')]])->save();

        $reponse = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/assistant/conversations/{$conversation->id}/appliquer", ['ids' => ['a1', 'a2', 'a3']]);

        // Reconnu trois fois : un seul cours passe (les deux autres tombent
        // sur le même créneau), aucun compte n'est créé.
        $reponse->assertJsonPath('appliquees', 1);
        $this->assertSame(1, User::where('role', 'Enseignant')->count());
        $this->assertDatabaseHas('course_templates', ['enseignant_id' => $this->prof->id]);
        foreach (collect($reponse->json('actions'))->whereIn('id', ['a2', 'a3']) as $a) {
            $this->assertStringNotContainsString('introuvable', $a['resultat']['message']);
        }

        // Un second Mballa : « Mballa » seul devient ambigu.
        $autre = User::factory()->enseignant()->create(['name' => 'Rose Mballa']);
        $conversation = $this->conversation();
        $conversation->fill(['actions' => [$cours('Mballa', 'b1')]])->save();
        $reponse = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/assistant/conversations/{$conversation->id}/appliquer", ['ids' => ['b1']]);

        $reponse->assertJsonPath('echouees', 1);
        $message = $reponse->json('actions.0.resultat.message');
        $this->assertStringContainsString('peut désigner', $message);
        $this->assertStringContainsString("Étienne Mballa (id {$this->prof->id})", $message);
        $this->assertStringContainsString("Rose Mballa (id {$autre->id})", $message);
        $this->assertSame(2, User::where('role', 'Enseignant')->count(), 'Aucun compte créé dans le doute.');
    }

    /**
     * Les comptes enseignants se créent aussi seuls (sans cours) : avec le
     * téléphone comme identifiant quand on le connaît, sinon le numéro
     * provisoire suivant ; toujours le mot de passe initial commun.
     */
    public function test_teacher_accounts_are_created_with_their_phone_or_the_next_provisional_identifier(): void
    {
        config(['presence.mot_de_passe_initial' => '87654321']);
        User::factory()->enseignant()->create(['name' => 'Ancien', 'phone' => 'ENS0007']);
        $this->scenario(new ModeleFictif([]));
        $conversation = $this->conversation();
        $conversation->fill(['actions' => [
            $this->action('creer_enseignant', ['nom' => 'Dr. Awa Ndiaye', 'telephone' => '699000123', 'email' => null], 'a1'),
            $this->action('creer_enseignant', ['nom' => 'Paul Essomba', 'telephone' => null, 'email' => 'paul@example.com'], 'a2'),
            $this->action('creer_cours', [
                'salle_id' => $this->salle->id, 'matiere_id' => null, 'matiere_nom' => 'Réseaux', 'matiere_code' => null,
                'enseignant_id' => null, 'enseignant_nom' => 'Marie Nkolo', 'enseignant_telephone' => '677000001',
                'jour' => 'LUNDI', 'heure_debut' => '08:00', 'heure_fin' => '10:00', 'date_debut' => null, 'date_fin' => null,
            ], 'a3'),
            $this->action('creer_enseignant', ['nom' => 'Doublon', 'telephone' => '699000123', 'email' => null], 'a4'),
        ]])->save();

        $reponse = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/assistant/conversations/{$conversation->id}/appliquer", ['ids' => ['a1', 'a2', 'a3', 'a4']]);

        $reponse->assertJsonPath('appliquees', 3)->assertJsonPath('echouees', 1);
        $actions = collect($reponse->json('actions'))->keyBy('id');

        $this->assertSame(['telephone' => '699000123', 'mot_de_passe_initial' => '87654321'], collect($actions['a1']['resultat']['details'])->only('telephone', 'mot_de_passe_initial')->all());
        $this->assertDatabaseHas('users', ['name' => 'Awa Ndiaye', 'phone' => '699000123', 'role' => 'Enseignant', 'validation_status' => 'approved']);
        $this->assertSame('ENS0008', $actions['a2']['resultat']['details']['telephone'], 'Le numéro provisoire suit le plus haut attribué.');
        $this->assertDatabaseHas('users', ['name' => 'Paul Essomba', 'phone' => 'ENS0008', 'email' => 'paul@example.com']);
        $this->assertSame('677000001', $actions['a3']['resultat']['details']['enseignant_cree']['identifiant'], 'Le téléphone donné avec le cours sert d\'identifiant.');
        $this->assertStringContainsString('téléphone', $actions['a4']['resultat']['message']);

        foreach (['699000123', 'ENS0008', '677000001'] as $identifiant) {
            $this->assertTrue(Hash::check('87654321', User::where('phone', $identifiant)->first()->password));
        }
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

    /**
     * Chaque filière a ses matières : une matière inconnue citée par un
     * emploi du temps est créée dans la filière de la salle du cours, et une
     * matière homonyme d'une autre filière n'est pas réutilisée.
     */
    public function test_an_unknown_subject_is_created_in_the_filiere_of_the_salle(): void
    {
        $autreFiliere = Filiere::factory()->create();
        $homonyme = Matiere::factory()->create(['nom' => 'Réseaux', 'code' => 'RES201', 'filiere_id' => $autreFiliere->id]);
        $this->scenario(new ModeleFictif([]));
        $conversation = $this->conversation();
        $conversation->fill(['actions' => [$this->action('creer_cours', [
            'salle_id' => $this->salle->id, 'matiere_id' => null, 'matiere_nom' => 'Réseaux', 'matiere_code' => 'RES201',
            'enseignant_id' => $this->prof->id, 'enseignant_nom' => null,
            'jour' => 'JEUDI', 'heure_debut' => '14:00', 'heure_fin' => '16:00', 'date_debut' => '2026-09-21', 'date_fin' => '2026-09-27',
        ], 'a1')]])->save();

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/assistant/conversations/{$conversation->id}/appliquer", ['ids' => ['a1']])
            ->assertJsonPath('appliquees', 1);

        $this->assertDatabaseCount('matieres', 2);
        $creee = Matiere::where('id', '!=', $homonyme->id)->first();
        $this->assertSame($this->salle->filiere_id, $creee->filiere_id, 'Créée dans la filière de la salle.');
        $this->assertSame('RES201', $creee->code, 'Le même code peut vivre dans deux filières.');
        $this->assertDatabaseHas('course_templates', ['matiere_id' => $creee->id]);
    }

    public function test_applying_a_student_enrolment_creates_the_account_with_the_initial_password(): void
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
        $this->assertSame('12345678', $actions['a1']['resultat']['details']['mot_de_passe_initial'], 'Le mot de passe initial commun, celui de config/presence.php.');
        $this->assertStringContainsString('matricule', $actions['a2']['resultat']['message']);
        $this->assertStringContainsString("n'accueille pas", $actions['a3']['resultat']['message']);

        $etudiant = User::where('phone', '24I09999')->first();
        $this->assertNotNull($etudiant);
        $this->assertSame($this->salle->id, $etudiant->salle_id);
        $this->assertSame($this->salle->filiere_id, $etudiant->filiere_id);
        $this->assertTrue(Hash::check($actions['a1']['resultat']['details']['mot_de_passe_initial'], $etudiant->password));
        $this->assertTrue($etudiant->doit_changer_mot_de_passe);
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

        $this->assertCount(count(Outils::ACTIONS) - 2, $propositions, 'Les deux imports en masse passent par leurs propres outils.');
        $this->assertSame(Outils::IMPORTS, collect($definitions)->pluck('name')->filter(fn ($n) => Outils::estImport($n))->values()->all());
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
