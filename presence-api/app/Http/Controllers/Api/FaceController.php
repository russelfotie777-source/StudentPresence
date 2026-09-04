<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Second facteur d'authentification, réservé aux Étudiants : après un
 * login réussi par mot de passe, un jeton "en attente" (habileté
 * face-pending) est émis — voir AuthController::authPayload. Ce contrôleur
 * échange ce jeton contre un jeton complet, une fois le visage inscrit
 * (première connexion) ou vérifié (fois suivantes).
 *
 * Objectif : empêcher un étudiant de pointer sa présence GPS à la place
 * d'un camarade en connaissant simplement son mot de passe.
 *
 * Tout le calcul se fait côté navigateur (TensorFlow.js dans presence-app) :
 * ce contrôleur ne reçoit et ne compare qu'un tableau de 128 nombres (le
 * "descripteur" du visage), jamais une image — reste minuscule à stocker
 * et à comparer, aucun traitement lourd nécessaire côté serveur mutualisé.
 */
class FaceController extends Controller
{
    private const DESCRIPTOR_LENGTH = 128;

    // Distance euclidienne maximale entre deux descripteurs pour les
    // considérer comme le même visage — seuil usuel du modèle
    // FaceRecognitionNet (face-api.js) pour ce type de comparaison.
    private const MATCH_THRESHOLD = 0.55;

    public function enroll(Request $request): JsonResponse
    {
        $user = $this->assertPendingFaceToken($request);

        if ($user->hasFaceEnrolled()) {
            abort(422, 'Un visage est déjà enregistré pour ce compte.');
        }

        $descriptor = $this->validatedDescriptor($request);

        $user->update([
            'face_descriptor' => $descriptor,
            'face_enrolled_at' => now(),
        ]);

        return $this->issueFullToken($request, $user);
    }

    public function verify(Request $request): JsonResponse
    {
        $user = $this->assertPendingFaceToken($request);

        if (! $user->hasFaceEnrolled()) {
            abort(422, "Aucun visage enregistré pour ce compte, l'inscription doit être faite d'abord.");
        }

        $descriptor = $this->validatedDescriptor($request);
        $distance = $this->euclideanDistance($user->face_descriptor, $descriptor);

        if ($distance > self::MATCH_THRESHOLD) {
            throw ValidationException::withMessages([
                'descriptor' => ['Le visage ne correspond pas à ce compte.'],
            ]);
        }

        return $this->issueFullToken($request, $user, ['distance' => $distance]);
    }

    /**
     * @return array<int, float>
     */
    private function validatedDescriptor(Request $request): array
    {
        $data = $request->validate([
            'descriptor' => ['required', 'array', 'size:'.self::DESCRIPTOR_LENGTH],
            'descriptor.*' => ['numeric'],
        ]);

        return $data['descriptor'];
    }

    /**
     * @param  array<int, float>  $a
     * @param  array<int, float>  $b
     */
    private function euclideanDistance(array $a, array $b): float
    {
        $sum = 0.0;
        foreach ($a as $i => $value) {
            $sum += ($value - $b[$i]) ** 2;
        }

        return round(sqrt($sum), 4);
    }

    /**
     * Seul un jeton émis en attente de vérification faciale (habileté
     * exclusive "face-pending") peut appeler enroll()/verify() — un jeton
     * complet (habileté par défaut "*") est refusé ici, ce qui empêche
     * aussi bien un compte déjà pleinement connecté qu'un jeton d'un autre
     * rôle de s'en servir pour, par exemple, réinscrire un visage à volonté.
     */
    private function assertPendingFaceToken(Request $request): User
    {
        $abilities = $request->user()?->currentAccessToken()?->abilities ?? [];

        abort_unless(in_array('face-pending', $abilities, true), 403, 'Jeton invalide pour cette action.');

        return $request->user();
    }

    private function issueFullToken(Request $request, User $user, array $extra = []): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        $token = $user->createToken('presence-app')->plainTextToken;

        return response()->json(array_merge([
            'user' => new UserResource($user->load(['salle', 'niveau', 'filiere'])),
            'token' => $token,
        ], $extra));
    }
}
