<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\PromotionTemporaire;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class StudentSearchController extends Controller
{
    /**
     * Recherche d'étudiants par nom, bornée à une salle — sert au
     * formulaire de promotion temporaire (enseignant ou délégué). Reprend
     * la recherche "live" de promotion_temporaire.php, désormais restreinte
     * à une seule salle par appel : celle du délégué lui-même (jamais un
     * salle_id client, c'est une frontière de sécurité), ou une salle
     * choisie par l'enseignant parmi celles où il enseigne réellement.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $effectiveRole = $user->effectiveRole();
        abort_unless(in_array($effectiveRole, [UserRole::Enseignant, UserRole::Delegue], true), 403);

        $rules = ['search' => ['sometimes', 'nullable', 'string', 'max:100']];
        if ($effectiveRole === UserRole::Enseignant) {
            $rules['salle_id'] = ['required', 'integer', 'exists:salles,id'];
        }

        $data = $request->validate($rules, [
            'salle_id.required' => 'Vous devez sélectionner une salle.',
        ]);
        $search = $data['search'] ?? '';

        if ($effectiveRole === UserRole::Enseignant) {
            if (! $user->enseigneDansSalle((int) $data['salle_id'])) {
                throw ValidationException::withMessages([
                    'salle_id' => ["Vous n'enseignez pas dans cette salle."],
                ]);
            }
            $salleId = (int) $data['salle_id'];
        } else {
            $salleId = $user->salle_id;
        }

        $students = User::query()
            ->where('role', UserRole::Etudiant->value)
            ->where('salle_id', $salleId)
            ->with(['salle', 'filiere', 'niveau'])
            ->when(strlen($search) > 0, fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ->orderBy('name')
            ->get();

        $activePromotionIds = PromotionTemporaire::where('date_fin', '>', now())
            ->whereIn('etudiant_id', $students->pluck('id'))
            ->pluck('etudiant_id')
            ->all();

        return $students->map(fn (User $u) => [
            'id' => $u->id,
            'name' => $u->name,
            'salle' => $u->salle?->nom,
            'filiere' => $u->filiere?->nom,
            'niveau' => $u->niveau?->nom,
            'has_active_promotion' => in_array($u->id, $activePromotionIds, true),
        ]);
    }
}
