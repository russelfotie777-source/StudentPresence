<?php

namespace App\Services;

use App\Enums\Weekday;
use App\Models\CourseTemplate;
use App\Models\Seance;
use App\Models\Semaine;

/**
 * Matérialise les `seances` d'un `course_template` récurrent, une par
 * semaine couverte par sa plage [date_debut, date_fin]. C'est la
 * fonctionnalité que l'ancienne app n'a jamais eue : là-bas, chaque séance
 * était saisie une par une à la main (voir superprotect/add_seance.php).
 */
class SeanceGenerator
{
    public function generate(CourseTemplate $template): SeanceGenerationResult
    {
        $weekday = $template->jour instanceof Weekday ? $template->jour : Weekday::from($template->jour);
        $dayOffset = array_search($weekday, Weekday::cases(), true);

        $semaines = Semaine::query()
            ->where('date_fin', '>=', $template->date_debut)
            ->where('date_debut', '<=', $template->date_fin)
            ->orderBy('numero')
            ->get();

        $created = collect();
        $skipped = collect();

        foreach ($semaines as $semaine) {
            $dateSeance = $semaine->date_debut->clone()->addDays($dayOffset);

            if ($dateSeance->lt($template->date_debut) || $dateSeance->gt($template->date_fin)) {
                continue; // Le jour choisi tombe hors de la plage de validité du template cette semaine-là.
            }

            $exists = Seance::query()
                ->where('course_template_id', $template->id)
                ->where('semaine_id', $semaine->id)
                ->exists();

            if ($exists) {
                $skipped->push(['semaine_id' => $semaine->id, 'reason' => 'Séance déjà générée pour cette semaine.']);

                continue;
            }

            $conflict = $this->findConflict($template, $semaine);

            if ($conflict) {
                $skipped->push(['semaine_id' => $semaine->id, 'reason' => $conflict]);

                continue;
            }

            $created->push(Seance::create([
                'course_template_id' => $template->id,
                'semaine_id' => $semaine->id,
                'salle_id' => $template->salle_id,
                'enseignant_id' => $template->enseignant_id,
                'groupe' => $template->groupe,
                'date_seance' => $dateSeance->toDateString(),
                'jour' => $weekday->value,
                'heure_debut' => $template->heure_debut,
                'heure_fin' => $template->heure_fin,
            ]));
        }

        return new SeanceGenerationResult($created, $skipped);
    }

    /**
     * Étend la vérification de conflit de l'ancienne app (salle+semaine+jour+
     * groupe) pour couvrir aussi le double-booking d'un même enseignant sur
     * deux salles au même horaire — un trou de sécurité réel de add_seance.php.
     */
    private function findConflict(CourseTemplate $template, Semaine $semaine): ?string
    {
        $overlaps = fn ($query) => $query
            ->where('semaine_id', $semaine->id)
            ->where('jour', $template->jour instanceof Weekday ? $template->jour->value : $template->jour)
            ->where('heure_debut', '<', $template->heure_fin)
            ->where('heure_fin', '>', $template->heure_debut);

        $salleConflict = Seance::query()
            ->where('salle_id', $template->salle_id)
            ->where('groupe', $template->groupe)
            ->tap($overlaps)
            ->exists();

        if ($salleConflict) {
            return "Conflit de salle pour le groupe {$template->groupe} sur ce créneau.";
        }

        $enseignantConflict = Seance::query()
            ->where('enseignant_id', $template->enseignant_id)
            ->tap($overlaps)
            ->exists();

        if ($enseignantConflict) {
            return "L'enseignant a déjà une séance sur ce créneau (autre salle).";
        }

        return null;
    }
}
