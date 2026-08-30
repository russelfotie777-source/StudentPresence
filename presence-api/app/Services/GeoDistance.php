<?php

namespace App\Services;

class GeoDistance
{
    private const EARTH_RADIUS_METERS = 6371000;

    /**
     * Distance en mètres entre deux points GPS (formule de Haversine),
     * arrondie à 2 décimales — identique à check_distance.php dans
     * l'ancienne app.
     */
    public static function metersBetween(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $lat1 = deg2rad($lat1);
        $lon1 = deg2rad($lon1);
        $lat2 = deg2rad($lat2);
        $lon2 = deg2rad($lon2);

        $dLat = $lat2 - $lat1;
        $dLon = $lon2 - $lon1;

        $a = sin($dLat / 2) ** 2 + cos($lat1) * cos($lat2) * sin($dLon / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return round(self::EARTH_RADIUS_METERS * $c, 2);
    }
}
