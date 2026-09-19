<?php

namespace App\Http\Controllers\Api;

use App\Enums\PresenceState;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Departement;
use App\Models\Parametre;
use App\Models\Salle;
use App\Models\Seance;
use App\Models\Semaine;
use App\Services\ListeHebdomadaire;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PdfController extends Controller
{
    /**
     * Liste de présence PDF d'une séance verrouillée — reprend
     * generatePresencePDF() de l'ancienne app (liste.php), avec la division
     * par zéro corrigée (roster vide) et le "surlignage formation différente"
     * de l'ancienne app abandonné (jamais réellement implémenté là-bas,
     * malgré le texte de pied de page qui le promettait).
     */
    public function presenceList(Request $request, Seance $seance)
    {
        $user = $request->user();
        $isDelegueDeLaSalle = $user->effectiveRole() === UserRole::Delegue && $seance->salle_id === $user->salle_id;

        abort_unless($isDelegueDeLaSalle || $user->isAdmin(), 403);
        abort_unless($seance->presences_locked, 422, "Les présences de cette séance n'ont pas encore été verrouillées.");

        $presences = $seance->presences()->with('etudiant')->orderBy('etudiant_id')->get();
        $total = $presences->count();
        $present = $presences->where('etat', PresenceState::Present)->count();

        $stats = [
            'total' => $total,
            'present' => $present,
            'absent' => $total - $present,
            'taux' => $total > 0 ? round($present / $total * 100) : 0,
        ];

        $seance->load(['salle', 'enseignant', 'courseTemplate.matiere']);

        $pdf = Pdf::loadView('pdf.presence', compact('seance', 'presences', 'stats'));

        return $pdf->download("presence_seance_{$seance->id}.pdf");
    }

    /**
     * Liste de présence hebdomadaire d'une salle au format officiel du
     * département : en-tête bilingue, une colonne de signature par jour,
     * tableau des séances de la semaine prérempli depuis l'emploi du temps.
     * Réservée à l'admin (groupe de routes).
     */
    public function listeHebdomadaire(Request $request, Salle $salle, ListeHebdomadaire $liste)
    {
        $data = $this->optionsDeListe($request);

        $semaine = Semaine::findOrFail($data['semaine_id']);
        $migrants = $request->boolean('migrants');
        $donnees = $liste->pour($salle, $semaine, $data['semestre'] ?? null, $data['annee'] ?? null, $data['symboles'] ?? null, $migrants);

        $pdf = Pdf::loadView('pdf.liste-hebdomadaire', $donnees)->setPaper('a4', 'landscape');

        $nom = 'liste_presence_'.($migrants ? 'migrants_' : '').Str::slug($salle->nom).'_S'.$semaine->numero.'.pdf';

        return $pdf->download($nom);
    }

    /**
     * Les listes de toutes les salles d'un département en un seul PDF, une
     * page par salle — ce que l'admin imprime pour tout le GI (ou tout le
     * GRT) d'un coup, au lieu de salle par salle.
     */
    public function listeDepartement(Request $request, Departement $departement, ListeHebdomadaire $liste)
    {
        $data = $this->optionsDeListe($request);

        $semaine = Semaine::findOrFail($data['semaine_id']);
        $donnees = $liste->pourDepartement($departement, $semaine, $data['semestre'] ?? null, $data['annee'] ?? null, $data['symboles'] ?? null);

        abort_if($donnees['listes']->isEmpty(), 422, "Aucune salle n'est rattachée au département {$departement->nom}.");

        $pdf = Pdf::loadView('pdf.liste-departement', $donnees)->setPaper('a4', 'landscape');

        $nom = 'listes_presence_'.Str::slug($departement->code).'_S'.$semaine->numero.'.pdf';

        return $pdf->download($nom);
    }

    /**
     * @return array<string, mixed>
     */
    private function optionsDeListe(Request $request): array
    {
        return $request->validate([
            'semaine_id' => ['required', 'integer', 'exists:semaines,id'],
            'semestre' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:6'],
            'annee' => ['sometimes', 'nullable', 'regex:/^\d{4}-\d{4}$/'],
            // Sans valeur : le réglage enregistré par l'admin (Parametre::symbolesPresence).
            'symboles' => ['sometimes', 'nullable', Rule::in(Parametre::SYMBOLES_PRESENCE_CHOIX)],
            // Vrai : seuls les étudiants migrants (FM) de la salle figurent sur la liste.
            'migrants' => ['sometimes', 'boolean'],
        ]);
    }
}
