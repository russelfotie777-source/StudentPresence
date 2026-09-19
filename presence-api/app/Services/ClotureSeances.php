<?php

namespace App\Services;

use App\Enums\PresenceState;
use App\Models\Seance;
use Carbon\CarbonInterface;

/**
 * Filet de sécurité de la paie : une séance que le délégué a marquée
 * présente mais dont il n'a pas enregistré la fin réelle resterait sans
 * `fin_reelle` — donc hors du calcul de salaire, qui exige début et fin
 * réels, et sans crédit d'heures. Une fois la fenêtre de pointage fermée,
 * on la clôture à l'heure prévue : c'est ce que l'admin faisait déjà à la
 * main en approuvant une requête enseignant (RequeteController::process).
 *
 * L'heure prévue, pas l'heure du passage : un cours qu'on n'a pas vu finir
 * est réputé avoir fini à l'heure, ni plus ni moins.
 */
class ClotureSeances
{
    /** Nombre de séances clôturées par ce passage. */
    public function tick(?CarbonInterface $maintenant = null): int
    {
        $maintenant ??= now();
        $cloturees = 0;

        Seance::query()
            ->where('etat_delegue', PresenceState::Present->value)
            ->whereNull('fin_reelle')
            ->whereNotNull('date_seance')
            ->whereDate('date_seance', '<=', $maintenant->toDateString())
            ->with('enseignant')
            ->orderBy('id')
            ->chunkById(200, function ($seances) use ($maintenant, &$cloturees) {
                foreach ($seances as $seance) {
                    if ($this->cloturer($seance, $maintenant)) {
                        $cloturees++;
                    }
                }
            });

        return $cloturees;
    }

    private function cloturer(Seance $seance, CarbonInterface $maintenant): bool
    {
        // Tant que la fenêtre est ouverte, le délégué peut encore le faire lui-même.
        if ($maintenant->lte($seance->finPrevue()->addMinutes(15))) {
            return false;
        }

        $seance->update([
            // Un « présent » sans heure d'arrivée (ancienne app) : à l'heure, comme la requête admin.
            'debut_reel' => $seance->debut_reel ?? $seance->heure_debut,
            'fin_reelle' => $seance->heure_fin,
        ]);
        $seance->refresh();
        $seance->crediterQuotaEnseignant();

        return true;
    }
}
