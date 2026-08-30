<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class RequeteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'seance_id' => $this->seance_id,
            'enseignant' => $this->whenLoaded('enseignant', fn () => $this->enseignant->name),
            'matiere' => $this->matiere,
            'salle' => $this->salle,
            'niveau' => $this->niveau,
            'heure_seance' => $this->heure_seance,
            'description' => $this->description,
            'preuve_url' => $this->preuve_path ? Storage::url($this->preuve_path) : null,
            'statut' => $this->statut->value,
            'date_creation' => $this->date_creation?->toIso8601String(),
            'date_traitement' => $this->date_traitement?->toIso8601String(),
            'commentaire_admin' => $this->commentaire_admin,
        ];
    }
}
