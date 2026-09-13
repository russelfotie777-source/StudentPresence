<?php

namespace App\Models;

use App\Enums\FormationType;
use App\Enums\StatutCompte;
use App\Enums\UserRole;
use App\Enums\ValidationStatus;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'phone', 'email', 'password', 'role', 'validation_status', 'statut_compte', 'motif_statut', 'statut_modifie_le', 'formation', 'salle_id', 'niveau_id', 'filiere_id', 'quota', 'face_descriptor', 'face_enrolled_at'])]
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
     * valeurs par défaut des colonnes après un insert. Même piège pour
     * statut_compte : null y serait lu comme "pas actif", donc pointage
     * refusé à tout compte tout juste créé.
     */
    protected $attributes = [
        'quota' => 0,
        'statut_compte' => 'actif',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'validation_status' => ValidationStatus::class,
            'statut_compte' => StatutCompte::class,
            'statut_modifie_le' => 'datetime',
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
     * Grades soumis au second facteur facial, réglables par l'admin depuis
     * presence-admin (voir Parametre::FACE_AUTH_ROLES).
     *
     * Le motif reste le même pour tous : empêcher qu'un mot de passe partagé
     * suffise à pointer, ou à déclarer des heures payées, à la place de
     * quelqu'un d'autre. Le délégué est concerné au même titre que
     * l'étudiant — c'est un étudiant promu, qui pointe aussi pour lui-même —
     * et l'enseignant l'est parce qu'il déclare ses heures réelles, qui
     * déterminent sa paie.
     *
     * On se fonde sur `role` et non sur effectiveRole() : un étudiant
     * temporairement promu délégué doit rester soumis aux règles de son
     * grade réel, sinon une promotion pourrait servir à contourner le
     * facial.
     */
    public function requiresFaceAuth(): bool
    {
        return in_array($this->role->value, Parametre::faceAuthRoles(), true);
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

    /**
     * Vrai si cet enseignant a au moins une séance dans cette salle — un
     * enseignant n'a pas de salle_id propre (contrairement à
     * l'étudiant/délégué), il "enseigne dans" une salle via ses séances,
     * potentiellement plusieurs.
     */
    public function enseigneDansSalle(int $salleId): bool
    {
        return $this->seancesEnseignees()->where('salle_id', $salleId)->exists();
    }

    /**
     * Salles distinctes où cet enseignant a au moins une séance.
     */
    public function salleIdsEnseignees(): Collection
    {
        return $this->seancesEnseignees()->distinct()->pluck('salle_id');
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

    public function estBloque(): bool
    {
        return $this->statut_compte === StatutCompte::Bloque;
    }

    /**
     * Vrai si le compte ne peut plus pointer — restreint ou bloqué. Un
     * compte bloqué ne devrait plus avoir de jeton, mais on ne s'y fie pas.
     */
    public function pointageInterdit(): bool
    {
        return $this->statut_compte !== StatutCompte::Actif;
    }

    /**
     * Vrai si une promotion temporaire (Étudiant → Délégué) est active en ce moment.
     * Contrairement à l'ancienne app, ce n'est jamais figé en session/token : c'est
     * recalculé à chaque requête via effectiveRole() / le middleware EnsureRole.
     *
     * Répond depuis la relation quand elle est déjà chargée, sinon interroge
     * la base. Sans ça, sérialiser une liste d'utilisateurs coûte deux
     * requêtes par ligne (UserResource appelle hasActivePromotion() ET
     * effectiveRole(), qui l'appelle à son tour) — voir scopeWithActivePromotions().
     */
    public function hasActivePromotion(): bool
    {
        if ($this->relationLoaded('promotionsRecues')) {
            return $this->promotionsRecues->contains(
                fn (PromotionTemporaire $promotion) => $promotion->date_fin->isFuture()
            );
        }

        return $this->promotionsRecues()->where('date_fin', '>', now())->exists();
    }

    /**
     * À appliquer dès qu'on sérialise plusieurs utilisateurs d'un coup :
     * charge les promotions encore actives en une seule requête, ce qui rend
     * hasActivePromotion()/effectiveRole() gratuits pour toute la collection.
     */
    #[Scope]
    protected function withActivePromotions(Builder $query): void
    {
        $query->with([
            'promotionsRecues' => fn (HasMany $q) => $q->where('date_fin', '>', now()),
        ]);
    }

    /**
     * Charge en une fois tout ce que UserResource sérialise — y compris les
     * promotions actives, sans quoi chaque sérialisation d'un utilisateur
     * déclenche deux requêtes supplémentaires. Utilisé sur les chemins
     * chauds (login, /auth/me appelé à chaque chargement de page).
     */
    public function loadForResource(): static
    {
        return $this->load([
            'salle',
            'niveau',
            'filiere',
            'promotionsRecues' => fn (HasMany $q) => $q->where('date_fin', '>', now()),
        ]);
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
