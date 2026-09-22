<?php

return [
    /*
     * Distance maximale (en mètres) entre un étudiant et la position GPS
     * envoyée par le délégué pour qu'un pointage de présence soit accepté.
     * L'ancienne app affichait "100m" à l'étudiant, son commentaire de code
     * disait "650m", et la valeur réellement appliquée était 2550m — aucun
     * des trois n'était le bon. Valeur retenue pour la v2 : 120m.
     *
     * Ce rayon s'entend entre les deux vraies positions, que personne ne
     * connaît exactement : chaque mesure vient avec son incertitude. Le
     * doute profite à l'étudiant — il est refusé seulement si, même en
     * prenant les deux mesures au plus favorable, il reste en dehors du
     * rayon (distance mesurée > rayon + incertitude de l'étudiant +
     * incertitude du délégué). Deux bons GPS à ±15 m gardent un périmètre
     * serré ; deux mesures Wi-Fi à ±300 m l'élargissent d'autant, mais
     * laissent pointer depuis la salle.
     */
    'max_check_in_distance_meters' => env('PRESENCE_MAX_DISTANCE_METERS', 120),

    /*
     * Incertitude maximale (coords.accuracy du navigateur, en mètres) au-delà
     * de laquelle une position est refusée plutôt qu'utilisée.
     *
     * Le pointage doit marcher depuis la salle, avec le téléphone qu'on a :
     * dans un bâtiment en béton, sans fenêtre, le GPS plafonne souvent
     * entre 100 et 500 m, et l'app ne propose pas d'appel manuel en
     * secours. On n'écarte donc que les mesures qui ne viennent pas du
     * téléphone lui-même — la localisation coupée, le navigateur rend un
     * point d'après l'adresse IP, à plusieurs kilomètres près. En dessous
     * de ce plafond, c'est le contrôle de distance qui tient compte de
     * l'incertitude (voir max_check_in_distance_meters).
     */
    'max_position_accuracy_meters' => env('PRESENCE_MAX_POSITION_ACCURACY_METERS', 2000),

    'max_check_in_accuracy_meters' => env('PRESENCE_MAX_CHECK_IN_ACCURACY_METERS', 2000),

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
     * l'emplacement indiqué ; les coordonnées sont celles de l'établissement.
     * Le nom du département, lui, n'est plus ici : chaque liste porte celui
     * de la salle imprimée (voir App\Models\Departement::enteteFr).
     */
    'etablissement' => [
        'logo' => env('PRESENCE_LOGO', resource_path('images/iut-douala.png')),
        'bp' => env('PRESENCE_BP', '8698 DOUALA'),
        'tel' => env('PRESENCE_TEL', '(237) 233 40 24 82'),
        'email' => env('PRESENCE_EMAIL', 'infos.iut@univ-douala.com'),
    ],

    /*
     * Mot de passe initial de tout compte créé par l'administration ou par
     * l'assistant (étudiant inscrit, enseignant cité par un emploi du
     * temps). Le même pour tous, communiqué avec l'identifiant ; la personne
     * le changera elle-même — parcours prévu plus tard, avec vérification
     * par e-mail.
     */
    'mot_de_passe_initial' => env('PRESENCE_MOT_DE_PASSE_INITIAL', '12345678'),

    /*
     * Identifiant de connexion provisoire d'un enseignant créé sans numéro
     * de téléphone (un emploi du temps ne le donne jamais) : ce préfixe
     * suivi d'un numéro d'ordre, ENS0001, ENS0002…
     */
    'prefixe_identifiant_enseignant' => env('PRESENCE_PREFIXE_ENSEIGNANT', 'ENS'),

    /*
     * Migration FA → FI : un étudiant en alternance peut demander à suivre
     * les cours de jour dans une salle FI de son département et de son
     * niveau. Ouverte aux premières années seulement — en troisième année,
     * les parcours sont trop différents pour changer de formation en route.
     */
    'migration' => [
        'niveau_max' => env('PRESENCE_MIGRATION_NIVEAU_MAX', 2),
    ],

    /*
     * Rappels de pointage (notification dans l'app + push si des clés VAPID
     * sont configurées). Envoyés par `php artisan presence:rappels`, lancé
     * chaque minute par le planificateur (voir routes/console.php).
     *
     * - delegue_avant_debut_minutes : combien de minutes avant le début on
     *   rappelle au délégué d'envoyer la position de la salle. 15 = à
     *   l'ouverture de la fenêtre de pointage (Seance::isActive).
     * - derniere_chance_avant_fermeture_minutes : combien de minutes avant
     *   la fermeture du pointage (fin + 15 min) on relance ceux qui n'ont
     *   pas pointé. 15 = à l'heure de fin prévue de la séance.
     */
    'rappels' => [
        'delegue_avant_debut_minutes' => env('PRESENCE_RAPPEL_DELEGUE_MINUTES', 15),
        'derniere_chance_avant_fermeture_minutes' => env('PRESENCE_RAPPEL_DERNIERE_CHANCE_MINUTES', 15),
    ],
];
