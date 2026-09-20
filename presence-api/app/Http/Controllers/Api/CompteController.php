<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\CodeEmail;
use App\Models\User;
use App\Services\CodesEmail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Ce qu'un utilisateur peut changer lui-même à ses identifiants : son mot
 * de passe (tout le monde), son numéro de téléphone (l'enseignant, dont
 * c'est l'identifiant de connexion) et son adresse e-mail, confirmée par
 * code. Le matricule, lui, ne se touche pas.
 */
class CompteController extends Controller
{
    public function __construct(private CodesEmail $codes) {}

    /**
     * Le mot de passe initial commun est un secret de polichinelle : tant
     * qu'il n'a pas été remplacé, les routes métier restent fermées (voir
     * EnsureValidated). Changer de mot de passe déconnecte les autres
     * appareils.
     */
    public function changerMotDePasse(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'mot_de_passe_actuel' => ['required', 'current_password:sanctum'],
            'mot_de_passe' => ['required', 'confirmed', Password::min(8), self::pasLeMotDePasseInitial(), function (string $attribut, mixed $valeur, \Closure $echec) use ($user) {
                if (Hash::check((string) $valeur, $user->password)) {
                    $echec('Choisissez un mot de passe différent de l\'actuel.');
                }
            }],
        ], [
            'mot_de_passe_actuel.current_password' => 'Le mot de passe actuel est incorrect.',
        ], ['mot_de_passe' => 'nouveau mot de passe', 'mot_de_passe_actuel' => 'mot de passe actuel']);

        $user->forceFill(['password' => $data['mot_de_passe'], 'doit_changer_mot_de_passe' => false])->save();

        $courant = $user->currentAccessToken();
        $user->tokens()->when($courant instanceof PersonalAccessToken, fn ($q) => $q->whereKeyNot($courant->getKey()))->delete();

        return $this->reponse($user, 'Mot de passe modifié.');
    }

    /** Le numéro est l'identifiant de connexion de l'enseignant : c'est lui, et lui seul, qui le tient à jour. */
    public function changerTelephone(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->role === UserRole::Enseignant, 403, 'Seul un enseignant peut changer son numéro : pour les autres, cet identifiant est le matricule.');

        $data = $request->validate([
            'mot_de_passe_actuel' => ['required', 'current_password:sanctum'],
            'telephone' => ['required', 'string', 'max:20', Rule::unique('users', 'phone')->ignore($user->id)],
        ], [
            'mot_de_passe_actuel.current_password' => 'Le mot de passe actuel est incorrect.',
            'telephone.unique' => 'Ce numéro est déjà utilisé par un autre compte.',
        ], ['telephone' => 'numéro de téléphone']);

        $user->update(['phone' => trim($data['telephone'])]);

        return $this->reponse($user, "Numéro modifié : connectez-vous désormais avec {$user->phone}.");
    }

    /**
     * Déclare une adresse : elle ne devient celle du compte qu'une fois le
     * code reçu confirmé — l'ancienne reste en place d'ici là, et personne
     * ne peut « réserver » l'adresse d'un autre sans y avoir accès.
     */
    public function definirEmail(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'email' => ['required', 'email:rfc', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
        ], ['email.unique' => 'Cette adresse est déjà utilisée par un autre compte.'], ['email' => 'adresse e-mail']);

        $email = mb_strtolower(trim($data['email']));
        if ($user->email === $email && $user->emailVerifie()) {
            throw ValidationException::withMessages(['email' => ['Cette adresse est déjà vérifiée.']]);
        }

        $this->codes->envoyer($user, CodeEmail::VERIFICATION, $email);

        return $this->reponse($user, "Code envoyé à {$email}.");
    }

    /** Renvoie un code à l'adresse en attente, ou à celle du compte si elle n'est pas encore vérifiée. */
    public function renvoyerCode(Request $request): JsonResponse
    {
        $user = $request->user();
        $email = $this->codes->adresseEnAttente($user) ?? ($user->emailVerifie() ? null : $user->email);

        if (! $email) {
            throw ValidationException::withMessages(['email' => ['Aucune adresse à vérifier : indiquez-en une d\'abord.']]);
        }

        $this->codes->envoyer($user, CodeEmail::VERIFICATION, $email);

        return $this->reponse($user, "Code envoyé à {$email}.");
    }

    public function verifierEmail(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate(['code' => ['required', 'digits:6']]);

        $email = $this->codes->confirmer($user, CodeEmail::VERIFICATION, $data['code']);

        if (User::where('email', $email)->whereKeyNot($user->id)->exists()) {
            throw ValidationException::withMessages(['email' => ['Cette adresse vient d\'être prise par un autre compte.']]);
        }

        $user->update(['email' => $email, 'email_verified_at' => now()]);

        return $this->reponse($user, 'Adresse vérifiée.');
    }

    /** Le mot de passe initial, remis avec l'identifiant, ne peut pas être « choisi » une seconde fois. */
    public static function pasLeMotDePasseInitial(): \Closure
    {
        return function (string $attribut, mixed $valeur, \Closure $echec) {
            if ((string) $valeur === (string) config('presence.mot_de_passe_initial')) {
                $echec('Ce mot de passe est celui remis à la création du compte : choisissez-en un autre.');
            }
        };
    }

    private function reponse(User $user, string $message): JsonResponse
    {
        return response()->json([
            'message' => $message,
            'user' => new UserResource($user->fresh()->loadForResource()),
            'email_en_attente' => $this->codes->adresseEnAttente($user),
        ]);
    }
}
