<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;

/**
 * Le filet de l'admin pour qui a tout perdu (mot de passe oublié, pas
 * d'adresse vérifiée) : le compte revient au mot de passe initial, à
 * remplacer dès la prochaine connexion, et tous ses appareils sont
 * déconnectés.
 */
class ReinitialisationController extends Controller
{
    public function motDePasse(User $utilisateur): JsonResponse
    {
        abort_if($utilisateur->role === UserRole::Admin, 403, 'Le mot de passe d\'un administrateur se change depuis la console.');

        $initial = (string) config('presence.mot_de_passe_initial');
        $utilisateur->forceFill(['password' => $initial, 'doit_changer_mot_de_passe' => true])->save();
        $utilisateur->tokens()->delete();

        return response()->json([
            'message' => "Mot de passe de {$utilisateur->name} remis à {$initial} : à remplacer à sa prochaine connexion.",
            'mot_de_passe_initial' => $initial,
        ]);
    }
}
