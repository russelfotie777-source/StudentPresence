<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CodeEmail;
use App\Models\User;
use App\Services\CodesEmail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/**
 * Mot de passe oublié, sans passer par l'administration : un code part à
 * l'adresse vérifiée du compte, et le code plus un nouveau mot de passe
 * suffisent. Sans adresse vérifiée, il reste l'admin (voir
 * ReinitialisationController).
 */
class MotDePasseOublieController extends Controller
{
    public function __construct(private CodesEmail $codes) {}

    public function demander(Request $request): JsonResponse
    {
        $data = $request->validate(['phone' => ['required', 'string']], [], ['phone' => 'identifiant']);
        $user = User::where('phone', trim($data['phone']))->first();

        // Le matricule figure sur toutes les listes : dire qu'un compte n'a pas
        // d'adresse ne révèle rien d'utile, et évite d'attendre un e-mail qui
        // ne viendra jamais.
        if (! $user || ! $user->emailVerifie()) {
            throw ValidationException::withMessages(['phone' => ["Aucune adresse e-mail vérifiée n'est associée à cet identifiant. Adressez-vous à l'administration pour réinitialiser votre mot de passe."]]);
        }

        $this->codes->envoyer($user, CodeEmail::REINITIALISATION, $user->email);

        return response()->json([
            'message' => 'Code envoyé à '.CodesEmail::masquer($user->email).'.',
            'email_masque' => CodesEmail::masquer($user->email),
        ]);
    }

    public function reinitialiser(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string'],
            'code' => ['required', 'digits:6'],
            'mot_de_passe' => ['required', 'confirmed', Password::min(8), CompteController::pasLeMotDePasseInitial()],
        ], [], ['phone' => 'identifiant', 'mot_de_passe' => 'nouveau mot de passe']);

        $user = User::where('phone', trim($data['phone']))->first()
            ?? throw ValidationException::withMessages(['code' => ['Aucun code en attente : demandez-en un nouveau.']]);

        $this->codes->confirmer($user, CodeEmail::REINITIALISATION, $data['code']);

        $user->forceFill(['password' => $data['mot_de_passe'], 'doit_changer_mot_de_passe' => false])->save();
        $user->tokens()->delete();

        return response()->json(['message' => 'Mot de passe modifié : vous pouvez vous connecter.']);
    }
}
