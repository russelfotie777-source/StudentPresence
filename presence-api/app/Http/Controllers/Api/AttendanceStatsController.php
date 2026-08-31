<?php

namespace App\Http\Controllers\Api;

use App\Enums\PresenceState;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\PresenceEtudiant;
use App\Models\Semaine;
use Illuminate\Http\Request;

class AttendanceStatsController extends Controller
{
    /**
     * Taux de présence personnel de l'étudiant connecté — sur toutes les
     * séances où un statut (présent ou absent) a été enregistré à son nom.
     * Nouveau : n'existait pas dans l'ancienne app.
     */
    public function me(Request $request)
    {
        $user = $request->user();
        abort_unless($user->role === UserRole::Etudiant, 403);

        $total = PresenceEtudiant::where('etudiant_id', $user->id)->count();
        $present = PresenceEtudiant::where('etudiant_id', $user->id)
            ->where('etat', PresenceState::Present->value)
            ->count();

        return response()->json([
            'total_seances' => $total,
            'presences' => $present,
            'taux' => $total > 0 ? round($present / $total * 100) : null,
        ]);
    }

    /**
     * Tendance du taux de présence, semaine par semaine, sur les 8
     * dernières semaines ayant une donnée — alimente le graphique de la
     * page Profil. Nouveau, comme me().
     */
    public function trend(Request $request)
    {
        $user = $request->user();
        abort_unless($user->role === UserRole::Etudiant, 403);

        $rows = PresenceEtudiant::query()
            ->join('seances', 'seances.id', '=', 'presences_etudiants.seance_id')
            ->where('presences_etudiants.etudiant_id', $user->id)
            ->whereNotNull('seances.semaine_id')
            ->selectRaw('seances.semaine_id, COUNT(*) as total, SUM(presences_etudiants.etat = ?) as present', [PresenceState::Present->value])
            ->groupBy('seances.semaine_id')
            ->get()
            ->keyBy('semaine_id');

        $semaines = Semaine::whereIn('id', $rows->keys())->orderBy('numero')->get()->take(-8);

        $trend = $semaines->map(function (Semaine $semaine) use ($rows) {
            $row = $rows->get($semaine->id);
            $total = (int) $row->total;

            return [
                'semaine' => $semaine->numero,
                'label' => 'S'.$semaine->numero,
                'taux' => $total > 0 ? round(((int) $row->present / $total) * 100) : 0,
            ];
        })->values();

        return response()->json($trend);
    }
}
