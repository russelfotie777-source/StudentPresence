<?php

namespace Tests\Feature;

use App\Enums\FormationType;
use App\Models\ConversationIA;
use App\Models\Salle;
use App\Models\Semaine;
use App\Models\User;
use App\Services\Assistant\ExtracteurPDF;
use App\Services\Assistant\Modele;
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
 * Imports en masse par l'assistant : un tableur dont le modèle désigne les
 * colonnes, un long PDF lu par tranches — le serveur construit les lignes,
 * l'admin applique, les identifiants sortent en CSV.
 */
class AssistantImportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Salle $salle;

    private Salle $salleFA;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-09-16 10:00:00', 'Africa/Douala'));
        Storage::fake('local');
        $this->admin = User::factory()->admin()->create();
        $this->salle = Salle::factory()->create(['nom' => 'A23-FI', 'formation' => FormationType::FI]);
        $this->salleFA = Salle::factory()->create(['nom' => 'D4-FA', 'formation' => FormationType::FA]);
        Semaine::factory()->create(['numero' => 1, 'date_debut' => '2026-09-14', 'date_fin' => '2026-09-20']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function conversation(): ConversationIA
    {
        return ConversationIA::create(['admin_id' => $this->admin->id]);
    }

    /**
     * @param  list<UploadedFile>  $fichiers
     * @return array<string, mixed>
     */
    private function envoyer(ConversationIA $conversation, string $texte, array $fichiers = []): array
    {
        return $this->actingAs($this->admin, 'sanctum')
            ->post("/api/assistant/conversations/{$conversation->id}/messages", ['texte' => $texte, 'fichiers' => $fichiers], ['Accept' => 'application/json'])
            ->assertStatus(202)
            ->json('conversation');
    }

    /**
     * @param  array<string, list<list<string|int>>>  $feuilles
     */
    private function tableur(array $feuilles): UploadedFile
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

        return new UploadedFile($chemin, 'inscriptions.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    private function pdf(int $pages): UploadedFile
    {
        $html = implode('<div style="page-break-after: always"></div>', array_map(fn ($i) => "<p>Page {$i}</p>", range(1, $pages)));
        $chemin = tempnam(sys_get_temp_dir(), 'pdf');
        file_put_contents($chemin, Pdf::loadHTML($html)->output());

        return new UploadedFile($chemin, 'liste.pdf', 'application/pdf', null, true);
    }

    // --- tableur -------------------------------------------------------------

    public function test_a_spreadsheet_is_previewed_to_the_model_and_imported_by_columns(): void
    {
        $lignes = [['LISTE L3 GI'], [], ['N°', 'Matricule', 'Nom et prénoms', 'Sexe']];
        for ($i = 1; $i <= 300; $i++) {
            $lignes[] = [$i, sprintf('24I%05d', $i), "Étudiant {$i}", $i % 2 ? 'M' : 'F'];
        }
        $lignes[] = [301, '', 'Sans matricule', 'M'];

        $modele = $this->app->instance(Modele::class, new ModeleFictif([
            ModeleFictif::outils([['name' => 'proposer_import_etudiants', 'input' => [
                'resume' => 'Inscrire les étudiants de la feuille L3 GI en A23-FI (FI)',
                'fichier' => 1, 'feuille' => 'L3 GI',
                'colonne_nom' => 'Nom et prénoms', 'colonne_matricule' => 'Matricule',
                'colonne_formation' => null, 'colonne_salle' => null,
                'salle_id' => $this->salle->id, 'formation' => 'FI', 'ligne_debut' => null,
            ]]]),
            ModeleFictif::texte('Import préparé : 301 lignes, 1 anomalie.'),
        ]));
        $conversation = $this->conversation();

        $c = $this->envoyer($conversation, 'Inscris ces étudiants en A23-FI', [
            $this->tableur(['L3 GI' => $lignes, 'Vide' => [['rien']]]),
        ]);

        // Le modèle a vu un aperçu (pas les 300 lignes) et le nom de la feuille.
        $apercu = $modele->appels[0]['messages'][0]['content'][0]['text'];
        $this->assertStringContainsString('Feuille « L3 GI » — 301 ligne(s) de données, en-tête ligne 3', $apercu);
        $this->assertStringContainsString('Nom et prénoms', $apercu);
        $this->assertStringContainsString('autres lignes', $apercu);
        $this->assertStringNotContainsString('Étudiant 200', $apercu);

        // Le serveur a construit l'import complet.
        $this->assertSame('termine', $c['traitement']['statut']);
        $this->assertCount(1, $c['actions']);
        $action = $c['actions'][0];
        $this->assertSame('importer_etudiants', $action['type']);
        $this->assertSame(301, $action['parametres']['total']);
        $this->assertSame(1, $action['parametres']['anomalies']);
        $this->assertCount(8, $action['parametres']['apercu']);
        $this->assertArrayNotHasKey('etudiants', $action['parametres'], 'Les lignes complètes ne transitent pas vers l\'écran.');

        $complet = collect($conversation->fresh()->actions)->firstWhere('id', $action['id']);
        $this->assertCount(301, $complet['parametres']['etudiants']);
        $this->assertSame(['nom' => 'Étudiant 1', 'matricule' => '24I00001', 'formation' => 'FI', 'salle_id' => $this->salle->id], collect($complet['parametres']['etudiants'][0])->only(['nom', 'matricule', 'formation', 'salle_id'])->all());
        $this->assertSame('matricule manquant', end($complet['parametres']['etudiants'])['anomalie']);

        // Le compte rendu au modèle porte total et anomalies.
        $retour = json_decode($modele->appels[1]['messages'][2]['content'][0]['content'], true);
        $this->assertSame(301, $retour['total']);
        $this->assertSame(1, $retour['anomalies']);
    }

    public function test_a_spreadsheet_with_room_and_formation_columns_resolves_them_per_row(): void
    {
        $this->app->instance(Modele::class, new ModeleFictif([
            ModeleFictif::outils([['name' => 'proposer_import_etudiants', 'input' => [
                'resume' => 'Import multi-salles', 'fichier' => 1, 'feuille' => null,
                'colonne_nom' => 'Nom', 'colonne_matricule' => 'Matricule',
                'colonne_formation' => 'Formation', 'colonne_salle' => 'Classe',
                'salle_id' => null, 'formation' => null, 'ligne_debut' => null,
            ]]]),
            ModeleFictif::texte('ok'),
        ]));
        $conversation = $this->conversation();

        $this->envoyer($conversation, 'Importe', [$this->tableur(['Tous' => [
            ['Nom', 'Matricule', 'Classe', 'Formation'],
            ['Awa', 'M1', 'A23-FI', 'FI'],
            ['Ben', 'M2', 'D4 FA', 'Alternance'],
            ['Cyr', 'M3', 'Z9-XX', 'FI'],
            ['Dan', 'M4', 'a23fi', 'FM'],
        ]])]);

        $lignes = collect(collect($conversation->fresh()->actions)->first()['parametres']['etudiants']);
        $this->assertSame([$this->salle->id, $this->salleFA->id, null, $this->salle->id], $lignes->pluck('salle_id')->all());
        $this->assertSame(['FI', 'FA', 'FI', 'FM'], $lignes->pluck('formation')->all());
        $this->assertStringContainsString('salle non reconnue', $lignes[2]['anomalie']);
    }

    public function test_an_unknown_column_is_reported_to_the_model_as_a_tool_error(): void
    {
        $modele = $this->app->instance(Modele::class, new ModeleFictif([
            ModeleFictif::outils([['name' => 'proposer_import_etudiants', 'input' => [
                'resume' => 'x', 'fichier' => 1, 'feuille' => null, 'colonne_nom' => 'Prénom complet', 'colonne_matricule' => 'Matricule',
                'colonne_formation' => null, 'colonne_salle' => null, 'salle_id' => $this->salle->id, 'formation' => 'FI', 'ligne_debut' => null,
            ]]]),
            ModeleFictif::texte('Je corrige.'),
        ]));
        $conversation = $this->conversation();

        $c = $this->envoyer($conversation, 'Importe', [$this->tableur(['F' => [['Nom', 'Matricule'], ['Awa', 'M1']]])]);

        $retour = $modele->appels[1]['messages'][2]['content'][0];
        $this->assertTrue($retour['isError']);
        $this->assertStringContainsString('Colonne « Prénom complet » introuvable', $retour['content']);
        $this->assertCount(0, $c['actions']);
    }

    // --- application ----------------------------------------------------------

    public function test_applying_an_import_creates_the_accounts_row_by_row_and_exports_credentials(): void
    {
        User::factory()->etudiant($this->salle)->create(['phone' => 'DEJA']);
        $lignes = [];
        for ($i = 1; $i <= 250; $i++) {
            $lignes[] = ['ligne' => $i + 1, 'nom' => "Étudiant {$i}", 'matricule' => sprintf('25I%05d', $i), 'formation' => 'FI', 'salle_id' => $this->salle->id, 'salle_nom' => 'A23-FI', 'anomalie' => null];
        }
        $lignes[] = ['ligne' => 252, 'nom' => 'Doublon', 'matricule' => 'DEJA', 'formation' => 'FI', 'salle_id' => $this->salle->id, 'salle_nom' => 'A23-FI', 'anomalie' => null];
        $lignes[] = ['ligne' => 253, 'nom' => 'Mauvaise', 'matricule' => 'X1', 'formation' => 'FA', 'salle_id' => $this->salle->id, 'salle_nom' => 'A23-FI', 'anomalie' => null];
        $lignes[] = ['ligne' => 254, 'nom' => 'Anomalie', 'matricule' => 'X2', 'formation' => 'FI', 'salle_id' => null, 'salle_nom' => null, 'anomalie' => 'salle non reconnue'];

        $conversation = $this->conversation();
        $conversation->fill(['actions' => [[
            'id' => 'imp', 'type' => 'importer_etudiants', 'resume' => 'Import test',
            'parametres' => ['source' => ['fichier' => 'x.xlsx'], 'etudiants' => $lignes, 'total' => count($lignes), 'apercu' => [], 'anomalies' => 1],
            'statut' => 'en_attente', 'resultat' => null, 'proposee_le' => now()->toIso8601String(),
        ]]])->save();

        $reponse = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/assistant/conversations/{$conversation->id}/appliquer", ['ids' => ['imp']]);

        $reponse->assertOk()->assertJsonPath('appliquees', 1);
        $action = $reponse->json('actions.0');
        $this->assertSame('appliquee', $action['statut']);
        $this->assertSame(250, $action['resultat']['details']['crees']);
        $this->assertSame(3, $action['resultat']['details']['echecs_total']);
        $this->assertSame(250, $action['resultat']['details']['identifiants_count']);
        $this->assertArrayNotHasKey('identifiants', $action['resultat']['details'], 'Les mots de passe ne transitent que par le CSV.');
        $this->assertStringContainsString('250 étudiants inscrits sur 253', $action['resultat']['message']);
        $motifs = array_column($action['resultat']['details']['echecs'], 'motif');
        $this->assertStringContainsString('déjà utilisée', $motifs[0]);
        $this->assertStringContainsString("n'accueille pas", $motifs[1]);
        $this->assertSame('salle non reconnue', $motifs[2]);

        $this->assertSame(250, User::where('phone', 'like', '25I%')->count());

        $csv = $this->actingAs($this->admin, 'sanctum')
            ->get("/api/assistant/conversations/{$conversation->id}/actions/imp/identifiants.csv");
        $csv->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $contenu = $csv->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $contenu);
        $this->assertSame(251, substr_count($contenu, "\n"), 'En-tête + 250 lignes.');
        $this->assertMatchesRegularExpression('/25I00001;\d{8};A23-FI/', $contenu);

        // Le mot de passe du CSV ouvre bien le compte.
        preg_match('/"?Étudiant 1"?;25I00001;(\d{8})/', $contenu, $m);
        $this->assertTrue(Hash::check($m[1], User::where('phone', '25I00001')->first()->password));
    }

    // --- PDF par tranches ------------------------------------------------------

    public function test_a_long_pdf_is_read_in_page_slices_and_becomes_one_import(): void
    {
        $tranche = fn (array $noms) => ['etudiants' => array_map(fn ($n) => ['nom' => $n, 'matricule' => 'M'.crc32($n), 'formation' => null, 'salle' => 'A23-FI'], $noms)];

        $modele = $this->app->instance(Modele::class, new ModeleFictif(
            [
                ModeleFictif::outils([['name' => 'extraire_etudiants_pdf', 'input' => ['resume' => 'Import du PDF', 'fichier' => 1, 'salle_id' => null, 'formation' => 'FI']]]),
                ModeleFictif::texte('Terminé.'),
            ],
            structures: [
                $tranche(['A', 'B', 'C']),  // pages 1–8
                null,                        // pages 9–16 débordent → coupées en 9–12 et 13–16
                $tranche(['D']),             // pages 9–12
                $tranche(['E', 'F']),        // pages 13–16
                $tranche(['G']),             // pages 17–20
            ],
        ));
        $conversation = $this->conversation();

        $c = $this->envoyer($conversation, 'Inscris tout le monde', [$this->pdf(20)]);

        $this->assertSame('termine', $c['traitement']['statut']);
        $this->assertCount(5, $modele->extractions, '3 tranches prévues, dont une coupée en deux.');
        $this->assertStringContainsString('pages 1 à 8 sur 20', $modele->extractions[0]['consigne']);
        $this->assertStringContainsString('pages 9 à 12 sur 20', $modele->extractions[2]['consigne']);
        $this->assertSame(ExtracteurPDF::SCHEMAS['etudiants'], $modele->extractions[0]['schema']);
        $this->assertStringStartsWith('JVBERi', $modele->extractions[0]['pdf'], 'Chaque tranche est un vrai PDF.');

        $action = $c['actions'][0];
        $this->assertSame('importer_etudiants', $action['type']);
        $this->assertSame(7, $action['parametres']['total']);
        $this->assertSame(0, $action['parametres']['anomalies']);
        $lignes = collect($conversation->fresh()->actions)->first()['parametres']['etudiants'];
        $this->assertSame(['A', 'B', 'C', 'D', 'E', 'F', 'G'], array_column($lignes, 'nom'));
        $this->assertSame([$this->salle->id], array_unique(array_column($lignes, 'salle_id')));
        $this->assertSame(['FI'], array_unique(array_column($lignes, 'formation')));
        $this->assertSame('p. 9-12', $lignes[3]['ligne']);
    }

    public function test_a_timetable_pdf_becomes_a_bulk_course_import(): void
    {
        $modele = $this->app->instance(Modele::class, new ModeleFictif(
            [
                ModeleFictif::outils([['name' => 'extraire_cours_pdf', 'input' => ['resume' => 'Import EDT', 'fichier' => 1, 'salle_id' => null]]]),
                ModeleFictif::texte('Terminé.'),
            ],
            structures: [[
                'cours' => [
                    ['salle' => 'A23-FI', 'matiere' => 'Algorithmique', 'code' => 'INF101', 'enseignant' => 'Étienne Mballa', 'jour' => 'LUNDI', 'heure_debut' => '8h00', 'heure_fin' => '10:00'],
                    ['salle' => 'Inconnue', 'matiere' => 'Réseaux', 'code' => null, 'enseignant' => null, 'jour' => 'MARDI', 'heure_debut' => '10:00', 'heure_fin' => '12:00'],
                ],
            ]],
        ));
        $prof = User::factory()->enseignant()->create(['name' => 'Étienne Mballa']);
        $conversation = $this->conversation();

        $c = $this->envoyer($conversation, 'Crée cet emploi du temps', [$this->pdf(3)]);

        $this->assertSame('importer_cours', $c['actions'][0]['type']);
        $this->assertSame(2, $c['actions'][0]['parametres']['total']);
        $this->assertSame(1, $c['actions'][0]['parametres']['anomalies']);
        $this->assertCount(1, $modele->extractions, 'Trois pages : une seule tranche.');

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/assistant/conversations/{$conversation->id}/appliquer", ['ids' => [$c['actions'][0]['id']]])
            ->assertOk()
            ->assertJsonPath('actions.0.resultat.details.crees', 1)
            ->assertJsonPath('actions.0.resultat.details.echecs_total', 1);

        $this->assertDatabaseHas('course_templates', ['salle_id' => $this->salle->id, 'enseignant_id' => $prof->id, 'heure_debut' => '08:00']);
        $this->assertDatabaseHas('matieres', ['code' => 'INF101']);
        $this->assertDatabaseCount('seances', 1);
    }
}
