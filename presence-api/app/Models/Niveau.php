<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['nom'])]
class Niveau extends Model
{
    use HasFactory;

    // La convention Eloquent par défaut ("niveaus") ignore le pluriel
    // français ("niveaux") du mot "niveau".
    protected $table = 'niveaux';

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
