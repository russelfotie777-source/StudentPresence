<?php

namespace App\Http\Controllers\Api;

use App\Enums\PresenceState;
use App\Http\Controllers\Controller;
use App\Http\Resources\SeanceResource;
use App\Models\Seance;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class SessionHistoryController extends Controller
{
    private const PAR_PAGE = 25;

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
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $seances = $this->filtree($data)
            ->with(['salle.filiere.niveau', 'enseignant', 'courseTemplate.matiere', 'semaine'])
            ->orderByDesc('date_seance')
            ->orderBy('heure_debut')
            ->paginate($data['per_page'] ?? self::PAR_PAGE);

        // Les totaux portent sur l'ensemble filtré, pas sur la page affichée :
        // un compteur qui changerait à chaque « voir plus » ne voudrait rien
        // dire. D'où une agrégation SQL séparée plutôt qu'un comptage sur la
        // collection chargée.
        $totaux = $this->filtree($data)
            ->selectRaw('count(*) as total')
            ->selectRaw('sum(case when etat_final = ? then 1 else 0 end) as presentes', [PresenceState::Present->value])
            ->first();

        $total = (int) ($totaux->total ?? 0);
        $presentes = (int) ($totaux->presentes ?? 0);

        return SeanceResource::collection($seances)->additional([
            'stats' => [
                'total' => $total,
                'present' => $presentes,
                'absent' => $total - $presentes,
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function filtree(array $data): Builder
    {
        return Seance::query()
            ->when($data['enseignant_id'] ?? null, fn ($q, $v) => $q->where('enseignant_id', $v))
            ->when($data['semaine_id'] ?? null, fn ($q, $v) => $q->where('semaine_id', $v))
            ->when($data['salle_id'] ?? null, fn ($q, $v) => $q->where('salle_id', $v))
            ->when($data['matiere_id'] ?? null, fn ($q, $v) => $q->whereHas('courseTemplate', fn ($q2) => $q2->where('matiere_id', $v)));
    }
}
