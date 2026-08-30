<?php

namespace App\Http\Resources;

use App\Services\PayrollResult;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property PayrollResult $resource
 */
class PayrollResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'total_salaire' => $this->resource->totalSalaire,
            'total_penalite_retard' => $this->resource->totalPenaliteRetard,
            'total_minutes' => $this->resource->totalMinutes,
            'lignes' => $this->resource->lines->map(fn ($line) => [
                'seance_id' => $line->seance->id,
                'date' => $line->seance->date_seance?->toDateString(),
                'matiere' => $line->seance->courseTemplate?->matiere?->nom,
                'salle' => $line->seance->salle->nom,
                'heure_debut' => $line->seance->heure_debut,
                'debut_reel' => $line->seance->debut_reel,
                'fin_reelle' => $line->seance->fin_reelle,
                'retard_minutes' => $line->retardMinutes,
                'duree_minutes' => $line->dureeMinutes,
                'tarif_plein' => $line->tarifPlein,
                'salaire' => $line->salaire,
                'penalite_retard' => $line->penaliteRetard,
            ])->values(),
        ];
    }
}
