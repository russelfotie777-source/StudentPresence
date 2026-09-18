<?php

namespace App\Console\Commands;

use App\Services\ClotureSeances;
use Illuminate\Console\Command;

/**
 * Un passage de clôture des séances présentes restées sans fin réelle.
 * Planifié chaque minute (voir routes/console.php) ; peut aussi se lancer
 * à la main, par exemple pour rattraper un historique.
 */
class CloturerSeances extends Command
{
    protected $signature = 'presence:cloturer';

    protected $description = 'Clôture à l\'heure prévue les séances marquées présentes dont la fin réelle n\'a pas été enregistrée';

    public function handle(ClotureSeances $cloture): int
    {
        $nombre = $cloture->tick();

        $this->info(sprintf('%s — %d séance(s) clôturée(s).', now()->format('d/m H:i'), $nombre));

        return self::SUCCESS;
    }
}
