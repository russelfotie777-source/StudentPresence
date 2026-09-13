<?php

use Illuminate\Support\Facades\Schedule;

// Rappels de pointage : un passage par minute, à l'heure de Douala
// (config app.timezone). Nécessite le planificateur côté serveur :
// `* * * * * php artisan schedule:run` en cron, ou `php artisan schedule:work`.
Schedule::command('presence:rappels')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();
