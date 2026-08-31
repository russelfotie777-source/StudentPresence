<?php

namespace App\Http\Controllers\Api;

use App\Enums\PresenceState;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\PresenceEtudiant;
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
}
