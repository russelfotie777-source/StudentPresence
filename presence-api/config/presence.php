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

    /*
     * Incertitude maximale (coords.accuracy du navigateur, en mètres) au-delà
     * de laquelle une position est refusée plutôt qu'utilisée.
     *
     * Une mesure à ±800 m ne dit rien d'utile face à un seuil de 120 m : la
     * refuser vaut mieux que l'accepter, l'étudiant peut réessayer près d'une
     * fenêtre pendant que le GPS converge.
     *
     * Le délégué est tenu plus strictement que les étudiants : son point sert
     * de référence à toute la classe, son erreur se propage à tout le monde.
     * Ces seuils restent larges parce que les cours ont lieu à l'intérieur,
     * où le GPS plafonne souvent autour de 20 à 50 m ; à resserrer si le
     * campus s'avère mieux couvert que prévu.
     */
    'max_position_accuracy_meters' => env('PRESENCE_MAX_POSITION_ACCURACY_METERS', 50),

    'max_check_in_accuracy_meters' => env('PRESENCE_MAX_CHECK_IN_ACCURACY_METERS', 75),
];
