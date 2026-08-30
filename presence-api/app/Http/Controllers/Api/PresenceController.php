<?php

namespace App\Http\Controllers\Api;

use App\Enums\FormationType;
use App\Enums\PresenceState;
use App\Enums\PushStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Seance;
use App\Models\User;
use App\Services\GeoDistance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PresenceController extends Controller
{
    /**
     * Pointage GPS d'un étudiant — équivalent du POST de dashEtudiant.php.
     * Vérification serveur unique et autoritaire (contrairement à l'ancienne
     * app, qui avait un double-check côté check_distance.php purement
     * indicatif côté client).
     */
    public function checkIn(Request $request, Seance $seance)
    {
        $user = $request->user();

        if ($user->role !== UserRole::Etudiant) {
            abort(403);
        }

        if ($seance->salle_id !== $user->salle_id) {
            abort(403, "Cette séance n'appartient pas à votre salle.");
        }

        if ($seance->presences_locked) {
            throw ValidationException::withMessages([
                'seance' => ['Les présences de cette séance ont déjà été validées par le délégué.'],
            ]);
        }

        $position = $seance->position;

        if (! $position) {
            throw ValidationException::withMessages([
                'position' => ["Le délégué n'a pas encore activé sa position pour cette séance."],
            ]);
        }

        $data = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $distance = GeoDistance::metersBetween(
            (float) $data['latitude'],
            (float) $data['longitude'],
            (float) $position->latitude,
            (float) $position->longitude,
        );

        $maxDistance = config('presence.max_check_in_distance_meters');

        if ($distance > $maxDistance) {
            throw ValidationException::withMessages([
                'position' => ["Vous êtes trop éloigné du délégué pour marquer votre présence ({$distance}m, max {$maxDistance}m)."],
            ]);
        }

        $presence = $seance->presences()->updateOrCreate(
            ['etudiant_id' => $user->id],
            ['etat' => PresenceState::Present, 'date_marquage' => now()],
        );

        return response()->json(['presence' => $presence, 'distance' => $distance]);
    }

    /**
     * Liste des étudiants de la salle éligibles pour cette séance, avec leur
     * statut de présence actuel — sert à construire l'écran de confirmation
     * du délégué (liste.php dans l'ancienne app).
     */
    public function roster(Request $request, Seance $seance)
    {
        $this->authorizeDelegue($request, $seance);

        return $this->rosterQuery($seance, $request->user())
            ->with(['presences' => fn ($q) => $q->where('seance_id', $seance->id)])
            ->get()
            ->map(fn (User $etudiant) => [
                'id' => $etudiant->id,
                'name' => $etudiant->name,
                'formation' => $etudiant->formation?->value,
                'etat' => $etudiant->presences->first()?->etat?->value,
            ]);
    }

    /**
     * Confirmation finale du roster par le délégué : remet tout le monde à
     * absent puis marque présents les étudiants cochés (le choix du délégué
     * est définitif, il écrase un éventuel auto-pointage étudiant), verrouille
     * la séance, et approuve le push en attente s'il y en a un. Reprend
     * exactement la logique transactionnelle de liste.php.
     */
    public function confirmRoster(Request $request, Seance $seance)
    {
        $user = $this->authorizeDelegue($request, $seance);

        if ($seance->presences_locked) {
            throw ValidationException::withMessages(['seance' => ['Cette séance est déjà verrouillée.']]);
        }

        $push = $seance->pushRequest;

        if (! $push) {
            throw ValidationException::withMessages([
                'seance' => ["L'enseignant n'a pas encore déclaré son effectif présent pour cette séance."],
            ]);
        }

        $data = $request->validate([
            'etudiants' => ['required', 'array'],
            'etudiants.*' => ['integer'],
        ]);

        $confirmedIds = collect($data['etudiants'])->unique()->values();

        if ($confirmedIds->count() > $push->etudiants_presents) {
            throw ValidationException::withMessages([
                'etudiants' => ["Le nombre d'étudiants sélectionnés dépasse l'effectif déclaré par l'enseignant ({$push->etudiants_presents})."],
            ]);
        }

        $roster = $this->rosterQuery($seance, $user)->pluck('id');

        DB::transaction(function () use ($seance, $roster, $confirmedIds, $push) {
            foreach ($roster as $etudiantId) {
                $seance->presences()->updateOrCreate(
                    ['etudiant_id' => $etudiantId],
                    ['etat' => PresenceState::Absent, 'date_marquage' => now()],
                );
            }

            $seance->presences()
                ->whereIn('etudiant_id', $confirmedIds->intersect($roster))
                ->update(['etat' => PresenceState::Present->value, 'date_marquage' => now()]);

            $seance->update(['presences_locked' => true]);

            if ($push->status === PushStatus::Pending) {
                $push->update(['status' => PushStatus::Approved]);
            }
        });

        return response()->json(['seance' => $seance->fresh(['presences'])]);
    }

    /**
     * Reprend le filtre par formation de l'ancienne app : une salle FI
     * accueille aussi les étudiants "migrants" FM, une salle FA n'accueille
     * que des FA.
     */
    private function rosterQuery(Seance $seance, User $delegue)
    {
        $formations = $seance->salle->formation === FormationType::FI
            ? [FormationType::FI->value, FormationType::FM->value]
            : [FormationType::FA->value];

        return User::query()
            ->where('role', UserRole::Etudiant->value)
            ->where('salle_id', $seance->salle_id)
            ->where('niveau_id', $delegue->niveau_id)
            ->whereIn('formation', $formations);
    }

    private function authorizeDelegue(Request $request, Seance $seance): User
    {
        $user = $request->user();

        if ($user->effectiveRole() !== UserRole::Delegue || $seance->salle_id !== $user->salle_id) {
            abort(403);
        }

        return $user;
    }
}
