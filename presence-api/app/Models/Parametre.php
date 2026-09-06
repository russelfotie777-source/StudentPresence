<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Réglage d'exploitation clé/valeur, modifiable par l'admin sans
 * redéploiement. Lu sur des chemins chauds (chaque connexion pour
 * FACE_AUTH_ROLES), donc systématiquement servi depuis le cache, invalidé
 * à l'écriture.
 */
#[Fillable(['cle', 'valeur'])]
class Parametre extends Model
{
    /**
     * Grades soumis au second facteur facial. Valeur : tableau de valeurs
     * de App\Enums\UserRole.
     */
    public const FACE_AUTH_ROLES = 'face_auth.roles';

    /**
     * Par défaut, tous les rôles qui pointent une présence ou déclarent des
     * heures payées. L'Admin en est exclu par construction (voir
     * faceAuthRoles()), pas par ce défaut.
     */
    public const FACE_AUTH_ROLES_DEFAUT = [
        UserRole::Etudiant->value,
        UserRole::Delegue->value,
        UserRole::Enseignant->value,
    ];

    protected function casts(): array
    {
        return ['valeur' => 'array'];
    }

    /**
     * @return array<int, string>
     */
    public static function faceAuthRoles(): array
    {
        $roles = Cache::rememberForever(
            self::cacheKey(self::FACE_AUTH_ROLES),
            fn () => self::where('cle', self::FACE_AUTH_ROLES)->value('valeur') ?? self::FACE_AUTH_ROLES_DEFAUT
        );

        // Garde-fou non négociable : l'Admin ne peut jamais être soumis au
        // facial. presence-admin n'a pas d'écran de capture, et un admin
        // incapable de s'inscrire ne pourrait plus désactiver le réglage
        // pour personne — l'app entière resterait bloquée.
        return array_values(array_diff($roles, [UserRole::Admin->value]));
    }

    /**
     * @param  array<int, string>  $roles
     * @return array<int, string>
     */
    public static function setFaceAuthRoles(array $roles): array
    {
        self::updateOrCreate(
            ['cle' => self::FACE_AUTH_ROLES],
            ['valeur' => array_values(array_diff($roles, [UserRole::Admin->value]))],
        );

        Cache::forget(self::cacheKey(self::FACE_AUTH_ROLES));

        return self::faceAuthRoles();
    }

    private static function cacheKey(string $cle): string
    {
        return 'parametre:'.$cle;
    }
}
