<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Parametre;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Règles de pointage réglables par l'admin. Première règle : le délégué
 * peut-il confirmer la présence de l'enseignant à sa place, pour les
 * enseignants qui n'utilisent pas l'application.
 */
class PointageSettingController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json($this->payload());
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'delegue_confirme_enseignant' => ['required', 'boolean'],
        ]);

        Parametre::setDelegueConfirmeEnseignant($data['delegue_confirme_enseignant']);

        return response()->json($this->payload());
    }

    /**
     * @return array<string, bool>
     */
    private function payload(): array
    {
        return [
            'delegue_confirme_enseignant' => Parametre::delegueConfirmeEnseignant(),
        ];
    }
}
