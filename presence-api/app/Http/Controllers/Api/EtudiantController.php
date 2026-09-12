<?php

namespace App\Http\Controllers\Api;

use App\Enums\StatutCompte;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\Salle;
use App\Models\User;
use App\Notifications\StatutCompteModifie;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Gestion des comptes étudiants par l'admin : liste, rattachement à une
 * salle, restriction, blocage, suppression. Les délégués sont des étudiants
 * (un étudiant promu), ils sont gérés ici au même titre.
 */
class EtudiantController extends Controller
{
    private const PAR_PAGE = 20;

    public function index(Request $request)
    {
        $data = $request->validate([
            'search' => ['sometimes', 'nullable', 'string', 'max:100'],
            'salle_id' => ['sometimes', 'nullable', 'integer', 'exists:salles,id'],
            'statut' => ['sometimes', 'nullable', Rule::enum(StatutCompte::class)],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $search = trim((string) ($data['search'] ?? ''));

        $etudiants = User::query()
            ->whereIn('role', [UserRole::Etudiant->value, UserRole::Delegue->value])
            ->with(['salle', 'filiere', 'niveau'])
            ->withActivePromotions()
            ->when($data['salle_id'] ?? null, fn ($q, $salleId) => $q->where('salle_id', $salleId))
            ->when($data['statut'] ?? null, fn ($q, $statut) => $q->where('statut_compte', $statut))
            ->when($search !== '', fn ($q) => $q->where(function ($q2) use ($search) {
                $q2->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            }))
            ->orderBy('name')
            ->paginate($data['per_page'] ?? self::PAR_PAGE);

        return UserResource::collection($etudiants);
    }

    /**
     * Rattache l'étudiant à une autre salle. La filière, le niveau et la
     * formation suivent la salle — ce sont des propriétés de la salle, pas
     * de l'étudiant, et les laisser diverger fausserait le roster et la
     * liste de présence. Même logique que l'approbation d'une migration.
     */
    public function changerSalle(Request $request, User $etudiant): JsonResponse
    {
        $this->assertEtudiant($etudiant);

        $data = $request->validate(['salle_id' => ['required', 'integer', 'exists:salles,id']]);

        $salle = Salle::with('filiere')->findOrFail($data['salle_id']);

        if ($salle->id === $etudiant->salle_id) {
            throw ValidationException::withMessages([
                'salle_id' => ['Cet étudiant est déjà dans cette salle.'],
            ]);
        }

        $etudiant->update([
            'salle_id' => $salle->id,
            'filiere_id' => $salle->filiere_id,
            'niveau_id' => $salle->filiere->niveau_id,
            'formation' => $salle->formation,
        ]);

        return response()->json(new UserResource($etudiant->fresh()->loadForResource()));
    }

    public function restreindre(Request $request, User $etudiant): JsonResponse
    {
        return $this->changerStatut($request, $etudiant, StatutCompte::Restreint);
    }

    public function bloquer(Request $request, User $etudiant): JsonResponse
    {
        return $this->changerStatut($request, $etudiant, StatutCompte::Bloque);
    }

    public function retablir(Request $request, User $etudiant): JsonResponse
    {
        return $this->changerStatut($request, $etudiant, StatutCompte::Actif);
    }

    public function destroy(User $etudiant): JsonResponse
    {
        $this->assertEtudiant($etudiant);

        $etudiant->tokens()->delete();
        $etudiant->delete();

        return response()->json(null, 204);
    }

    private function changerStatut(Request $request, User $etudiant, StatutCompte $statut): JsonResponse
    {
        $this->assertEtudiant($etudiant);

        $data = $request->validate([
            'motif' => [$statut === StatutCompte::Actif ? 'nullable' : 'required', 'string', 'max:500'],
        ]);

        if ($etudiant->statut_compte === $statut) {
            throw ValidationException::withMessages([
                'statut' => ['Ce compte est déjà dans cet état.'],
            ]);
        }

        DB::transaction(function () use ($etudiant, $statut, $data) {
            $etudiant->update([
                'statut_compte' => $statut,
                'motif_statut' => $data['motif'] ?? null,
                'statut_modifie_le' => now(),
            ]);

            // Un compte bloqué ne doit pas garder une session ouverte : le
            // refus à la connexion ne suffirait pas à quelqu'un déjà connecté.
            if ($statut === StatutCompte::Bloque) {
                $etudiant->tokens()->delete();
            }

            $etudiant->notify(new StatutCompteModifie($statut, $data['motif'] ?? null));
        });

        return response()->json(new UserResource($etudiant->fresh()->loadForResource()));
    }

    private function assertEtudiant(User $user): void
    {
        abort_unless(
            in_array($user->role, [UserRole::Etudiant, UserRole::Delegue], true),
            422,
            'Ce compte n\'est pas un compte étudiant.',
        );
    }
}
