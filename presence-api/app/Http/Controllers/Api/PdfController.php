<?php

namespace App\Http\Controllers\Api;

use App\Enums\PresenceState;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Seance;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

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
}
