<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Une matière appartient à une filière (donc à un niveau et à un
 * département) ; sans filière, elle est commune à toutes.
 */
#[Fillable(['nom', 'code', 'filiere_id'])]
class Matiere extends Model
{
    use HasFactory;

    public function filiere(): BelongsTo
    {
        return $this->belongsTo(Filiere::class);
    }

    /** Les matières qu'une salle peut suivre : celles de sa filière, et les communes. */
    public function scopePourFiliere(Builder $query, ?int $filiereId): Builder
    {
        return $query->where(fn (Builder $q) => $q->whereNull('filiere_id')->orWhere('filiere_id', $filiereId));
    }

    public function courseTemplates(): HasMany
    {
        return $this->hasMany(CourseTemplate::class);
    }
}
