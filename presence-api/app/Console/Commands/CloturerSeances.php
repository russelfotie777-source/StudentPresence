<?php

namespace App\Console\Commands;

use App\Services\ClotureSeances;
use App\Services\PresenceAutomatique;
use Illuminate\Console\Command;

/**
 * Un passage de clôture des séances présentes restées sans fin réelle, et
 * de pose des présences automatiques (privilège « toujours présent ») sur
 * les séances du jour déjà commencées. Planifié chaque minute (voir
 * routes/console.php) ; peut aussi se lancer à la main.
 */
class CloturerSeances extends Command
{
    protected $signature = 'presence:cloturer';

    protected $description = 'Clôture à l\'heure prévue les séances marquées présentes dont la fin réelle n\'a pas été enregistrée';

    public function handle(ClotureSeances $cloture, PresenceAutomatique $automatique): int
    {
        $nombre = $cloture->tick();
        $posees = $automatique->tick();

        $this->info(sprintf('%s — %d séance(s) clôturée(s), %d présence(s) automatique(s) posée(s).', now()->format('d/m H:i'), $nombre, $posees));

        return self::SUCCESS;
    }
}
