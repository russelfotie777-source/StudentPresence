<?php

namespace App\Services;

use App\Enums\PlanningUpdateScope;
use App\Enums\Weekday;
use App\Models\CourseTemplate;
use App\Models\Seance;
use App\Models\Semaine;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Les écritures du planning partagées par le back-office et l'assistant :
 * retoucher ou annuler une séance, supprimer un cours avec ses séances à
 * venir. Une seule règle, quel que soit le chemin qui y mène.
 */
class RetouchesPlanning
{
    public function __construct(private DetecteurConflits $conflits) {}

    /**
     * Modifie une occurrence seule ou propage les changements aux occurrences
     * encore modifiables du même cours. Les séances historiques restent telles
     * qu'elles ont réellement eu lieu.
     *
     * @param  array{date_seance?: string, jour?: string, heure_debut?: string, heure_fin?: string, enseignant_id?: int, salle_id?: int}  $data
     */
    public function modifierSelonPortee(
        Seance $seance,
        array $data,
        PlanningUpdateScope $portee,
    ): PlanningUpdateResult {
        if ($portee === PlanningUpdateScope::Occurrence) {
            return new PlanningUpdateResult($this->modifier($seance, $data), 1, 0);
        }

        return $this->modifierSerie($seance, $data, $portee);
    }

    /**
     * Retouche d'une occurrence : horaires, jour (dans la même semaine ou
     * une autre), enseignant, salle. Refusée dès que la séance a été tenue —
     * ses présences et ses heures payées sont figées.
     *
     * @param  array{date_seance?: string, heure_debut?: string, heure_fin?: string, enseignant_id?: int, salle_id?: int}  $data  déjà validé
     */
    public function modifier(Seance $seance, array $data): Seance
    {
        $this->assertModifiable($seance);

        $date = Carbon::parse($data['date_seance'] ?? $seance->date_seance->toDateString());
        $debut = $data['heure_debut'] ?? substr($seance->heure_debut, 0, 5);
        $fin = $data['heure_fin'] ?? substr($seance->heure_fin, 0, 5);

        if ($fin <= $debut) {
            throw ValidationException::withMessages(['heure_fin' => ["L'heure de fin doit être après l'heure de début."]]);
        }

        $semaine = Semaine::couvrant($date);

        if (! $semaine) {
            throw ValidationException::withMessages(['date_seance' => ['Aucune semaine du semestre ne couvre cette date.']]);
        }

        $creneau = [
            'salle_id' => (int) ($data['salle_id'] ?? $seance->salle_id),
            'groupe' => $seance->groupe,
            'enseignant_id' => (int) ($data['enseignant_id'] ?? $seance->enseignant_id),
            'date_seance' => $date->toDateString(),
            'semaine_id' => $semaine->id,
            'jour' => Weekday::fromCarbon($date)->value,
            'heure_debut' => $debut,
            'heure_fin' => $fin,
        ];

        if ($conflit = $this->conflits->pour($creneau, $seance->id)) {
            throw ValidationException::withMessages(['creneau' => [$conflit]]);
        }

        $seance->update($creneau);

        return $seance->fresh(['salle', 'enseignant', 'courseTemplate.matiere']);
    }

