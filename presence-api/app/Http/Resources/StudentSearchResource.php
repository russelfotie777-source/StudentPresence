<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Étudiant tel qu'affiché dans le formulaire de promotion temporaire.
 *
 * has_active_promotion s'appuie sur la relation chargée en amont par le
 * scope withActivePromotions() : sans ce chargement, chaque ligne coûterait
 * une requête supplémentaire (voir QueryPerformanceTest).
 */
class StudentSearchResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'salle' => $this->whenLoaded('salle', fn () => $this->salle?->nom),
            'filiere' => $this->whenLoaded('filiere', fn () => $this->filiere?->nom),
            'niveau' => $this->whenLoaded('niveau', fn () => $this->niveau?->nom),
            'has_active_promotion' => $this->hasActivePromotion(),
        ];
    }
}
