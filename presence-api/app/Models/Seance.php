<?php

namespace App\Models;

use App\Enums\PresenceState;
use App\Enums\Weekday;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'course_template_id', 'semaine_id', 'salle_id', 'enseignant_id', 'groupe',
    'date_seance', 'jour', 'heure_debut', 'heure_fin', 'debut_reel', 'fin_reelle',
    'etat_delegue', 'etat_prof', 'presences_locked', 'commentaires',
])]
class Seance extends Model
{
    protected function casts(): array
    {
        return [
            'jour' => Weekday::class,
            'date_seance' => 'date',
            'etat_delegue' => PresenceState::class,
            'etat_prof' => PresenceState::class,
            'etat_final' => PresenceState::class,
            'presences_locked' => 'boolean',
        ];
    }

    public function courseTemplate(): BelongsTo
    {
        return $this->belongsTo(CourseTemplate::class);
    }

    public function semaine(): BelongsTo
    {
        return $this->belongsTo(Semaine::class);
    }

    public function salle(): BelongsTo
    {
        return $this->belongsTo(Salle::class);
    }

    public function enseignant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enseignant_id');
    }

    public function presences(): HasMany
    {
        return $this->hasMany(PresenceEtudiant::class);
    }

    public function position(): HasOne
    {
        return $this->hasOne(PositionSeance::class);
    }

    public function push(): HasOne
    {
        return $this->hasOne(Push::class);
    }

    public function requetes(): HasMany
    {
        return $this->hasMany(RequeteEnseignant::class);
    }

    /**
     * Horodatage complet de heure_debut/heure_fin sur la date de la séance
     * (les colonnes DB ne stockent que l'heure).
     */
    public function debutPrevu(): Carbon
    {
        return Carbon::parse($this->date_seance?->toDateString() ?? today()->toDateString().' '.$this->heure_debut);
    }

    public function finPrevue(): Carbon
    {
        return Carbon::parse($this->date_seance?->toDateString() ?? today()->toDateString().' '.$this->heure_fin);
    }

    /**
     * Fenêtre active = ±15 min autour de l'horaire prévu. C'est la règle qui,
     * dans l'ancienne app, conditionnait le marquage présence/absence, l'envoi
     * de position GPS et l'horodatage début/fin réel.
     */
    protected function isActive(): Attribute
    {
        return Attribute::get(function () {
            $now = now();

            return $now->between(
                $this->debutPrevu()->subMinutes(15),
                $this->finPrevue()->addMinutes(15),
            );
        });
    }

    protected function isPast(): Attribute
    {
        return Attribute::get(fn () => now()->greaterThan($this->finPrevue()->addMinutes(15)));
    }
}
