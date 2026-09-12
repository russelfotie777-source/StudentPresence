<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Notifications de la personne connectée (canal database de Laravel).
 * Lues à l'ouverture de l'app : c'est ainsi qu'un étudiant apprend qu'un
 * admin a restreint ou rétabli son compte.
 */
class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $notifications = $request->user()
            ->notifications()
            ->latest()
            ->limit(30)
            ->get()
            ->map(fn ($n) => [
                'id' => $n->id,
                ...$n->data,
                'lue' => $n->read_at !== null,
                'date' => $n->created_at->toIso8601String(),
            ]);

        return response()->json([
            'notifications' => $notifications,
            'non_lues' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    public function marquerLue(Request $request, string $id): JsonResponse
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        return response()->json(['non_lues' => $request->user()->unreadNotifications()->count()]);
    }
}
