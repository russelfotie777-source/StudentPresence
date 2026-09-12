<?php

namespace App\Http\Controllers\Api;

use App\Enums\PresenceState;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Salle;
use App\Models\Seance;
use App\Models\Semaine;
use App\Services\ListeHebdomadaire;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

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
        $data = $request->validate([
            'semaine_id' => ['required', 'integer', 'exists:semaines,id'],
            'semestre' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:6'],
            'annee' => ['sometimes', 'nullable', 'regex:/^\d{4}-\d{4}$/'],
        ]);

        $semaine = Semaine::findOrFail($data['semaine_id']);
        $donnees = $liste->pour($salle, $semaine, $data['semestre'] ?? null, $data['annee'] ?? null);

        $pdf = Pdf::loadView('pdf.liste-hebdomadaire', $donnees)->setPaper('a4', 'landscape');

        $nom = 'liste_presence_'.Str::slug($salle->nom).'_S'.$semaine->numero.'.pdf';

        return $pdf->download($nom);
    }
}
