<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DemandeFormationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'etudiant' => $this->whenLoaded('etudiant', fn () => [
                'id' => $this->etudiant->id,
                'name' => $this->etudiant->name,
                'phone' => $this->etudiant->phone,
                'salle' => $this->etudiant->salle?->nom,
                // Niveau et filière servent à restreindre les salles FI
                // proposées à l'admin : sans eux, il choisit une cible parmi
                // toutes les salles de l'établissement, dont les noms se
                // répètent d'une filière à l'autre.
                'niveau' => $this->etudiant->niveau?->nom,
                'niveau_id' => $this->etudiant->niveau_id,
                'filiere' => $this->etudiant->filiere?->nom,
            ]),
            'salle_cible' => $this->whenLoaded('salleCible', fn () => $this->salleCible ? [
                'id' => $this->salleCible->id,
                'nom' => $this->salleCible->nom,
            ] : null),
            'motif' => $this->motif,
            'statut' => $this->statut->value,
            'date_creation' => $this->date_creation?->toIso8601String(),
            'date_traitement' => $this->date_traitement?->toIso8601String(),
            'commentaire_admin' => $this->commentaire_admin,
        ];
    }
}
