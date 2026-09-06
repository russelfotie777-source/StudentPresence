<?php

namespace App\Services;

use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * Cache des listes du catalogue académique (niveaux, filières, salles).
 *
 * Ces listes sont lues à chaque ouverture du formulaire d'inscription et des
 * écrans admin, mais ne changent que quelques fois par an.
 *
 * L'invalidation passe par un numéro de version inclus dans la clé, et non
 * par des oublis clé par clé : le store `database` ne gère pas les tags, et
 * surtout les entrées sont liées entre elles (renommer un niveau change
 * l'affichage des salles, qui embarquent filiere.niveau). Incrémenter la
 * version périme tout le catalogue d'un coup, y compris les listes filtrées
 * par filière dont on ne connaît pas les clés à l'avance.
 */
class CatalogueCache
{
    private const CLE_VERSION = 'catalogue:version';

    /**
     * Filet de sécurité si une écriture passait un jour hors des modèles
     * (SQL brut, import) : la version ne serait pas incrémentée, le cache
     * expire quand même de lui-même.
     */
    private const TTL_SECONDES = 3600;

    public static function souvenir(string $cle, Closure $callback): mixed
    {
        return Cache::remember(self::prefixe().$cle, self::TTL_SECONDES, $callback);
    }

    public static function invalider(): void
    {
        Cache::forever(self::CLE_VERSION, self::version() + 1);
    }

    private static function prefixe(): string
    {
        return 'catalogue:v'.self::version().':';
    }

    private static function version(): int
    {
        return (int) Cache::get(self::CLE_VERSION, 1);
    }
}
