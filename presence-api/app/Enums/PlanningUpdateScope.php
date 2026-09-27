<?php

namespace App\Enums;

enum PlanningUpdateScope: string
{
    case Occurrence = 'seance';
    case Following = 'suivantes';
    case Series = 'serie';
}
