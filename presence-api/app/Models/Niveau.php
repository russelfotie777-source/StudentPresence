<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['nom'])]
class Niveau extends Model
{
    public function filieres(): HasMany
    {
        return $this->hasMany(Filiere::class);
    }

    public function groupes(): HasMany
    {
        return $this->hasMany(Groupe::class);
    }

    public function tarifHeure(): HasOne
    {
        return $this->hasOne(TarifHeure::class);
    }
}
