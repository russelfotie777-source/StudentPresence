<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Parametre;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Réglage admin des grades soumis au second facteur facial. Permet de
 * lever le blocage immédiatement si un compte n'arrive pas à inscrire son
 * visage (caméra absente, mauvaise lumière), sans redéploiement.
 */
class FaceAuthSettingController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json($this->payload());
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'roles' => ['present', 'array'],
            'roles.*' => ['string', Rule::in($this->rolesReglables())],
        ], [
            'roles.*.in' => "L'Admin ne peut pas être soumis à la reconnaissance faciale.",
        ]);

        Parametre::setFaceAuthRoles($data['roles']);

        return response()->json($this->payload());
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        return [
            'roles' => Parametre::faceAuthRoles(),
            'roles_reglables' => $this->rolesReglables(),
        ];
    }

    /**
     * Tous les grades sauf Admin : le soumettre au facial le priverait du
     * seul écran permettant de désactiver ce réglage.
     *
     * @return array<int, string>
     */
    private function rolesReglables(): array
    {
        return array_values(array_diff(
            array_column(UserRole::cases(), 'value'),
            [UserRole::Admin->value],
        ));
    }
}
