<?php

namespace App\Models;

use App\Enums\Weekday;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['matiere_id', 'enseignant_id', 'salle_id', 'groupe', 'jour', 'heure_debut', 'heure_fin', 'date_debut', 'date_fin', 'actif'])]
class CourseTemplate extends Model
{
    protected function casts(): array
    {
        return [
            'jour' => Weekday::class,
            'date_debut' => 'date',
            'date_fin' => 'date',
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
