<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    // presence-app et presence-admin (Next.js/Vercel) parlent à cette API en
    // Bearer token (Sanctum personal access tokens), jamais en cookies — pas
    // de session cross-domaine à sécuriser ici, donc une origine "*" avec
    // supports_credentials=false est volontairement laissée telle quelle
    // (pas besoin d'énumérer les domaines Vercel, y compris les previews).
    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => ['*'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    // Sans ces expositions, le navigateur ne peut lire en cross-origin ni
    // Retry-After sur un 429 (les apps n'afficheraient qu'un « patientez »
    // au lieu du délai réel), ni le nom de fichier d'un PDF téléchargé (qui
    // retomberait sur un nom générique).
    'exposed_headers' => ['Retry-After', 'Content-Disposition'],

    'max_age' => 0,

    'supports_credentials' => false,

];
