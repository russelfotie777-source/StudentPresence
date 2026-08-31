<?php

namespace App\Http\Controllers\Api;

use App\Enums\FormationType;
use App\Enums\RequestStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Resources\DemandeFormationResource;
use App\Models\DemandeFormation;
use App\Models\Salle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Demande d'un étudiant FA pour rejoindre l'emploi du temps FI (statut FM à
 * l'approbation). Contrairement à l'ancienne implémentation de
 * l'inscription, FM n'est jamais un choix de l'étudiant lui-même : il ne
 * fait qu'exprimer une demande, c'est l'admin qui choisit la salle FI
 * cible et bascule effectivement le compte.
 */
class FormationRequestController extends Controller
{
    public function store(Request $request)
    {
        $user = $request->user();
        abort_unless($user->role === UserRole::Etudiant, 403);
        abort_unless($user->formation === FormationType::FA, 422, 'Seuls les étudiants en Formation Alternance (FA) peuvent demander à rejoindre la Formation Initiale (FI).');

        $existing = DemandeFormation::where('etudiant_id', $user->id)
            ->where('statut', RequestStatus::EnAttente)
            ->exists();

        if ($existing) {
            throw ValidationException::withMessages(['demande' => ['Vous avez déjà une demande en attente.']]);
        }

        $data = $request->validate([
            'motif' => ['sometimes', 'nullable', 'string', 'max:500'],
        ]);

        $demande = DemandeFormation::create([
            'etudiant_id' => $user->id,
            'motif' => $data['motif'] ?? null,
            'statut' => RequestStatus::EnAttente,
            'date_creation' => now(),
        ]);

        return response()->json(new DemandeFormationResource($demande), 201);
    }

    public function mine(Request $request)
    {
        abort_unless($request->user()->role === UserRole::Etudiant, 403);

        return DemandeFormationResource::collection(
            DemandeFormation::with('salleCible')
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
            DemandeFormation::with(['etudiant.salle', 'salleCible'])
                ->when($data['statut'] ?? null, fn ($q, $statut) => $q->where('statut', $statut))
                ->latest('date_creation')
                ->get()
        );
    }

    /**
     * Approbation admin : choisit la salle FI cible, bascule l'étudiant en
     * FM et le rattache à cette salle (donc à son niveau/filière).
     */
    public function approve(Request $request, DemandeFormation $demande)
    {
        $this->assertPending($demande);

        $data = $request->validate(['salle_id' => ['required', 'exists:salles,id']]);

        $salle = Salle::with('filiere')->findOrFail($data['salle_id']);
        abort_unless($salle->formation === FormationType::FI, 422, 'La salle cible doit être une salle en Formation Initiale (FI).');

        DB::transaction(function () use ($demande, $salle) {
            $demande->update([
                'statut' => RequestStatus::Acceptee,
                'salle_cible_id' => $salle->id,
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