    /**
     * @param  array{jour?: string, heure_debut?: string, heure_fin?: string, enseignant_id?: int, salle_id?: int}  $data
     */
    private function modifierSerie(
        Seance $seance,
        array $data,
        PlanningUpdateScope $portee,
    ): PlanningUpdateResult {
        $this->assertModifiable($seance);

        if (! $seance->course_template_id) {
            throw ValidationException::withMessages([
                'portee' => ["Cette séance n'appartient pas à un cours récurrent."],
            ]);
        }

        return DB::transaction(function () use ($seance, $data, $portee) {
            $cours = CourseTemplate::query()->lockForUpdate()->findOrFail($seance->course_template_id);

            $seances = Seance::query()
                ->where('course_template_id', $cours->id)
                ->when(
                    $portee === PlanningUpdateScope::Following,
                    fn ($query) => $query->whereDate('date_seance', '>=', $seance->date_seance->toDateString()),
                )
                ->with('semaine')
                ->withCount('presences')
                ->orderBy('date_seance')
                ->lockForUpdate()
                ->get();

            $aModifier = $seances->filter(fn (Seance $occurrence) => $this->estModifiable($occurrence))->values();

            if (! $aModifier->contains('id', $seance->id)) {
                throw ValidationException::withMessages([
                    'seance' => ["La séance choisie n'est plus modifiable."],
                ]);
            }

            $jour = isset($data['jour'])
                ? Weekday::from($data['jour'])
                : ($cours->jour instanceof Weekday ? $cours->jour : Weekday::from($cours->jour));
            $debut = $data['heure_debut'] ?? substr($seance->heure_debut, 0, 5);
            $fin = $data['heure_fin'] ?? substr($seance->heure_fin, 0, 5);

            if ($fin <= $debut) {
                throw ValidationException::withMessages(['heure_fin' => ["L'heure de fin doit être après l'heure de début."]]);
            }

            $creneaux = $aModifier->map(function (Seance $occurrence) use ($cours, $data, $jour, $debut, $fin) {
                $semaine = $occurrence->semaine ?? Semaine::couvrant($occurrence->date_seance);

                if (! $semaine) {
                    throw ValidationException::withMessages([
                        'seance' => ["La séance du {$occurrence->date_seance->format('d/m/Y')} n'appartient à aucune semaine du semestre."],
                    ]);
                }

                $date = $semaine->date_debut->clone()->addDays($jour->iso() - 1);

                if ($date->lt($cours->date_debut) || $date->gt($cours->date_fin)) {
                    throw ValidationException::withMessages([
                        'jour' => ["Le {$jour->value} de la semaine S{$semaine->numero} tombe hors de la période du cours."],
                    ]);
                }

                return [
                    'seance' => $occurrence,
                    'semaine_numero' => $semaine->numero,
                    'attributes' => [
                        'salle_id' => (int) ($data['salle_id'] ?? $occurrence->salle_id),
                        'groupe' => $occurrence->groupe,
                        'enseignant_id' => (int) ($data['enseignant_id'] ?? $occurrence->enseignant_id),
                        'date_seance' => $date->toDateString(),
                        'semaine_id' => $semaine->id,
                        'jour' => $jour->value,
                        'heure_debut' => $debut,
                        'heure_fin' => $fin,
                    ],
                ];
            });

            $this->assertCreneauxCompatibles($creneaux);
            $idsModifies = $aModifier->pluck('id')->all();

            foreach ($creneaux as $creneau) {
                if ($conflit = $this->conflits->pour($creneau['attributes'], $idsModifies)) {
                    throw ValidationException::withMessages([
                        'creneau' => ["S{$creneau['semaine_numero']} : {$conflit}"],
                    ]);
                }
            }

            foreach ($creneaux as $creneau) {
                $creneau['seance']->update($creneau['attributes']);
            }

            $changementsCours = [
                'jour' => $jour->value,
                'heure_debut' => $debut,
                'heure_fin' => $fin,
            ];

            foreach (['enseignant_id', 'salle_id'] as $champ) {
                if (array_key_exists($champ, $data)) {
                    $changementsCours[$champ] = $data[$champ];
                }
            }

            if ($portee === PlanningUpdateScope::Following) {
                $selection = $creneaux->first(fn ($creneau) => $creneau['seance']->id === $seance->id);
                $changementsCours['date_debut'] = $selection['attributes']['date_seance'];
            }

            $cours->update($changementsCours);

            return new PlanningUpdateResult(
                $seance->fresh(['salle', 'enseignant', 'courseTemplate.matiere']),
                $aModifier->count(),
                $seances->count() - $aModifier->count(),
            );
        });
    }

    /**
     * @param  Collection<int, array{seance: Seance, semaine_numero: int, attributes: array<string, mixed>}>  $creneaux
     */
    private function assertCreneauxCompatibles(Collection $creneaux): void
    {
        $liste = $creneaux->values();

        for ($i = 0; $i < $liste->count(); $i++) {
            for ($j = $i + 1; $j < $liste->count(); $j++) {
                $a = $liste[$i]['attributes'];
                $b = $liste[$j]['attributes'];
                $chevauche = $a['date_seance'] === $b['date_seance']
                    && $a['heure_debut'] < $b['heure_fin']
                    && $a['heure_fin'] > $b['heure_debut'];
                $memeSalleEtGroupe = $a['salle_id'] === $b['salle_id'] && $a['groupe'] === $b['groupe'];
                $memeEnseignant = $a['enseignant_id'] === $b['enseignant_id'];

                if ($chevauche && ($memeSalleEtGroupe || $memeEnseignant)) {
                    throw ValidationException::withMessages([
                        'creneau' => ['La modification placerait deux séances de la série sur le même créneau.'],
                    ]);
                }
            }
        }
    }

    private function estModifiable(Seance $seance): bool
    {
        return ! $seance->is_past
            && $seance->etat_delegue === null
            && $seance->etat_prof === null
            && ! $seance->presences_locked
            && ($seance->presences_count ?? $seance->presences()->count()) === 0;
    }

    public function annuler(Seance $seance): void
    {
        $this->assertModifiable($seance);

        DB::transaction(function () use ($seance) {
            $cours = $seance->courseTemplate;
            $seance->delete();

            // Un cours ponctuel n'existe que pour porter sa séance : l'annuler
            // ne doit pas laisser un cours vide dans la liste.
            if ($cours && $cours->date_debut->equalTo($cours->date_fin) && ! $cours->seances()->exists()) {
                $cours->delete();
            }
        });
    }

    /**
     * Supprimer un cours retire aussi ses séances à venir. Celles déjà
     * tenues (ou avec des présences) restent : historique et paie.
     * Renvoie le nombre de séances supprimées.
     */
    public function supprimerCours(CourseTemplate $cours): int
    {
        return DB::transaction(function () use ($cours) {
            $aVenir = $cours->seances()
                ->whereDate('date_seance', '>=', now()->toDateString())
                ->whereNull('etat_delegue')
                ->whereNull('etat_prof')
                ->where('presences_locked', false)
                ->whereDoesntHave('presences');

            $nombre = (clone $aVenir)->count();
            $aVenir->delete();
            $cours->delete();

            return $nombre;
        });
    }

    public function assertModifiable(Seance $seance): void
    {
        $tenue = $seance->is_past
            || $seance->etat_delegue !== null
            || $seance->etat_prof !== null
            || $seance->presences_locked
            || $seance->presences()->exists();

        if ($tenue) {
            throw ValidationException::withMessages([
                'seance' => ['Cette séance est passée, a déjà été tenue ou a des présences enregistrées : elle ne peut plus être modifiée ni supprimée.'],
            ]);
        }
    }
}
