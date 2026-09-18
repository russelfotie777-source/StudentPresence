<?php

use Illuminate\Support\Facades\Schedule;

// Rappels de pointage : un passage par minute, à l'heure de Douala
// (config app.timezone). Nécessite le planificateur côté serveur :
// `* * * * * php artisan schedule:run` en cron, ou `php artisan schedule:work`.
Schedule::command('presence:rappels')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();

// Clôture des séances présentes sans fin réelle enregistrée, une fois leur
// fenêtre de pointage fermée — sans elle, ces séances n'entrent jamais dans
// la paie (voir App\Services\ClotureSeances).
Schedule::command('presence:cloturer')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();
