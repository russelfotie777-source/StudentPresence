<?php

namespace App\Models;

use App\Enums\FormationType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['nom', 'filiere_id', 'formation'])]
class Salle extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return ['formation' => FormationType::class];
    }

    public function filiere(): BelongsTo
    {
        return $this->belongsTo(Filiere::class);
    }

    public function courseTemplates(): HasMany
    {
        return $this->hasMany(CourseTemplate::class);
    }

    public function seances(): HasMany
    {
        return $this->hasMany(Seance::class);
    }

    public function etudiants(): HasMany
    {
        return $this->hasMany(User::class, 'salle_id');
    }
}
