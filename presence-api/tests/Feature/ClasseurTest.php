<?php

namespace Tests\Feature;

use App\Enums\FormationType;
use App\Models\Departement;
use App\Models\Filiere;
use App\Models\Niveau;
use App\Models\Salle;
use App\Models\Seance;
use App\Models\Semaine;
use App\Models\TarifHeure;
use App\Models\User;
use App\Services\Classeurs;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Tests\TestCase;

/**
 * Les exports Excel : les mêmes listes que les PDF, lisibles cellule par
 * cellule, et la comptabilité d'une période — réservés à l'admin.
 */
class ClasseurTest extends TestCase
{
    use RefreshDatabase;

    private const TYPE = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_the_weekly_list_of_a_salle_becomes_a_sheet_with_the_official_layout(): void
    {
        Carbon::setTestNow('2026-02-05 09:00:00');
        [$salle, $semaine] = $this->salleEtSemaine('Génie Informatique', 'L2');
        User::factory()->etudiant($salle)->create(['name' => 'Zoé Fi', 'phone' => '24I09002', 'formation' => FormationType::FI]);
        User::factory()->etudiant($salle)->create(['name' => 'Ali Migrant', 'phone' => '24I09001', 'formation' => FormationType::FM]);
        Seance::factory()->create(['salle_id' => $salle->id, 'date_seance' => '2026-02-04', 'jour' => 'MERCREDI', 'heure_debut' => '08:00', 'heure_fin' => '10:30']);

        $reponse = $this->actingAs(User::factory()->admin()->create(), 'sanctum')
            ->get("/api/salles/{$salle->id}/liste-presence.xlsx?semaine_id={$semaine->id}&semestre=4&annee=2025-2026")
            ->assertOk()
            ->assertHeader('content-type', self::TYPE);
        $this->assertStringContainsString('liste_presence_'.Str::slug($salle->nom).'_S1.xlsx', $reponse->headers->get('content-disposition'));

        $feuille = $this->classeur($reponse)->getActiveSheet();
        $this->assertSame(Classeurs::titreDeFeuille($salle->nom), $feuille->getTitle());
        $this->assertSame('REPUBLIQUE DU CAMEROUN', $feuille->getCell('A1')->getValue());
        $this->assertSame('REPUBLIC OF CAMEROON', $feuille->getCell('G1')->getValue());
        $this->assertSame('OPTION : GI   NIVEAU : II   ANNEE ACADEMIQUE : 2025-2026', $feuille->getCell('D3')->getValue());
        $this->assertSame('LISTE DE PRESENCE DES ETUDIANTS', $feuille->getCell('D4')->getValue());
        $this->assertSame('SEMESTRE 4', $feuille->getCell('D5')->getValue());
        $this->assertSame("SALLE : {$salle->nom}", $feuille->getCell('A9')->getValue());
        $this->assertSame('SEMAINE DU 02/02/2026 AU 08/02/2026', $feuille->getCell('G9')->getValue());

        // Les étudiants, dans l'ordre de la liste papier, le matricule gardé en texte.
        $this->assertSame(['N°', 'Matricule', 'Noms & Prénoms', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'], array_map(fn ($c) => $feuille->getCell($c.'11')->getValue(), range('A', 'I')));
        $this->assertSame('24I09001', $feuille->getCell('B12')->getValue());
        $this->assertSame('ALI MIGRANT  (FM)', $feuille->getCell('C12')->getValue());
        $this->assertSame('ZOÉ FI', $feuille->getCell('C13')->getValue());

        // Le tableau des séances, prérempli depuis l'emploi du temps.
        $lignes = collect(range(1, $feuille->getHighestRow()))->map(fn ($r) => $feuille->getCell("A{$r}")->getValue());
        $mercredi = $lignes->search('Mercredi');
        $this->assertNotFalse($mercredi);
        $mercredi++; // l'index de la collection part de 0, les lignes Excel de 1
        $this->assertSame('08:00', $feuille->getCell("E{$mercredi}")->getValue());
        $this->assertSame('10:30', $feuille->getCell("F{$mercredi}")->getValue());
        $this->assertSame('2h30', $feuille->getCell("G{$mercredi}")->getValue());
    }

    public function test_a_departement_becomes_one_sheet_per_salle(): void
    {
        [$salleA, $semaine] = $this->salleEtSemaine('Génie Informatique', 'L1', 'A11-FI');
        $departement = $salleA->filiere->departement;
        $filiereB = Filiere::factory()->create(['nom' => 'Génie Informatique', 'niveau_id' => Niveau::factory()->create(['nom' => 'L2'])->id, 'departement_id' => $departement->id]);
        Salle::factory()->create(['nom' => 'A23-FI', 'filiere_id' => $filiereB->id, 'formation' => FormationType::FI]);

        $reponse = $this->actingAs(User::factory()->admin()->create(), 'sanctum')
            ->get("/api/departements/{$departement->id}/liste-presence.xlsx?semaine_id={$semaine->id}")
            ->assertOk();

        $this->assertSame(['A11-FI', 'A23-FI'], $this->classeur($reponse)->getSheetNames());
    }

    public function test_the_accounting_workbook_has_a_summary_and_a_pay_sheet(): void
    {
        Carbon::setTestNow('2026-03-10 12:00:00');
        $niveau = Niveau::factory()->create(['nom' => 'L2']);
        $filiere = Filiere::factory()->create(['niveau_id' => $niveau->id]);
        $salle = Salle::factory()->create(['filiere_id' => $filiere->id, 'formation' => FormationType::FI]);
        TarifHeure::create(['niveau_id' => $niveau->id, 'tarif_heure' => 3000]);
        User::factory()->etudiant($salle)->count(2)->create();
        $prof = User::factory()->enseignant()->create(['name' => 'Étienne Mballa']);
        Seance::factory()->create([
            'salle_id' => $salle->id, 'enseignant_id' => $prof->id, 'date_seance' => '2026-03-02', 'jour' => 'LUNDI',
            'heure_debut' => '08:00', 'heure_fin' => '09:00', 'debut_reel' => '08:00:00', 'fin_reelle' => '09:00:00',
            'etat_delegue' => 'present', 'etat_prof' => 'present',
        ]);

        $reponse = $this->actingAs(User::factory()->admin()->create(), 'sanctum')
            ->get('/api/comptabilite.xlsx')
            ->assertOk()
            ->assertHeader('content-type', self::TYPE);
        $this->assertStringContainsString('comptabilite_2025-09-01_2026-03-10.xlsx', $reponse->headers->get('content-disposition'));

        $classeur = $this->classeur($reponse);
        $this->assertSame(['Synthèse', 'Paie'], $classeur->getSheetNames());

        $synthese = $classeur->getSheetByName('Synthèse');
        $this->assertSame('du 2025-09-01 au 2026-03-10', $synthese->getCell('B1')->getValue());
        $this->assertSame('Étudiants', $synthese->getCell('A4')->getValue());
        $this->assertSame(2, $synthese->getCell('B4')->getValue());
        $this->assertSame('dont FM', $synthese->getCell('A7')->getValue());
        $this->assertSame(0, $synthese->getCell('B7')->getValue(), 'Un zéro est une valeur, pas une case vide.');

        $paie = $classeur->getSheetByName('Paie');
        $this->assertSame('Enseignant', $paie->getCell('A1')->getValue());
        $this->assertSame('Étienne Mballa', $paie->getCell('A2')->getValue());
        $this->assertSame(3000, (int) $paie->getCell('D2')->getValue());
        $this->assertSame('Total', $paie->getCell('A3')->getValue());
        $this->assertSame(3000, (int) $paie->getCell('D3')->getValue());
    }

    public function test_excel_exports_are_for_the_admin_only(): void
    {
        [$salle, $semaine] = $this->salleEtSemaine();

        foreach ([User::factory()->enseignant()->create(), User::factory()->delegue($salle)->create()] as $u) {
            $this->actingAs($u, 'sanctum')->get("/api/salles/{$salle->id}/liste-presence.xlsx?semaine_id={$semaine->id}")->assertForbidden();
            $this->actingAs($u, 'sanctum')->get('/api/comptabilite.xlsx')->assertForbidden();
        }

        $this->actingAs(User::factory()->admin()->create(), 'sanctum')
            ->getJson("/api/salles/{$salle->id}/liste-presence.xlsx")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('semaine_id');
    }

    public function test_sheet_titles_fit_excel(): void
    {
        $this->assertSame('A23-FI', Classeurs::titreDeFeuille('A23-FI'));
        $this->assertSame('Salle B 2 GI', Classeurs::titreDeFeuille('Salle B/[2]:GI'));
        $this->assertSame(31, mb_strlen(Classeurs::titreDeFeuille(str_repeat('x', 40))));
    }

    /** @return array{0: Salle, 1: Semaine} */
    private function salleEtSemaine(string $filiere = 'Génie Informatique', string $niveau = 'L3', ?string $nom = null): array
    {
        $niveauModel = Niveau::factory()->create(['nom' => $niveau]);
        $filiereModel = Filiere::factory()->create(['nom' => $filiere, 'niveau_id' => $niveauModel->id, 'departement_id' => Departement::factory()->create(['code' => 'GI', 'nom' => 'Génie Informatique'])->id]);
        $salle = Salle::factory()->create(array_filter(['filiere_id' => $filiereModel->id, 'formation' => FormationType::FI, 'nom' => $nom]));
        $semaine = Semaine::factory()->create(['numero' => 1, 'date_debut' => '2026-02-02', 'date_fin' => '2026-02-08']);

        return [$salle, $semaine];
    }

    private function classeur(TestResponse $reponse): Spreadsheet
    {
        $chemin = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($chemin, $reponse->streamedContent());

        return IOFactory::load($chemin);
    }
}
