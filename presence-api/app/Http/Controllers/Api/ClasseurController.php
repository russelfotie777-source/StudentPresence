<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\OptionsListeRequest;
use App\Models\Departement;
use App\Models\Salle;
use App\Models\Semaine;
use App\Services\Classeurs;
use App\Services\Comptabilite;
use App\Services\ListeHebdomadaire;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Les exports Excel du back-office : les mêmes listes de présence que les
 * PDF (une salle, tout un département, les seuls migrants) et la
 * comptabilité d'une période. Réservés à l'admin (groupe de routes).
 */
class ClasseurController extends Controller
{
    private const TYPE = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

    public function __construct(private Classeurs $classeurs) {}

    public function listeHebdomadaire(OptionsListeRequest $request, Salle $salle, ListeHebdomadaire $liste): StreamedResponse
    {
        $data = $request->validated();
        $semaine = Semaine::findOrFail($data['semaine_id']);
        $migrants = $request->boolean('migrants');

        $donnees = $liste->pour($salle, $semaine, $data['semestre'] ?? null, $data['annee'] ?? null, $data['symboles'] ?? null, $migrants);
        $nom = 'liste_presence_'.($migrants ? 'migrants_' : '').Str::slug($salle->nom).'_S'.$semaine->numero.'.xlsx';

        return $this->telecharger($this->classeurs->listeHebdomadaire($donnees), $nom);
    }

    public function listeDepartement(OptionsListeRequest $request, Departement $departement, ListeHebdomadaire $liste): StreamedResponse
    {
        $data = $request->validated();
        $semaine = Semaine::findOrFail($data['semaine_id']);

        $donnees = $liste->pourDepartement($departement, $semaine, $data['semestre'] ?? null, $data['annee'] ?? null, $data['symboles'] ?? null);
        abort_if($donnees['listes']->isEmpty(), 422, "Aucune salle n'est rattachée au département {$departement->nom}.");

        $nom = 'listes_presence_'.Str::slug($departement->code).'_S'.$semaine->numero.'.xlsx';

        return $this->telecharger($this->classeurs->listeDepartement($donnees), $nom);
    }

    public function comptabilite(Request $request, Comptabilite $comptabilite): StreamedResponse
    {
        $data = $request->validate([
            'du' => ['sometimes', 'date'],
            'au' => ['sometimes', 'date', 'after_or_equal:du'],
        ]);

        $donnees = $comptabilite->pour(
            isset($data['du']) ? Carbon::parse($data['du']) : null,
            isset($data['au']) ? Carbon::parse($data['au']) : null,
        );
        $nom = "comptabilite_{$donnees['periode']['du']}_{$donnees['periode']['au']}.xlsx";

        return $this->telecharger($this->classeurs->comptabilite($donnees), $nom);
    }

    private function telecharger(Spreadsheet $classeur, string $nom): StreamedResponse
    {
        return response()->streamDownload(
            fn () => (new Xlsx($classeur))->save('php://output'),
            $nom,
            ['Content-Type' => self::TYPE],
        );
    }
}
