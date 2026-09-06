<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Seance;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

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
            'accuracy' => ['required', 'numeric', 'min:0'],
        ]);

        // Ce point sert de référence à toute la classe : une mesure trop
        // incertaine décalerait le périmètre pour tout le monde, sans que
        // personne ne puisse s'en rendre compte.
        $maxAccuracy = config('presence.max_position_accuracy_meters');

        if ($data['accuracy'] > $maxAccuracy) {
            throw ValidationException::withMessages([
                'accuracy' => [
                    'Position trop imprécise pour servir de référence ('.round($data['accuracy'])."m, max {$maxAccuracy}m). ".
                    'Rapprochez-vous d\'une fenêtre et patientez quelques secondes, le temps que le GPS se stabilise.',
                ],
            ]);
        }

        $position = $seance->position()->updateOrCreate([], [
            'delegue_id' => $user->id,
            'latitude' => $data['latitude'],
            'longitude' => $data['longitude'],
            'precision_metres' => (int) round($data['accuracy']),
            'date_creation' => now(),
        ]);

        return response()->json($position, 201);
    }
}
