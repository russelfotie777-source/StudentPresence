<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Abonnements Web Push de la personne connectée : le navigateur (ou la PWA
 * installée) nous confie l'adresse à laquelle lui pousser les rappels de
 * pointage. Un appareil = un abonnement ; la clé publique VAPID permet au
 * navigateur de vérifier que c'est bien ce serveur qui pousse.
 */
class PushSubscriptionController extends Controller
{
    public function clePublique(): JsonResponse
    {
        $cle = config('webpush.vapid.public_key');

        return response()->json([
            'cle' => $cle ?: null,
            'disponible' => (bool) ($cle && config('webpush.vapid.private_key')),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'url', 'max:2000'],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth' => ['required', 'string', 'max:255'],
            'content_encoding' => ['sometimes', 'nullable', 'string', 'max:20'],
        ]);

        $request->user()->updatePushSubscription(
            $data['endpoint'],
            $data['keys']['p256dh'],
            $data['keys']['auth'],
            $data['content_encoding'] ?? 'aesgcm',
        );

        return response()->json(['abonne' => true], 201);
    }

    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate(['endpoint' => ['required', 'url', 'max:2000']]);

        $request->user()->deletePushSubscription($data['endpoint']);

        return response()->json(['abonne' => false]);
    }
}
