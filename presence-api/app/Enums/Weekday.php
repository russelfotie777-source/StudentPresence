<?php

namespace App\Enums;

use Carbon\Carbon;

enum Weekday: string
{
    case Lundi = 'LUNDI';
    case Mardi = 'MARDI';
    case Mercredi = 'MERCREDI';
    case Jeudi = 'JEUDI';
    case Vendredi = 'VENDREDI';
    case Samedi = 'SAMEDI';
    case Dimanche = 'DIMANCHE';

    public static function fromCarbon(Carbon $date): self
    {
        return self::from(match ($date->dayOfWeekIso) {
            1 => 'LUNDI', 2 => 'MARDI', 3 => 'MERCREDI', 4 => 'JEUDI',
            5 => 'VENDREDI', 6 => 'SAMEDI', 7 => 'DIMANCHE',
        });
    }

    /** 1 pour lundi … 7 pour dimanche (ISO-8601). */
    public function iso(): int
    {
        return array_search($this, self::cases(), true) + 1;
    }

    /**
     * Ce jour de la semaine tombe-t-il au moins une fois entre deux dates
     * (bornes incluses) ? Vrai dès que la plage couvre 7 jours.
     */
    public function tombeEntre(Carbon $debut, Carbon $fin): bool
    {
        if ($fin->lt($debut)) {
            return false;
        }

        $premier = $debut->clone()->startOfDay();
        $decalage = ($this->iso() - $premier->dayOfWeekIso + 7) % 7;

        return $premier->addDays($decalage)->lte($fin->clone()->endOfDay());
    }
}
