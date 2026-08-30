<?php

return [
    /*
     * Distance maximale (en mètres) entre un étudiant et la position GPS
     * envoyée par le délégué pour qu'un pointage de présence soit accepté.
     * L'ancienne app affichait "100m" à l'étudiant, son commentaire de code
     * disait "650m", et la valeur réellement appliquée était 2550m — aucun
     * des trois n'était le bon. Valeur retenue pour la v2 : 120m.
     */
    'max_check_in_distance_meters' => env('PRESENCE_MAX_DISTANCE_METERS', 120),
];
