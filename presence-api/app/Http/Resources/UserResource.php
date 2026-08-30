<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'role' => $this->role->value,
            'effective_role' => $this->effectiveRole()->value,
            'validation_status' => $this->validation_status->value,
            'formation' => $this->formation?->value,
            'salle' => $this->whenLoaded('salle', fn () => [
                'id' => $this->salle->id,
                'nom' => $this->salle->nom,
            ]),
            'niveau' => $this->whenLoaded('niveau', fn () => [
                'id' => $this->niveau->id,
                'nom' => $this->niveau->nom,
            ]),
            'filiere' => $this->whenLoaded('filiere', fn () => [
                'id' => $this->filiere->id,
                'nom' => $this->filiere->nom,
            ]),
            'quota' => $this->quota,
            'has_active_promotion' => $this->hasActivePromotion(),
        ];
    }
}
