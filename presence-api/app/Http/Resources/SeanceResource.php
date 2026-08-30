<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SeanceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'salle' => $this->whenLoaded('salle', fn () => $this->salle->nom),
            'enseignant' => $this->whenLoaded('enseignant', fn () => $this->enseignant->name),
            'matiere' => $this->whenLoaded('courseTemplate', fn () => $this->courseTemplate?->matiere?->nom),
            'push' => $this->whenLoaded('pushRequest', fn () => $this->pushRequest ? [
                'etudiants_presents' => $this->pushRequest->etudiants_presents,
                'status' => $this->pushRequest->status->value,
            ] : null),
            // Présence de l'étudiant courant — seulement peuplé quand
            // SeanceController::today() a chargé `presences` filtrée sur son
            // propre id (vue Étudiant uniquement).
            'ma_presence' => $this->whenLoaded('presences', fn () => $this->presences->first()?->etat?->value),
            'position_envoyee' => $this->whenLoaded('position', fn () => $this->position !== null),
            'groupe' => $this->groupe,
            'date_seance' => $this->date_seance?->toDateString(),
            'jour' => $this->jour->value,
            'heure_debut' => $this->heure_debut,
            'heure_fin' => $this->heure_fin,
            'debut_reel' => $this->debut_reel,
            'fin_reelle' => $this->fin_reelle,
            'etat_delegue' => $this->etat_delegue?->value,
            'etat_prof' => $this->etat_prof?->value,
            'etat_final' => $this->etat_final->value,
            'presences_locked' => $this->presences_locked,
            'is_active' => $this->is_active,
            'is_past' => $this->is_past,
        ];
    }
}
