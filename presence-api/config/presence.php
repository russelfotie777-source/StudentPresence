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

    /*
     * Durée de validité d'une session d'administration, en heures.
     *
     * Sanctum n'expire aucun jeton par défaut ('expiration' => null dans
     * config/sanctum.php), et le back-office conserve le sien dans le
     * localStorage du navigateur : sans échéance, un jeton récupéré sur un
     * poste partagé resterait valable indéfiniment, avec les droits les plus
     * élevés de l'application.
     *
     * L'échéance est posée par jeton et non globalement : la même valeur
     * appliquée à toute l'application déconnecterait les étudiants en pleine
     * journée de cours, pour un risque bien moindre.
     */
    'admin_session_hours' => env('PRESENCE_ADMIN_SESSION_HOURS', 12),

    /*
     * En-tête institutionnel des documents officiels (liste de présence
     * hebdomadaire). Le logo se remplace en déposant le fichier officiel à
     * l'emplacement indiqué ; les textes suivent la maquette du département
     * et se surchargent par l'environnement pour un autre département.
     */
    'etablissement' => [
        'logo' => env('PRESENCE_LOGO', resource_path('images/iut-douala.png')),
        'departement_fr' => env('PRESENCE_DEPARTEMENT_FR', 'DEPARTEMENT DE GENIE INFORMATIQUE'),
        'departement_en' => env('PRESENCE_DEPARTEMENT_EN', 'DEPARTMENT OF COMPUTERS SCIENCES'),
        'bp' => env('PRESENCE_BP', '8698 DOUALA'),
        'tel' => env('PRESENCE_TEL', '(237) 233 40 24 82'),
        'email' => env('PRESENCE_EMAIL', 'infos.iut@univ-douala.com'),
    ],
];
