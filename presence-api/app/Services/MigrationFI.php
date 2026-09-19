<?php

namespace App\Services;

use App\Enums\FormationType;
use App\Enums\RequestStatus;
use App\Enums\UserRole;
use App\Models\DemandeFormation;
use App\Models\Salle;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Ce qu'un étudiant peut faire côté migration FA → FI : sa situation, s'il
 * peut demander, et vers quelles salles. Une seule règle, lue par l'écran
 * de l'app (qui la montre) et par la création d'une demande (qui l'impose).
 */
class MigrationFI
{
    /**
     * @return array<string, mixed>
     */
    public function situation(User $etudiant): array
    {
        $etudiant->loadMissing(['salle', 'niveau', 'filiere.departement']);
        $enAttente = DemandeFormation::with('salleCible.filiere.niveau')
            ->where('etudiant_id', $etudiant->id)
            ->where('statut', RequestStatus::EnAttente)
            ->latest('date_creation')
            ->first();
        $empechement = $this->empechement($etudiant);
        $salles = $empechement === null ? $this->sallesEligibles($etudiant) : collect();

        return [
            'formation' => $etudiant->formation?->value,
            'salle' => $etudiant->salle ? ['id' => $etudiant->salle->id, 'nom' => $etudiant->salle->nom, 'formation' => $etudiant->salle->formation->value] : null,
            'niveau' => $etudiant->niveau?->nom,
            'filiere' => $etudiant->filiere?->nom,
            'departement' => $etudiant->filiere?->departement ? [
                'code' => $etudiant->filiere->departement->code,
                'nom' => $etudiant->filiere->departement->nom,
            ] : null,
            'niveau_max' => (int) config('presence.migration.niveau_max', 2),
            'eligible' => $empechement === null,
            'empechement' => $empechement,
            'salles' => $salles->map(fn (Salle $s) => [
                'id' => $s->id,
                'nom' => $s->nom,
                'filiere' => $s->filiere->nom,
                'niveau' => $s->filiere->niveau->nom,
                'effectif' => $s->etudiants_count,
            ])->values(),
            'demande_en_attente' => $enAttente ? [
                'id' => $enAttente->id,
                'salle_cible' => $enAttente->salleCible ? ['id' => $enAttente->salleCible->id, 'nom' => $enAttente->salleCible->nom] : null,
                'motif' => $enAttente->motif,
                'date_creation' => $enAttente->date_creation?->toIso8601String(),
            ] : null,
        ];
    }

    /**
     * Pourquoi cet étudiant ne peut pas demander à migrer — null s'il le peut.
     * Une demande déjà en attente n'est pas un empêchement : c'est un état,
     * que l'écran montre à la place du formulaire.
     */
    public function empechement(User $etudiant): ?string
    {
        if ($etudiant->role !== UserRole::Etudiant) {
            return 'La migration est réservée aux étudiants.';
        }
        if ($etudiant->formation === FormationType::FI) {
            return 'Vous êtes déjà en formation initiale.';
        }
        if ($etudiant->formation === FormationType::FM) {
            return 'Vous suivez déjà les cours en formation initiale.';
        }
        if ($etudiant->formation !== FormationType::FA) {
            return 'Votre formation n\'est pas renseignée.';
        }
        if (! $etudiant->niveau || ! $etudiant->filiere) {
            return 'Votre niveau ou votre filière n\'est pas renseigné.';
        }
        if ($etudiant->niveau->chiffre() > (int) config('presence.migration.niveau_max', 2)) {
            return "La migration n'est pas ouverte en {$etudiant->niveau->nom}.";
        }
        if ($this->sallesEligibles($etudiant)->isEmpty()) {
            $departement = $etudiant->filiere->departement?->code ?? 'votre département';

            return "Aucune salle de formation initiale n'existe pour {$etudiant->niveau->nom} en {$departement}.";
        }

        return null;
    }

    /**
     * Les salles FI du même département et du même niveau que l'étudiant —
     * là où son emploi du temps de jour existe déjà.
     *
     * @return Collection<int, Salle>
     */
    public function sallesEligibles(User $etudiant): Collection
    {
        $etudiant->loadMissing('filiere');

        if (! $etudiant->filiere || ! $etudiant->niveau_id) {
            return collect();
        }

        return Salle::query()
            ->where('formation', FormationType::FI->value)
            ->whereHas('filiere', fn ($q) => $q
                ->where('departement_id', $etudiant->filiere->departement_id)
                ->where('niveau_id', $etudiant->niveau_id))
            ->with('filiere.niveau')
            ->withCount('etudiants')
            ->orderBy('nom')
            ->get();
    }
}
