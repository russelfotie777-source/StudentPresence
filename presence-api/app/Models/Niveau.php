<?php

namespace App\Models;

use App\Models\Concerns\InvalideLeCacheCatalogue;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['nom'])]
class Niveau extends Model
{
    use HasFactory, InvalideLeCacheCatalogue;

    // La convention Eloquent par défaut ("niveaus") ignore le pluriel
    // français ("niveaux") du mot "niveau".
    protected $table = 'niveaux';

    /** "L2" → 2, "DUT3" → 3, "Licence" → 1 : le rang du niveau dans le cursus. */
    public function chiffre(): int
    {
        return preg_match('/(\d+)/', $this->nom, $m) ? (int) $m[1] : 1;
    }

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
