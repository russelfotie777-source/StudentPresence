<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Enums\ValidationStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Validation admin des comptes Délégué/Enseignant. L'ancienne app avait DEUX
 * implémentations concurrentes de ce flux par rôle : une version paramétrée
 * sûre (validate.php/validate_teacher.php) et une version dupliquée en SQL
 * concaténé — donc injectable — (validation.php/validation_enseignant.php),
 * cette dernière étant celle réellement liée depuis le menu admin pour les
 * enseignants. Ici il n'y en a plus qu'une, paramétrée par construction
 * (Eloquent), pour les deux rôles.
 */
class AccountValidationController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'role' => ['sometimes', 'in:Delegue,Enseignant'],
            'statut' => ['sometimes', 'in:none,pending,approved'],
        ]);

        $users = User::query()
            ->whereIn('role', $data['role'] ?? null ? [$data['role']] : [UserRole::Delegue->value, UserRole::Enseignant->value])
            ->when($data['statut'] ?? null, fn ($q, $statut) => $q->where('validation_status', $statut))
            ->with(['salle', 'niveau', 'filiere'])
            ->orderByDesc('created_at')
            ->get();

        return UserResource::collection($users);
    }

    public function approve(User $user)
    {
        $this->assertValidatable($user);
        $user->update(['validation_status' => ValidationStatus::Approved]);

        return new UserResource($user);
    }

    public function reject(User $user)
    {
        $this->assertValidatable($user);
        $user->update(['validation_status' => ValidationStatus::None]);

        return new UserResource($user);
    }

    private function assertValidatable(User $user): void
    {
        abort_unless(in_array($user->role, [UserRole::Delegue, UserRole::Enseignant], true), 422, 'Ce compte ne passe pas par un flux de validation.');
    }
}
