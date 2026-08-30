<?php

namespace App\Models;

use App\Enums\FormationType;
use App\Enums\UserRole;
use App\Enums\ValidationStatus;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'phone', 'email', 'password', 'role', 'formation', 'salle_id', 'niveau_id', 'filiere_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'validation_status' => ValidationStatus::class,
            'formation' => FormationType::class,
        ];
    }

    public function niveau(): BelongsTo
    {
        return $this->belongsTo(Niveau::class);
    }

    public function filiere(): BelongsTo
    {
        return $this->belongsTo(Filiere::class);
    }

    public function salle(): BelongsTo
    {
        return $this->belongsTo(Salle::class);
    }

    public function coursEnseignes(): HasMany
    {
        return $this->hasMany(CourseTemplate::class, 'enseignant_id');
    }

    public function seancesEnseignees(): HasMany
    {
        return $this->hasMany(Seance::class, 'enseignant_id');
    }

    public function presences(): HasMany
    {
        return $this->hasMany(PresenceEtudiant::class, 'etudiant_id');
    }

    public function requetes(): HasMany
    {
        return $this->hasMany(RequeteEnseignant::class, 'enseignant_id');
    }

    public function promotionsRecues(): HasMany
    {
        return $this->hasMany(PromotionTemporaire::class, 'etudiant_id');
    }

    public function isTeacher(): bool
    {
        return $this->role === UserRole::Enseignant;
    }

    public function isDelegate(): bool
    {
        return $this->role === UserRole::Delegue && $this->salle_id !== null;
    }

    public function isStudent(): bool
    {
        return $this->role === UserRole::Etudiant && $this->salle_id !== null;
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    /**
     * Vrai si une promotion temporaire (Étudiant → Délégué) est active en ce moment.
     * Contrairement à l'ancienne app, ce n'est jamais figé en session : c'est
     * recalculé à chaque requête via un middleware (voir EnsureRoleAbilities).
     */
    public function hasActivePromotion(): bool
    {
        return $this->promotionsRecues()->where('date_fin', '>', now())->exists();
    }
}
