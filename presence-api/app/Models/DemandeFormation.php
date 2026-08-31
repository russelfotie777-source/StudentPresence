<?php

namespace App\Models;

use App\Enums\RequestStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['etudiant_id', 'salle_cible_id', 'motif', 'statut', 'date_creation', 'date_traitement', 'commentaire_admin'])]
class DemandeFormation extends Model
{
    // Convention Eloquent par défaut ("demande_formations") ne pluralise que
    // le dernier mot — la table réelle est "demandes_formation".
    protected $table = 'demandes_formation';

    protected function casts(): array
    {
        return [
            'statut' => RequestStatus::class,
            'date_creation' => 'datetime',
            'date_traitement' => 'datetime',
        ];
    }

    public function etudiant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'etudiant_id');
    }

    public function salleCible(): BelongsTo
    {
        return $this->belongsTo(Salle::class, 'salle_cible_id');
    }
}
