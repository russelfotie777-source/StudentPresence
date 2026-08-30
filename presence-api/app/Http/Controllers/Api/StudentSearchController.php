<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class StudentSearchController extends Controller
{
    /**
     * Recherche d'étudiants par nom/salle/filière/niveau — sert au formulaire
     * de promotion temporaire de l'enseignant. Reprend la recherche "live"
     * de promotion_temporaire.php.
     */
    public function index(Request $request)
    {
        abort_unless($request->user()->role === UserRole::Enseignant, 403);

        $data = $request->validate(['search' => ['sometimes', 'string', 'max:100']]);
        $search = $data['search'] ?? '';

        $students = User::query()
            ->where('role', UserRole::Etudiant->value)
            ->with(['salle', 'filiere', 'niveau'])
            ->when(strlen($search) > 0, fn ($q) => $q->where(function ($q2) use ($search) {
                $q2->where('name', 'like', "%{$search}%")
                    ->orWhereHas('salle', fn ($q3) => $q3->where('nom', 'like', "%{$search}%"))
                    ->orWhereHas('filiere', fn ($q3) => $q3->where('nom', 'like', "%{$search}%"))
                    ->orWhereHas('niveau', fn ($q3) => $q3->where('nom', 'like', "%{$search}%"));
            }))
            ->orderBy('name')
            ->limit(30)
            ->get();

        return $students->map(fn (User $u) => [
            'id' => $u->id,
            'name' => $u->name,
            'salle' => $u->salle?->nom,
            'filiere' => $u->filiere?->nom,
            'niveau' => $u->niveau?->nom,
            'has_active_promotion' => $u->hasActivePromotion(),
        ]);
    }
}
