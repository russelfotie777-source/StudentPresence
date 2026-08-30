<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Enums\ValidationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Inscription. Les Étudiants sont auto-connectés immédiatement (comme
     * dans l'ancienne app) ; Délégué/Enseignant doivent se connecter
     * manuellement puis passer par le flux de validation.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $role = UserRole::from($request->string('role')->value());

        $user = User::create([
            'name' => $request->string('name')->value(),
            'phone' => $request->string('phone')->value(),
            'password' => Hash::make($request->string('password')->value()),
            'role' => $role,
            // Les étudiants n'ont jamais de flux de validation à traverser ;
            // Délégué/Enseignant démarrent à "none" (voir requestValidation()).
            'validation_status' => $role === UserRole::Etudiant ? ValidationStatus::Approved : ValidationStatus::None,
            'formation' => $request->input('formation'),
            'salle_id' => $request->input('salle_id'),
            'niveau_id' => $request->input('niveau_id'),
            'filiere_id' => $request->input('filiere_id'),
        ]);

        if ($role !== UserRole::Etudiant) {
            return response()->json([
                'message' => 'Inscription réussie. Vous pouvez maintenant vous connecter.',
                'user' => new UserResource($user),
            ], 201);
        }

        $token = $user->createToken('presence-app')->plainTextToken;

        return response()->json([
            'user' => new UserResource($user->load(['salle', 'niveau', 'filiere'])),
            'token' => $token,
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('phone', $request->string('phone')->value())->first();

        if (! $user || ! Hash::check($request->string('password')->value(), $user->password)) {
            throw ValidationException::withMessages([
                'phone' => ['Identifiants incorrects.'],
            ]);
        }

        // Le token est toujours émis, y compris pour un compte Délégué/Enseignant
        // "none"/"pending" — il sert alors uniquement à consulter son statut et
        // soumettre sa validation. Les routes métier protégées exigent en plus
        // le middleware `validated`, qui les bloquera tant que validation_status
        // n'est pas "approved". Le front doit lire validation_status et
        // rediriger vers l'écran d'attente plutôt que le dashboard.
        $token = $user->createToken('presence-app')->plainTextToken;

        return response()->json([
            'user' => new UserResource($user->load(['salle', 'niveau', 'filiere'])),
            'token' => $token,
        ]);
    }

    /**
     * Démarre le flux de validation pour un Délégué/Enseignant : none -> pending.
     * Équivalent du POST de l'ancienne validation.php / validation_enseignant_user.php.
     */
    public function requestValidation(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! in_array($user->role, [UserRole::Delegue, UserRole::Enseignant], true)) {
            abort(422, "Ce compte n'a pas de flux de validation.");
        }

        if ($user->validation_status !== ValidationStatus::None) {
            abort(422, 'Une demande de validation est déjà en cours ou traitée.');
        }

        $user->update(['validation_status' => ValidationStatus::Pending]);

        return response()->json(['user' => new UserResource($user)]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => new UserResource($request->user()->load(['salle', 'niveau', 'filiere'])),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Déconnecté.']);
    }
}
