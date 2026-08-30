<?php

namespace App\Http\Controllers\Api;

use App\Enums\PresenceState;
use App\Http\Controllers\Controller;
use App\Http\Resources\SeanceResource;
use App\Models\Seance;
use Illuminate\Http\Request;

class SessionHistoryController extends Controller
{
    /**
     * Historique filtrable des séances — reprend
     * superprotect/historique_seances.php.
     */
    public function index(Request $request)
    {
        $data = $request->validate([
            'enseignant_id' => ['sometimes', 'exists:users,id'],
            'semaine_id' => ['sometimes', 'exists:semaines,id'],
            'salle_id' => ['sometimes', 'exists:salles,id'],
            'matiere_id' => ['sometimes', 'exists:matieres,id'],
        ]);

        $seances = Seance::query()
            ->with(['salle.filiere.niveau', 'enseignant', 'courseTemplate.matiere', 'semaine'])
            ->when($data['enseignant_id'] ?? null, fn ($q, $v) => $q->where('enseignant_id', $v))
            ->when($data['semaine_id'] ?? null, fn ($q, $v) => $q->where('semaine_id', $v))
            ->when($data['salle_id'] ?? null, fn ($q, $v) => $q->where('salle_id', $v))
            ->when($data['matiere_id'] ?? null, fn ($q, $v) => $q->whereHas('courseTemplate', fn ($q2) => $q2->where('matiere_id', $v)))
            ->orderByDesc('date_seance')
            ->orderBy('heure_debut')
            ->get();

        $present = $seances->where('etat_final', PresenceState::Present);

        return response()->json([
            'seances' => SeanceResource::collection($seances),
            'stats' => [
                'total' => $seances->count(),
                'present' => $present->count(),
                'absent' => $seances->count() - $present->count(),
            ],
        ]);
    }
}
