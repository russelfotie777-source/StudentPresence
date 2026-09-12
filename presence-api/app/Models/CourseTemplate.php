<?php

namespace App\Models;

use App\Enums\Weekday;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['matiere_id', 'enseignant_id', 'salle_id', 'groupe', 'jour', 'heure_debut', 'heure_fin', 'date_debut', 'date_fin', 'actif'])]
class CourseTemplate extends Model
{
    use HasFactory;

    // Eloquent ne relit pas les valeurs par défaut de la base après un
    // insert : sans ceci, un cours créé sans groupe engendrerait des séances
    // au groupe null.
    protected $attributes = ['groupe' => 'G1'];

    protected function casts(): array
    {
        return [
            'jour' => Weekday::class,
            'date_debut' => 'date:Y-m-d',
            'date_fin' => 'date:Y-m-d',
            'actif' => 'boolean',
        ];
    }

    public function matiere(): BelongsTo
    {
        return $this->belongsTo(Matiere::class);
    }

    public function enseignant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enseignant_id');
    }

    public function salle(): BelongsTo
    {
        return $this->belongsTo(Salle::class);
    }

    public function seances(): HasMany
    {
        return $this->hasMany(Seance::class);
    }
}
