<?php

namespace App\Models;

use App\Enums\FormationType;
use App\Models\Concerns\InvalideLeCacheCatalogue;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['nom', 'filiere_id', 'formation'])]
class Salle extends Model
{
    use HasFactory, InvalideLeCacheCatalogue;

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

    /**
     * Formations dont les étudiants suivent les cours dans cette salle : une
     * salle FI accueille aussi les "migrants" FM (des FA passés à l'emploi
     * du temps de jour), une salle FA n'accueille que des FA. Règle héritée
     * de l'ancienne app, partagée par le roster et la liste de présence.
     *
     * @return array<int, string>
     */
    public function formationsAccueillies(): array
    {
        return $this->formation === FormationType::FI
            ? [FormationType::FI->value, FormationType::FM->value]
            : [FormationType::FA->value];
    }
}
