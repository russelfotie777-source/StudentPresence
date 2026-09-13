<?php

namespace App\Services;

use App\Enums\PresenceState;
use App\Enums\UserRole;
use App\Models\Salle;
use App\Models\Seance;
use App\Models\Semaine;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Feuille de présence d'une salle sur une semaine : les étudiants en lignes,
 * les séances en colonnes, et dans chaque case l'état retenu. C'est la même
 * matière que voit l'admin dans la grille et qu'imprime le PDF officiel —
 * un seul endroit décide de ce qu'une case vaut.
 */
class FeuilleDePresence
{
    /**
     * @return array{seances: Collection<int, Seance>, etudiants: Collection<int, User>, delegue: User|null}
     */
    public function pour(Salle $salle, Semaine $semaine): array
    {
        $seances = Seance::query()
            ->with(['courseTemplate.matiere', 'enseignant'])
            ->withCount('presences')
            ->where('salle_id', $salle->id)
            ->whereBetween('date_seance', [$semaine->date_debut->toDateString(), $semaine->date_fin->toDateString()])
            ->orderBy('date_seance')
            ->orderBy('heure_debut')
            ->get();

        $etudiants = User::query()
            ->whereIn('role', [UserRole::Etudiant->value, UserRole::Delegue->value])
            ->where('salle_id', $salle->id)
            ->whereIn('formation', $salle->formationsAccueillies())
            ->with(['presences' => fn ($q) => $q->whereIn('seance_id', $seances->pluck('id'))])
            ->orderBy('name')
            ->get();

        return [
            'seances' => $seances,
            'etudiants' => $etudiants,
            'delegue' => $etudiants->first(fn (User $u) => $u->role === UserRole::Delegue),
        ];
    }

    /**
     * Situation d'une séance vis-à-vis de l'appel : `tenue` (appel validé ou
     * au moins un pointage), `en_cours`, `a_venir`, ou `non_validee` — passée
     * sans qu'aucun appel n'ait été fait, auquel cas on n'invente rien.
     */
    public function statut(Seance $seance): string
    {
        if ($this->tenue($seance)) {
            return 'tenue';
        }
        if ($seance->is_active) {
            return 'en_cours';
        }

        return $seance->is_past ? 'non_validee' : 'a_venir';
    }

    /**
     * Ce qu'une case vaut pour un étudiant : son pointage s'il existe ;
     * sinon absent si le délégué a validé l'appel sans lui ; sinon rien —
     * une séance à venir ou un appel jamais validé ne dit rien de lui.
     */
    public function marque(Seance $seance, User $etudiant): ?PresenceState
    {
        $presence = $etudiant->presences->firstWhere('seance_id', $seance->id);

        if ($presence) {
            return $presence->etat;
        }

        return $seance->presences_locked ? PresenceState::Absent : null;
    }

    private function tenue(Seance $seance): bool
    {
        return $seance->presences_locked
            || ($seance->presences_count ?? 0) > 0
            || $seance->etat_final === PresenceState::Present;
    }
}
