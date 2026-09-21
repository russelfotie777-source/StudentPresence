<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Seance;
use App\Notifications\PositionAttendue;
use App\Services\RappelsPointage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * « Faire signe au délégué » : l'étudiant est en salle, l'heure est venue,
 * mais le pointage n'ouvre pas tant que la position n'est pas envoyée. Le
 * délégué reçoit une notification — une seule par séance, quel que soit
 * le nombre d'étudiants qui appuient.
 */
class PositionAttendueController extends Controller
{
    public function __invoke(Request $request, Seance $seance, RappelsPointage $rappels): JsonResponse
    {
        $etudiant = $request->user();
        abort_unless($etudiant->role === UserRole::Etudiant && $etudiant->salle_id === $seance->salle_id, 403);

        if (! $seance->is_active) {
            throw ValidationException::withMessages(['seance' => ["Le pointage de cette séance n'est pas ouvert."]]);
        }
        if ($seance->position()->exists()) {
            throw ValidationException::withMessages(['position' => ['La position est déjà là : vous pouvez pointer.']]);
        }

        $seance->loadMissing(['salle', 'courseTemplate.matiere']);
        $delegues = $rappels->delegues($seance);

        $dejaPrevenu = $delegues->contains(fn ($d) => $d->notifications()
            ->where('type', PositionAttendue::class)
            ->where('data->seance_id', $seance->id)
            ->exists());

        if (! $dejaPrevenu) {
            foreach ($delegues as $delegue) {
                $delegue->notify(new PositionAttendue($seance, $etudiant));
            }
        }

        return response()->json([
            'deja_prevenu' => $dejaPrevenu,
            // Le prénom, pour le dire comme on le dirait : « Anastasie est prévenue ».
            'delegues' => $delegues->map(fn ($d) => self::prenom($d->name))->values(),
        ]);
    }

    /** « MBALLA Étienne » ou « Étienne Mballa » → « Étienne » : le mot qui n'est pas tout en capitales, sinon le premier. */
    private static function prenom(string $nom): string
    {
        $mots = preg_split('/\s+/', trim($nom)) ?: [];
        $prenom = collect($mots)->first(fn ($m) => mb_strtoupper($m) !== $m) ?? ($mots[0] ?? '');

        return mb_convert_case(mb_strtolower($prenom), MB_CASE_TITLE);
    }
}
