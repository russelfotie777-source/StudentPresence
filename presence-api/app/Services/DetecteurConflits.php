<?php

namespace App\Services;

use App\Models\Seance;
use Illuminate\Database\Eloquent\Builder;

/**
 * Une salle (pour un même groupe) ou un enseignant ne peuvent pas être pris
 * deux fois sur le même créneau. Partagé entre la génération des séances
 * d'un cours récurrent et la modification d'une séance isolée, pour que les
 * deux chemins appliquent exactement la même règle.
 */
class DetecteurConflits
{
    /**
     * Motif lisible du conflit, ou null si le créneau est libre.
     *
     * @param  array{salle_id:int, groupe:string, enseignant_id:int, date_seance:string, heure_debut:string, heure_fin:string, semaine_id?:int, jour?:string}  $creneau
     * @param  int|null  $ignorerSeanceId  la séance en cours de modification, qui ne doit pas entrer en conflit avec elle-même
     */
    public function pour(array $creneau, ?int $ignorerSeanceId = null): ?string
    {
        // Même jour = même date, ou même jour de la même semaine : les deux
        // coïncident pour toute séance générée, mais une séance saisie à la
        // main peut n'avoir que l'un des deux de renseigné correctement.
        $memeJour = fn (Builder $query) => $query
            ->whereDate('date_seance', $creneau['date_seance'])
            ->when(isset($creneau['semaine_id'], $creneau['jour']), fn ($q) => $q
                ->orWhere(fn ($q2) => $q2
                    ->where('semaine_id', $creneau['semaine_id'])
                    ->where('jour', $creneau['jour'])));

        $chevauche = fn (Builder $query) => $query
            ->where($memeJour)
            ->where('heure_debut', '<', $creneau['heure_fin'])
            ->where('heure_fin', '>', $creneau['heure_debut'])
            ->when($ignorerSeanceId, fn ($q, $id) => $q->whereKeyNot($id));

        $salleOccupee = Seance::query()
            ->where('salle_id', $creneau['salle_id'])
            ->where('groupe', $creneau['groupe'])
            ->tap($chevauche)
            ->with('courseTemplate.matiere')
            ->first();

        if ($salleOccupee) {
            $matiere = $salleOccupee->courseTemplate?->matiere?->nom ?? 'autre cours';

            return sprintf(
                'Conflit de salle pour le groupe %s : %s occupe déjà %s–%s.',
                $creneau['groupe'],
                $matiere,
                substr($salleOccupee->heure_debut, 0, 5),
                substr($salleOccupee->heure_fin, 0, 5),
            );
        }

        $enseignantPris = Seance::query()
            ->where('enseignant_id', $creneau['enseignant_id'])
            ->tap($chevauche)
            ->with('salle')
            ->first();

        if ($enseignantPris) {
            return sprintf(
                "L'enseignant a déjà une séance sur ce créneau (%s, %s–%s).",
                $enseignantPris->salle?->nom ?? 'autre salle',
                substr($enseignantPris->heure_debut, 0, 5),
                substr($enseignantPris->heure_fin, 0, 5),
            );
        }

        return null;
    }
}
