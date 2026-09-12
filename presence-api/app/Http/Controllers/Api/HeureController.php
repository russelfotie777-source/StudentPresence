<?php

namespace App\Http\Controllers\Api;

use App\Enums\Weekday;
use App\Http\Controllers\Controller;

/**
 * Heure de référence de l'application — celle de Douala, quel que soit le
 * fuseau du serveur qui héberge l'API ou celui du téléphone/PC qui l'appelle.
 * Les fronts s'alignent dessus (ligne « maintenant » de l'emploi du temps,
 * date du jour) au lieu de faire confiance à l'horloge de la machine.
 */
class HeureController extends Controller
{
    public function __invoke()
    {
        $maintenant = now();

        return response()->json([
            'maintenant' => $maintenant->toIso8601String(),
            'fuseau' => config('app.timezone'),
            'date' => $maintenant->toDateString(),
            'heure' => $maintenant->format('H:i'),
            'jour' => Weekday::fromCarbon($maintenant)->value,
        ]);
    }
}
