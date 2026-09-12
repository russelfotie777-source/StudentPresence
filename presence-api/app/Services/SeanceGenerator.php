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
    public function __construct(private DetecteurConflits $conflits) {}

    public function generate(CourseTemplate $template): SeanceGenerationResult
    {
        $weekday = $template->jour instanceof Weekday ? $template->jour : Weekday::from($template->jour);
        $dayOffset = $weekday->iso() - 1;

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
                $skipped->push([
                    'semaine_id' => $semaine->id,
                    'numero' => $semaine->numero,
                    'date' => $dateSeance->toDateString(),
                    'reason' => 'Séance déjà générée pour cette semaine.',
                ]);

                continue;
            }

            $conflict = $this->conflits->pour([
                'salle_id' => $template->salle_id,
                'groupe' => $template->groupe,
                'enseignant_id' => $template->enseignant_id,
                'date_seance' => $dateSeance->toDateString(),
                'semaine_id' => $semaine->id,
                'jour' => $weekday->value,
                'heure_debut' => $template->heure_debut,
                'heure_fin' => $template->heure_fin,
            ]);

            if ($conflict) {
                $skipped->push([
                    'semaine_id' => $semaine->id,
                    'numero' => $semaine->numero,
                    'date' => $dateSeance->toDateString(),
                    'reason' => $conflict,
                ]);

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
}
