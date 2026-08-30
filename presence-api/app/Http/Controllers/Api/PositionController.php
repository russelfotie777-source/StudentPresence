<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Seance;
use Illuminate\Http\Request;

class PositionController extends Controller
{
    /**
     * Le délégué envoie sa position GPS pour une séance — sert de référence
     * pour le pointage des étudiants (voir PresenceController::checkIn).
     * Upsert par seance_id, comme position.php dans l'ancienne app.
     */
    public function store(Request $request, Seance $seance)
    {
        $user = $request->user();

        if ($user->effectiveRole() !== UserRole::Delegue || $seance->salle_id !== $user->salle_id) {
            abort(403);
        }

        $data = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $position = $seance->position()->updateOrCreate([], [
            'delegue_id' => $user->id,
            'latitude' => $data['latitude'],
            'longitude' => $data['longitude'],
            'date_creation' => now(),
        ]);

        return response()->json($position, 201);
    }
}
