<?php

namespace App\Console\Commands;

use App\Services\RappelsPointage;
use Illuminate\Console\Command;

/**
 * Un passage des rappels de pointage. Planifié chaque minute (voir
 * routes/console.php) ; peut aussi se lancer à la main pour vérifier.
 */
class EnvoyerRappelsPointage extends Command
{
    protected $signature = 'presence:rappels';

    protected $description = 'Envoie les rappels de pointage dus à cet instant (délégué, ouverture, dernière chance)';

    public function handle(RappelsPointage $rappels): int
    {
        $envoyes = $rappels->tick();

        $this->info(sprintf(
            '%s — délégués : %d · ouverture : %d · dernière chance : %d',
            now()->format('d/m H:i'),
            $envoyes['delegue'],
            $envoyes['ouverture'],
            $envoyes['derniere_chance'],
        ));

        return self::SUCCESS;
    }
}
