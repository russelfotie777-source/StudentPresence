<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['nom', 'niveau_id'])]
class Filiere extends Model
{
    public function niveau(): BelongsTo
    {
        return $this->belongsTo(Niveau::class);
    }

    public function salles(): HasMany
    {
        return $this->hasMany(Salle::class);
    }

    public function groupes(): HasMany
    {
        return $this->hasMany(Groupe::class);
    }
}
