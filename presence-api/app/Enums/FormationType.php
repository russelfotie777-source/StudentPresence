<?php

namespace App\Enums;

enum FormationType: string
{
    case FI = 'FI'; // Formation Initiale
    case FA = 'FA'; // Formation Alternance
    case FM = 'FM'; // Formation "migrante" (rattachée à une salle FI)
}
