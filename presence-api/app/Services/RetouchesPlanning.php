<?php

namespace App\Services;

use App\Enums\Weekday;
use App\Models\CourseTemplate;
use App\Models\Seance;
use App\Models\Semaine;
use Carbon\Carbon;
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
     * Modifier un cours, c'est modifier la règle : jour, horaires,
     * enseignant, salle ou matière se répercutent sur toutes ses séances à
     * venir non tenues. Chaque séance est revérifiée pour les conflits ;
     * celle qui en a reste telle quelle et est signalée, les autres suivent.
     * Les séances tenues ne bougent pas : leur historique et leur paie
     * sont figés.
     *
     * @param  array{jour?: string, heure_debut?: string, heure_fin?: string, enseignant_id?: int, salle_id?: int, matiere_id?: int}  $data  déjà validé
     * @return array{modifiees: int, ignorees: list<array{seance_id: int, date: string, reason: string}>}
     */
    public function modifierCours(CourseTemplate $cours, array $data): array
    {
        return DB::transaction(function () use ($cours, $data) {
            $cours->update(array_intersect_key($data, array_flip(['jour', 'heure_debut', 'heure_fin', 'enseignant_id', 'salle_id', 'matiere_id', 'groupe'])));
            $cours->refresh();

            $jour = $cours->jour instanceof Weekday ? $cours->jour : Weekday::from($cours->jour);
            $modifiees = 0;
            $ignorees = [];

            foreach ($this->seancesAVenir($cours)->with('semaine')->orderBy('date_seance')->get() as $seance) {
                // Le jour du cours a changé : la séance se replace ce jour-là, dans sa semaine.
                $date = $seance->semaine
                    ? $seance->semaine->date_debut->clone()->addDays($jour->iso() - 1)
                    : $seance->date_seance;

                $creneau = [
                    'salle_id' => $cours->salle_id,
                    'groupe' => $cours->groupe,
                    'enseignant_id' => $cours->enseignant_id,
                    'date_seance' => $date->toDateString(),
                    'semaine_id' => $seance->semaine_id,
                    'jour' => $jour->value,
                    'heure_debut' => substr($cours->heure_debut, 0, 5),
                    'heure_fin' => substr($cours->heure_fin, 0, 5),
                ];

                if ($conflit = $this->conflits->pour($creneau, $seance->id)) {
                    $ignorees[] = ['seance_id' => $seance->id, 'date' => $seance->date_seance->toDateString(), 'reason' => $conflit];

                    continue;
                }

                $seance->update($creneau);
                $modifiees++;
            }

            return ['modifiees' => $modifiees, 'ignorees' => $ignorees];
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
            $aVenir = $this->seancesAVenir($cours);

            $nombre = (clone $aVenir)->count();
            $aVenir->delete();
            $cours->delete();

            return $nombre;
        });
    }

    /** Les séances d'un cours qui restent à tenir : à partir d'aujourd'hui, sans appel ni présence. */
    private function seancesAVenir(CourseTemplate $cours)
    {
        return $cours->seances()
            ->whereDate('date_seance', '>=', now()->toDateString())
            ->whereNull('etat_delegue')
            ->whereNull('etat_prof')
            ->where('presences_locked', false)
            ->whereDoesntHave('presences');
    }

    public function assertModifiable(Seance $seance): void
    {
        $tenue = $seance->etat_delegue !== null
            || $seance->etat_prof !== null
            || $seance->presences_locked
            || $seance->presences()->exists();

        if ($tenue) {
            throw ValidationException::withMessages([
                'seance' => ['Cette séance a déjà été tenue ou a des présences enregistrées : elle ne peut plus être modifiée ni supprimée.'],
            ]);
        }
    }
}
