<?php

namespace App\Services;

use App\Enums\StatutCompte;
use App\Enums\UserRole;
use App\Models\Seance;
use App\Models\User;
use App\Notifications\RappelPointage;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Trois rappels par séance, chacun envoyé une seule fois :
 *
 *  1. au délégué, à l'ouverture de la fenêtre de pointage, tant qu'il n'a
 *     pas envoyé la position — sans elle, personne ne peut pointer ;
 *  2. aux étudiants qui n'ont pas pointé, dès que la position est là
 *     (déclenché par PositionController, rattrapé par le passage planifié) ;
 *  3. aux étudiants qui n'ont toujours pas pointé, peu avant la fermeture.
 *
 * Tout se calcule à l'heure de Douala (fuseau de l'application), jamais à
 * celle du téléphone.
 */
class RappelsPointage
{
    /**
     * Un passage du planificateur : les trois rappels pour l'instant présent.
     *
     * @return array{delegue: int, ouverture: int, derniere_chance: int}
     */
    public function tick(?CarbonInterface $maintenant = null): array
    {
        $maintenant ??= now();

        return [
            'delegue' => $this->rappelerDelegues($maintenant),
            'ouverture' => $this->seancesDuJour($maintenant)
                ->whereNull('rappel_ouverture_at')
                ->whereHas('position')
                ->get()
                ->filter(fn (Seance $s) => $this->fenetreOuverte($s, $maintenant))
                ->sum(fn (Seance $s) => $this->annoncerOuverture($s, $maintenant)),
            'derniere_chance' => $this->dernieresChances($maintenant),
        ];
    }

    /** Nombre de délégués prévenus. */
    public function rappelerDelegues(CarbonInterface $maintenant): int
    {
        $avance = (int) config('presence.rappels.delegue_avant_debut_minutes', 15);

        return $this->seancesDuJour($maintenant)
            ->whereNull('rappel_delegue_at')
            ->whereDoesntHave('position')
            ->get()
            ->filter(fn (Seance $s) => $maintenant->gte($s->debutPrevu()->subMinutes($avance))
                && $maintenant->lt($s->finPrevue()))
            ->sum(function (Seance $s) use ($maintenant) {
                $delegues = $this->delegues($s);
                $this->envoyer($delegues, $s, RappelPointage::DELEGUE);
                $s->forceFill(['rappel_delegue_at' => $maintenant])->save();

                return $delegues->count();
            });
    }

    /**
     * La position vient d'arriver (ou était déjà là) : prévenir ceux qui
     * n'ont pas pointé. Nombre d'étudiants prévenus, 0 si déjà fait.
     */
    public function annoncerOuverture(Seance $seance, ?CarbonInterface $maintenant = null): int
    {
        $maintenant ??= now();

        if ($seance->rappel_ouverture_at || ! $this->fenetreOuverte($seance, $maintenant)) {
            return 0;
        }

        $etudiants = $this->etudiantsSansPointage($seance);
        $this->envoyer($etudiants, $seance, RappelPointage::OUVERTURE);
        $seance->forceFill(['rappel_ouverture_at' => $maintenant])->save();

        return $etudiants->count();
    }

    /** Nombre d'étudiants relancés avant la fermeture. */
    public function dernieresChances(CarbonInterface $maintenant): int
    {
        $avance = (int) config('presence.rappels.derniere_chance_avant_fermeture_minutes', 15);

        return $this->seancesDuJour($maintenant)
            ->whereNull('rappel_cloture_at')
            ->whereHas('position')
            ->get()
            ->filter(function (Seance $s) use ($maintenant, $avance) {
                $fermeture = $s->finPrevue()->addMinutes(15);

                return $maintenant->gte($fermeture->clone()->subMinutes($avance)) && $maintenant->lt($fermeture);
            })
            ->sum(function (Seance $s) use ($maintenant) {
                $etudiants = $this->etudiantsSansPointage($s);
                $this->envoyer($etudiants, $s, RappelPointage::DERNIERE_CHANCE);
                $s->forceFill(['rappel_cloture_at' => $maintenant])->save();

                return $etudiants->count();
            });
    }

    private function seancesDuJour(CarbonInterface $maintenant): Builder
    {
        return Seance::query()
            ->with(['salle', 'courseTemplate.matiere'])
            ->whereDate('date_seance', $maintenant->toDateString());
    }

    /** Fenêtre de pointage : ±15 min autour de l'horaire prévu (Seance::isActive). */
    private function fenetreOuverte(Seance $seance, CarbonInterface $maintenant): bool
    {
        return $maintenant->between(
            $seance->debutPrevu()->subMinutes(15),
            $seance->finPrevue()->addMinutes(15),
        );
    }

    /**
     * Délégué titulaire de la salle, et tout étudiant promu délégué pour le
     * moment (remplaçant désigné) — c'est lui qui enverra la position.
     *
     * @return Collection<int, User>
     */
    public function delegues(Seance $seance): Collection
    {
        return User::query()
            ->where('salle_id', $seance->salle_id)
            ->where('statut_compte', StatutCompte::Actif->value)
            ->where(fn ($q) => $q
                ->where('role', UserRole::Delegue->value)
                ->orWhereHas('promotionsRecues', fn ($q2) => $q2->where('date_fin', '>', now())))
            ->get();
    }

    /**
     * Étudiants de la salle qui n'ont pas encore pointé. Un compte restreint
     * ou bloqué ne peut pas pointer : lui rappeler de le faire n'aurait
     * aucun sens.
     *
     * @return Collection<int, User>
     */
    private function etudiantsSansPointage(Seance $seance): Collection
    {
        return User::query()
            ->where('salle_id', $seance->salle_id)
            ->where('role', UserRole::Etudiant->value)
            ->where('statut_compte', StatutCompte::Actif->value)
            ->whereDoesntHave('presences', fn ($q) => $q->where('seance_id', $seance->id))
            ->get();
    }

    /**
     * @param  Collection<int, User>  $destinataires
     */
    private function envoyer(Collection $destinataires, Seance $seance, string $sousType): void
    {
        foreach ($destinataires as $destinataire) {
            try {
                $destinataire->notify(new RappelPointage($seance, $sousType));
            } catch (\Throwable $e) {
                // Un service push injoignable ne doit ni bloquer la position du
                // délégué ni priver les autres de leur rappel : on note et on continue.
                report($e);
            }
        }
    }
}
