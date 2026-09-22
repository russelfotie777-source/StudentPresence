<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Seance;
use App\Services\RappelsPointage;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PositionController extends Controller
{
    /**
     * Le délégué envoie sa position GPS pour une séance — sert de référence
     * pour le pointage des étudiants (voir PresenceController::checkIn).
     * Upsert par seance_id, comme position.php dans l'ancienne app.
     */
    public function store(Request $request, Seance $seance, RappelsPointage $rappels)
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

        // Ce point sert de référence à toute la classe. Son incertitude
        // n'est pas un motif de refus — elle élargit le périmètre des
        // étudiants d'autant (PresenceController::checkIn) — sauf au-delà
        // du plafond, où la mesure vient de l'adresse IP et non du téléphone.
        $maxAccuracy = config('presence.max_position_accuracy_meters');

        if ($data['accuracy'] > $maxAccuracy) {
            throw ValidationException::withMessages([
                'accuracy' => [
                    'Votre téléphone ne trouve pas sa position (à '.round($data['accuracy']).' m près). '.
                    'Activez la localisation dans ses réglages, puis réessayez.',
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

        // La position ouvre le pointage à toute la classe : c'est le moment de
        // prévenir ceux qui ne sont pas encore pointés (une seule fois).
        $rappels->annoncerOuverture($seance->fresh(['salle', 'courseTemplate.matiere']));

        return response()->json($position, 201);
    }
}
