<?php

namespace App\Http\Middleware;

use App\Enums\ValidationStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureValidated
{
    /**
     * Bloque l'accès aux routes métier tant que le compte (Délégué/Enseignant)
     * n'est pas "approved". Les Étudiants sont toujours "approved" dès
     * l'inscription (voir AuthController::register) donc ne sont jamais
     * bloqués ici.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        if ($user->validation_status !== ValidationStatus::Approved) {
            abort(403, match ($user->validation_status) {
                ValidationStatus::None => 'Votre compte doit être soumis pour validation.',
                ValidationStatus::Pending => 'Votre compte est en attente de validation par un administrateur.',
                default => 'Compte non validé.',
            });
        }

        return $next($request);
    }
}
