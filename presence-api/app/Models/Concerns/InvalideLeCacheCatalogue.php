<?php

namespace App\Models\Concerns;

use App\Services\CatalogueCache;

/**
 * Périme le cache du catalogue dès qu'une entité qui le compose change.
 *
 * Branché sur les évènements du modèle plutôt que dans les contrôleurs :
 * l'invalidation suit alors n'importe quel chemin d'écriture (CRUD admin,
 * seeder, tinker, commande artisan), sans qu'on ait à y penser à chaque fois.
 */
trait InvalideLeCacheCatalogue
{
    protected static function bootInvalideLeCacheCatalogue(): void
    {
        static::saved(fn () => CatalogueCache::invalider());
        static::deleted(fn () => CatalogueCache::invalider());
    }
}
