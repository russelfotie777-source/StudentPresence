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
