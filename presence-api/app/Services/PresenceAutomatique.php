<?php

namespace App\Services;

use App\Enums\PresenceState;
use App\Enums\UserRole;
use App\Models\Seance;
use App\Models\User;
use Carbon\CarbonInterface;

/**
 * Le privilège « toujours présent » : un étudiant que l'admin a désigné est
 * compté présent à chaque séance de sa salle, sans pointer et quoi que
 * décide le délégué à l'appel. Appliqué à deux moments, pour ne dépendre
 * d'aucun oubli : quand le délégué confirme la liste, et par le
 * planificateur dès qu'une séance a commencé.
 *
 * Seule limite : une présence posée à la main par l'admin (forcee_par_id)
 * reste ce qu'elle est — l'admin a toujours le dernier mot sur sa faveur.
 */
class PresenceAutomatique
{
    /** Nombre de présences posées ou rétablies pour cette séance. */
    public function appliquer(Seance $seance): int
    {
        $privilegies = User::query()
            ->where('salle_id', $seance->salle_id)
            ->whereIn('role', [UserRole::Etudiant->value, UserRole::Delegue->value])
            ->where('presence_automatique', true)
            ->pluck('id');

        $posees = 0;
        foreach ($privilegies as $etudiantId) {
            $presence = $seance->presences()->firstOrNew(['etudiant_id' => $etudiantId]);
            if ($presence->exists && $presence->forcee_par_id !== null) {
                continue;
            }
            if ($presence->exists && $presence->etat === PresenceState::Present) {
                continue;
            }
            $presence->fill([
                'etat' => PresenceState::Present,
                'date_marquage' => now(),
                'automatique' => true,
            ])->save();
            $posees++;
        }

        return $posees;
    }

    /**
     * Un passage du planificateur : toutes les séances du jour déjà
     * commencées. Rejouable : une présence déjà posée n'est pas retouchée.
     */
    public function tick(?CarbonInterface $maintenant = null): int
    {
        $maintenant ??= now();
        $posees = 0;

        Seance::query()
            ->whereDate('date_seance', $maintenant->toDateString())
            ->where('heure_debut', '<=', $maintenant->format('H:i:s'))
            ->whereIn('salle_id', User::query()->where('presence_automatique', true)->whereNotNull('salle_id')->select('salle_id'))
            ->orderBy('id')
            ->each(function (Seance $seance) use (&$posees) {
                $posees += $this->appliquer($seance);
            });

        return $posees;
    }
}
