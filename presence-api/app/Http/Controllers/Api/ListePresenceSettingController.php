<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Parametre;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Réglage admin des listes de présence générées : présence/absence notées
 * par une coche et une croix rouge, ou par +1 / −1. Partagé par la grille
 * à l'écran et le PDF, pour que l'un annonce exactement l'autre.
 */
class ListePresenceSettingController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json($this->payload());
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'symboles' => ['required', Rule::in(Parametre::SYMBOLES_PRESENCE_CHOIX)],
        ]);

        Parametre::setSymbolesPresence($data['symboles']);

        return response()->json($this->payload());
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        return [
            'symboles' => Parametre::symbolesPresence(),
            'choix' => Parametre::SYMBOLES_PRESENCE_CHOIX,
        ];
    }
}
