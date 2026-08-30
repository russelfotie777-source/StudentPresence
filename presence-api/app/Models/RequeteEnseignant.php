<?php

namespace App\Models;

use App\Enums\RequestStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'seance_id', 'enseignant_id', 'heure_seance', 'matiere', 'salle', 'niveau',
    'penalite', 'description', 'preuve_path', 'statut', 'date_creation',
    'date_traitement', 'commentaire_admin',
])]
class RequeteEnseignant extends Model
{
    protected function casts(): array
    {
        return [
            'statut' => RequestStatus::class,
            'penalite' => 'decimal:2',
            'date_creation' => 'datetime',
            'date_traitement' => 'datetime',
        ];
    }

    public function seance(): BelongsTo
    {
        return $this->belongsTo(Seance::class);
    }

    public function enseignant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enseignant_id');
    }
}
