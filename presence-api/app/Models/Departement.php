<?php

namespace App\Models;

use App\Models\Concerns\InvalideLeCacheCatalogue;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Str;

/**
 * Sommet de la structure académique : un département possède des filières
 * (une par niveau au minimum), qui possèdent des salles. Les niveaux, eux,
 * sont communs à tout l'établissement — un département "a" tous les niveaux
 * d'office, c'est par ses filières qu'il s'y déploie.
 */
#[Fillable(['nom', 'code', 'nom_en'])]
class Departement extends Model
{
    use HasFactory, InvalideLeCacheCatalogue;

    public function filieres(): HasMany
    {
        return $this->hasMany(Filiere::class);
    }

    public function salles(): HasManyThrough
    {
        return $this->hasManyThrough(Salle::class, Filiere::class);
    }

    /**
     * Intitulé officiel français tel qu'il figure en tête des listes de
     * présence : "DEPARTEMENT DE GENIE INFORMATIQUE", sans accents comme sur
     * la maquette, avec l'élision devant une voyelle ("D'INFORMATIQUE").
     */
    public function enteteFr(): string
    {
        $nom = Str::upper(Str::ascii($this->nom));
        $liaison = preg_match('/^[AEIOUYH]/', $nom) ? "D'" : 'DE ';

        return "DEPARTEMENT {$liaison}{$nom}";
    }

    /** Pendant anglais de l'en-tête ; à défaut de traduction, le nom français tel quel. */
    public function enteteEn(): string
    {
        return 'DEPARTMENT OF '.Str::upper(Str::ascii($this->nom_en ?: $this->nom));
    }
}
