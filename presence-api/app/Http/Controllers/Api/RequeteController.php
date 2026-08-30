<?php

namespace App\Http\Controllers\Api;

use App\Enums\PresenceState;
use App\Enums\RequestStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Requete\StoreRequeteRequest;
use App\Http\Resources\RequeteResource;
use App\Models\RequeteEnseignant;
use App\Models\Seance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RequeteController extends Controller
{
    /**
     * Dépôt d'une contestation par l'enseignant, avec preuve jointe — reprend
     * requete.php de l'ancienne app.
     */
    public function store(StoreRequeteRequest $request)
    {
        $user = $request->user();
        abort_unless($user->role === UserRole::Enseignant, 403);

        $seance = Seance::with('salle.filiere.niveau', 'courseTemplate.matiere')->findOrFail($request->integer('seance_id'));
        abort_unless($seance->enseignant_id === $user->id, 403);

        $path = $request->file('preuve')->store('requetes', 'public');

        $requete = RequeteEnseignant::create([
            'seance_id' => $seance->id,
            'enseignant_id' => $user->id,
            'heure_seance' => $seance->heure_debut,
            'matiere' => $seance->courseTemplate?->matiere?->nom ?? '—',
            'salle' => $seance->salle->nom,
            'niveau' => $seance->salle->filiere->niveau->nom,
            'penalite' => 0,
            'description' => $request->string('description')->value(),
            'preuve_path' => $path,
            'statut' => RequestStatus::EnAttente,
            'date_creation' => now(),
        ]);

        return response()->json(new RequeteResource($requete), 201);
    }

    public function mine(Request $request)
    {
        abort_unless($request->user()->role === UserRole::Enseignant, 403);

        return RequeteResource::collection(
            RequeteEnseignant::where('enseignant_id', $request->user()->id)->latest('date_creation')->get()
        );
    }

    /**
     * Vue admin : toutes les requêtes, filtrables par statut — reprend
     * superprotect/requetes.php.
     */
    public function index(Request $request)
    {
        $data = $request->validate(['statut' => ['sometimes', 'in:en_attente,acceptee,rejetee']]);

        return RequeteResource::collection(
            RequeteEnseignant::with('enseignant')
                ->when($data['statut'] ?? null, fn ($q, $statut) => $q->where('statut', $statut))
                ->latest('date_creation')
                ->get()
        );
    }

    /**
     * Traitement admin. Corrige un vrai bug de l'ancienne app
     * (superprotect/process_request.php) : l'acceptation ne forçait que
     * etat_prof='present', jamais etat_delegue — du coup etat_final (colonne
     * générée, présent seulement si les deux sont 'present') pouvait rester
     * "absent" même après approbation. Ici on force les deux.
     */
    public function process(Request $request, RequeteEnseignant $requete)
    {
        $data = $request->validate([
            'action' => ['required', 'in:acceptee,rejetee'],
            'commentaire' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ]);

        if ($requete->statut !== RequestStatus::EnAttente) {
            throw ValidationException::withMessages(['statut' => ['Cette requête a déjà été traitée.']]);
        }

        DB::transaction(function () use ($requete, $data) {
            $requete->update([
                'statut' => $data['action'],
                'date_traitement' => now(),
                'commentaire_admin' => $data['commentaire'] ?? null,
            ]);

            if ($data['action'] === RequestStatus::Acceptee->value) {
                $seance = $requete->seance;
                $seance->update([
                    'debut_reel' => $seance->heure_debut,
                    'fin_reelle' => $seance->heure_fin,
                    'etat_prof' => PresenceState::Present->value,
                    'etat_delegue' => PresenceState::Present->value,
                ]);
            }
        });

        return new RequeteResource($requete->fresh());
    }
}
