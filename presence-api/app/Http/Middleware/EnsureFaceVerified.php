<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bloque les routes métier tant qu'un compte Étudiant n'a pas terminé la
 * seconde étape (inscription ou vérification faciale) pour cette connexion
 * — reconnaissable au fait que le jeton courant porte uniquement
 * l'habileté "face-pending" plutôt qu'un accès complet ("*").
 *
 * Important : on vérifie la présence explicite de "face-pending" dans le
 * tableau d'habiletés, jamais via $token->can('face-pending') — un jeton
 * complet a l'habileté "*" par défaut, qui répondrait aussi "oui" à
 * can('face-pending') par effet du joker, et laisserait passer à tort.
 */
class EnsureFaceVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $abilities = $request->user()?->currentAccessToken()?->abilities ?? [];

        if (in_array('face-pending', $abilities, true)) {
            abort(403, 'Vérification faciale requise avant d\'accéder à cette ressource.');
        }

        return $next($request);
    }
}
