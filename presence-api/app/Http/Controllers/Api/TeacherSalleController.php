<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Salle;
use Illuminate\Http\Request;

class TeacherSalleController extends Controller
{
    /**
     * Salles où l'enseignant connecté a au moins une séance — alimente le
     * sélecteur de salle du formulaire de promotion temporaire côté
     * enseignant (un enseignant n'a pas de salle_id propre, contrairement
     * à l'étudiant/délégué).
     */
    public function mine(Request $request)
    {
        $user = $request->user();
        abort_unless($user->effectiveRole() === UserRole::Enseignant, 403);

        return Salle::query()
            ->whereIn('id', $user->salleIdsEnseignees())
            ->with('filiere')
            ->orderBy('nom')
            ->get()
            ->map(fn (Salle $s) => [
                'id' => $s->id,
                'nom' => $s->nom,
                'filiere' => $s->filiere?->nom,
                'formation' => $s->formation?->value,
            ]);
    }
}
