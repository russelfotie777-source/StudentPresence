<?php

namespace App\Enums;

enum Weekday: string
{
    case Lundi = 'LUNDI';
    case Mardi = 'MARDI';
    case Mercredi = 'MERCREDI';
    case Jeudi = 'JEUDI';
    case Vendredi = 'VENDREDI';
    case Samedi = 'SAMEDI';
    case Dimanche = 'DIMANCHE';

    public static function fromCarbon(\Carbon\Carbon $date): self
    {
        return self::from(match ($date->dayOfWeekIso) {
            1 => 'LUNDI', 2 => 'MARDI', 3 => 'MERCREDI', 4 => 'JEUDI',
            5 => 'VENDREDI', 6 => 'SAMEDI', 7 => 'DIMANCHE',
        });
    }
}
