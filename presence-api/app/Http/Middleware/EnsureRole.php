<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    /**
     * Autorise la requête si le rôle *effectif* de l'utilisateur (voir
     * User::effectiveRole(), qui tient compte des promotions temporaires
     * actives) figure parmi les rôles autorisés.
     *
     * Usage : ->middleware('role:Enseignant,Admin')
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        $allowed = array_map(fn (string $role) => UserRole::from($role), $roles);

        if (! in_array($user->effectiveRole(), $allowed, true)) {
            abort(403, "Vous n'avez pas accès à cette ressource.");
        }

        return $next($request);
    }
}
