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
     *
     * Ouvert à l'enseignant ET au délégué (y compris un étudiant
     * actuellement promu, via effectiveRole()) — chacun ne peut désigner
     * un remplaçant que parmi les étudiants d'une salle qu'il est
     * légitime à représenter : sa propre salle pour un délégué, une salle
     * où il enseigne réellement pour un enseignant.
     */
    public function store(Request $request)
    {
        $user = $request->user();
        $effectiveRole = $user->effectiveRole();
        abort_unless(in_array($effectiveRole, [UserRole::Enseignant, UserRole::Delegue], true), 403);

        $rules = [
            'etudiant_id' => ['required', Rule::exists('users', 'id')->where('role', UserRole::Etudiant->value)],
            'duree_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
        ];
        if ($effectiveRole === UserRole::Enseignant) {
            $rules['salle_id'] = ['required', 'integer', 'exists:salles,id'];
        }

        $data = $request->validate($rules, [
            'salle_id.required' => 'Vous devez sélectionner une salle.',
        ]);

        $etudiant = User::findOrFail($data['etudiant_id']);

        if ($effectiveRole === UserRole::Enseignant) {
            if (! $user->enseigneDansSalle((int) $data['salle_id'])) {
                throw ValidationException::withMessages([
                    'salle_id' => ["Vous n'enseignez pas dans cette salle."],
                ]);
            }

            if ($etudiant->salle_id !== (int) $data['salle_id']) {
                throw ValidationException::withMessages([
                    'etudiant_id' => ['Cet étudiant n\'appartient pas à la salle sélectionnée.'],
                ]);
            }
        } else {
            // Délégué (titulaire ou étudiant actuellement promu) : jamais de
            // salle_id client — seule sa propre salle fait foi.
            if ($etudiant->salle_id !== $user->salle_id) {
                throw ValidationException::withMessages([
                    'etudiant_id' => ["Cet étudiant n'appartient pas à votre salle."],
                ]);
            }
        }

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
        $user = $request->user();
        $effectiveRole = $user->effectiveRole();
        abort_unless(in_array($effectiveRole, [UserRole::Enseignant, UserRole::Delegue], true), 403);

        return PromotionTemporaire::with('etudiant.salle')
            ->where('date_fin', '>', now())
            ->when(
                $effectiveRole === UserRole::Delegue,
                fn ($q) => $q->whereHas('etudiant', fn ($q2) => $q2->where('salle_id', $user->salle_id))
            )
            ->latest('date_debut')
            ->get();
    }
}
