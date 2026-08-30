<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\PromotionTemporaire;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PromotionController extends Controller
{
    /**
     * Promotion temporaire d'un étudiant en délégué — reprend
     * promotion_temporaire.php. Corrige un vrai bug de l'ancienne app : ici
     * le serveur refuse une promotion si une autre est déjà active pour cet
     * étudiant (l'ancienne app ne l'empêchait que côté UI, un POST direct
     * pouvait créer des promotions qui se chevauchent).
     */
    public function store(Request $request)
    {
        $user = $request->user();
        abort_unless($user->role === UserRole::Enseignant, 403);

        $data = $request->validate([
            'etudiant_id' => ['required', Rule::exists('users', 'id')->where('role', UserRole::Etudiant->value)],
            'duree_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
        ]);

        $etudiant = User::findOrFail($data['etudiant_id']);

        if ($etudiant->hasActivePromotion()) {
            throw ValidationException::withMessages([
                'etudiant_id' => ['Cet étudiant a déjà une promotion active.'],
            ]);
        }

        $promotion = PromotionTemporaire::create([
            'etudiant_id' => $etudiant->id,
            'promoteur_id' => $user->id,
            'date_debut' => now(),
            'date_fin' => now()->addMinutes($data['duree_minutes']),
            'duree_minutes' => $data['duree_minutes'],
        ]);

        return response()->json($promotion, 201);
    }

    public function index(Request $request)
    {
        abort_unless($request->user()->role === UserRole::Enseignant, 403);

        return PromotionTemporaire::with('etudiant.salle')
            ->where('date_fin', '>', now())
            ->latest('date_debut')
            ->get();
    }
}
