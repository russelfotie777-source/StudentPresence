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

#[Fillable(['name', 'phone', 'email', 'password', 'role', 'validation_status', 'formation', 'salle_id', 'niveau_id', 'filiere_id', 'quota', 'face_descriptor', 'face_enrolled_at'])]
// face_descriptor est une donnée biométrique : jamais renvoyée par l'API,
// même par accident (ex. un ->toArray() ajouté négligemment plus tard).
#[Hidden(['password', 'remember_token', 'face_descriptor'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Sans ça, un User fraîchement créé sans `quota` explicite (ex.
     * AuthController::register) expose `quota: null` en mémoire tant que le
     * modèle n'a pas été rechargé depuis la base — Eloquent ne relit pas les
     * valeurs par défaut des colonnes après un insert.
     */
    protected $attributes = [
        'quota' => 0,
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'validation_status' => ValidationStatus::class,
            'formation' => FormationType::class,
            'face_descriptor' => 'array',
            'face_enrolled_at' => 'datetime',
        ];
    }

    /**
     * Vrai si un descripteur facial a déjà été enregistré pour ce compte —
     * détermine si la seconde étape de connexion est une inscription ou une
     * vérification (voir FaceController).
     */
    public function hasFaceEnrolled(): bool
    {
        return $this->face_descriptor !== null;
    }

    /**
     * Seul l'étudiant est concerné par le second facteur facial : c'est le
     * rôle qui pointe sa propre présence via GPS, donc celui où un mot de
     * passe partagé pourrait permettre de pointer à la place d'un autre.
     */
    public function requiresFaceAuth(): bool
    {
        return $this->role === UserRole::Etudiant;
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
     * Contrairement à l'ancienne app, ce n'est jamais figé en session/token : c'est
     * recalculé à chaque requête via effectiveRole() / le middleware EnsureRole.
     */
    public function hasActivePromotion(): bool
    {
        return $this->promotionsRecues()->where('date_fin', '>', now())->exists();
    }

    /**
     * Rôle réellement applicable pour cette requête : identique à `role`, sauf
     * pour un Étudiant avec une promotion temporaire active, qui agit alors
     * comme Délégué de sa propre salle.
     */
    public function effectiveRole(): UserRole
    {
        if ($this->role === UserRole::Etudiant && $this->hasActivePromotion()) {
            return UserRole::Delegue;
        }

        return $this->role;
    }
}
