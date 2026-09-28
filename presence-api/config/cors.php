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
    // Bearer token (Sanctum personal access tokens), jamais en cookies. Les
    // origines restent toutefois explicites : un domaine tiers n'a aucune
    // raison d'appeler l'API depuis le navigateur d'un utilisateur.
    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('CORS_ALLOWED_ORIGINS', 'http://localhost:3000,http://localhost:3001')),
    ))),

    'allowed_origins_patterns' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('CORS_ALLOWED_ORIGIN_PATTERNS', '')),
    ))),

    'allowed_headers' => ['*'],

    // Sans ces expositions, le navigateur ne peut lire en cross-origin ni
    // Retry-After sur un 429 (les apps n'afficheraient qu'un « patientez »
    // au lieu du délai réel), ni le nom de fichier d'un PDF téléchargé (qui
    // retomberait sur un nom générique).
    'exposed_headers' => ['Retry-After', 'Content-Disposition'],

    'max_age' => 0,

    'supports_credentials' => false,

];
