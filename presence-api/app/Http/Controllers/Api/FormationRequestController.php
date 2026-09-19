<?php

namespace App\Http\Controllers\Api;

use App\Enums\FormationType;
use App\Enums\RequestStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Resources\DemandeFormationResource;
use App\Models\DemandeFormation;
use App\Models\Salle;
use App\Services\MigrationFI;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Demande d'un étudiant FA pour rejoindre l'emploi du temps FI (statut FM à
 * l'approbation). L'étudiant choisit la salle FI qu'il vise — parmi celles
 * de son département et de son niveau (voir MigrationFI) — mais FM n'est
 * jamais un choix de l'étudiant lui-même : c'est l'admin qui valide, peut
 * retenir une autre salle, et bascule effectivement le compte.
 */
class FormationRequestController extends Controller
{
    /**
     * Ce que l'étudiant voit sur l'onglet Migration : sa situation, s'il
     * peut demander, vers quelles salles, et sa demande en attente s'il en a une.
     */
    public function situation(Request $request, MigrationFI $migration)
    {
        abort_unless($request->user()->role === UserRole::Etudiant, 403);

        return response()->json($migration->situation($request->user()));
    }

    public function store(Request $request, MigrationFI $migration)
    {
        $user = $request->user();
        abort_unless($user->role === UserRole::Etudiant, 403);

        if ($empechement = $migration->empechement($user)) {
            throw ValidationException::withMessages(['demande' => [$empechement]]);
        }

        $existing = DemandeFormation::where('etudiant_id', $user->id)
            ->where('statut', RequestStatus::EnAttente)
            ->exists();

        if ($existing) {
            throw ValidationException::withMessages(['demande' => ['Vous avez déjà une demande en attente.']]);
        }

        $data = $request->validate([
            'salle_cible_id' => ['required', 'integer'],
            'motif' => ['sometimes', 'nullable', 'string', 'max:500'],
        ]);

        if (! $migration->sallesEligibles($user)->contains('id', (int) $data['salle_cible_id'])) {
            throw ValidationException::withMessages([
                'salle_cible_id' => ['Choisissez une salle de formation initiale de votre département et de votre niveau.'],
            ]);
        }

        $demande = DemandeFormation::create([
            'etudiant_id' => $user->id,
            'salle_cible_id' => (int) $data['salle_cible_id'],
            'motif' => $data['motif'] ?? null,
            'statut' => RequestStatus::EnAttente,
            'date_creation' => now(),
        ]);

        return response()->json(new DemandeFormationResource($demande->load('salleCible.filiere.niveau')), 201);
    }

    /** L'étudiant retire sa demande tant qu'elle n'a pas été traitée. */
    public function destroy(Request $request, DemandeFormation $demande)
    {
        abort_unless($demande->etudiant_id === $request->user()->id, 403);
        $this->assertPending($demande);

        $demande->delete();

        return response()->noContent();
    }

    public function mine(Request $request)
    {
        abort_unless($request->user()->role === UserRole::Etudiant, 403);

        return DemandeFormationResource::collection(
            DemandeFormation::with(['salleCible.filiere.niveau', 'etudiant.niveau', 'etudiant.filiere'])
                ->where('etudiant_id', $request->user()->id)
                ->latest('date_creation')
                ->get()
        );
    }

    /**
     * Vue admin : toutes les demandes, filtrables par statut.
     */
    public function index(Request $request)
    {
        $data = $request->validate(['statut' => ['sometimes', 'in:en_attente,acceptee,rejetee']]);

        return DemandeFormationResource::collection(
            DemandeFormation::with(['etudiant.salle', 'etudiant.niveau', 'etudiant.filiere', 'salleCible.filiere.niveau'])
                ->when($data['statut'] ?? null, fn ($q, $statut) => $q->where('statut', $statut))
                ->latest('date_creation')
                ->get()
        );
    }

    /**
     * Approbation admin : retient la salle FI demandée par l'étudiant — ou
     * une autre, si l'admin en décide ainsi —, bascule l'étudiant en FM et le
     * rattache à cette salle (donc à son niveau/filière).
     */
    public function approve(Request $request, DemandeFormation $demande)
    {
        $this->assertPending($demande);

        $data = $request->validate(['salle_id' => ['sometimes', 'nullable', 'exists:salles,id']]);
        $salleId = $data['salle_id'] ?? $demande->salle_cible_id;
        abort_unless($salleId, 422, "Aucune salle d'accueil : choisissez la salle FI qui recevra l'étudiant.");

        $salle = Salle::with('filiere')->findOrFail($salleId);
        abort_unless($salle->formation === FormationType::FI, 422, 'La salle cible doit être une salle en Formation Initiale (FI).');

        DB::transaction(function () use ($demande, $salle) {
            $demande->update([
                'statut' => RequestStatus::Acceptee,
                'salle_cible_id' => $salle->id,
                // D'où il vient : sa salle courante devient celle d'accueil,
                // l'origine ne se lirait plus nulle part sans cette trace.
                'salle_origine_id' => $demande->etudiant->salle_id,
                'date_traitement' => now(),
            ]);

            $demande->etudiant->update([
                'formation' => FormationType::FM,
                'salle_id' => $salle->id,
                'filiere_id' => $salle->filiere_id,
                'niveau_id' => $salle->filiere->niveau_id,
            ]);
        });

        return new DemandeFormationResource($demande->fresh(['etudiant.salle', 'salleCible']));
    }

    public function reject(Request $request, DemandeFormation $demande)
    {
        $this->assertPending($demande);

        $data = $request->validate(['commentaire' => ['sometimes', 'nullable', 'string', 'max:1000']]);

        $demande->update([
            'statut' => RequestStatus::Rejetee,
            'date_traitement' => now(),
            'commentaire_admin' => $data['commentaire'] ?? null,
        ]);

        return new DemandeFormationResource($demande->fresh());
    }

    private function assertPending(DemandeFormation $demande): void
    {
        if ($demande->statut !== RequestStatus::EnAttente) {
            throw ValidationException::withMessages(['statut' => ['Cette demande a déjà été traitée.']]);
        }
    }
}
