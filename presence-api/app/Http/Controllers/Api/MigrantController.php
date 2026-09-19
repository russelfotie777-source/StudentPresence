<?php

namespace App\Http\Controllers\Api;

use App\Enums\FormationType;
use App\Enums\PresenceState;
use App\Enums\RequestStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\DemandeFormation;
use App\Models\PresenceEtudiant;
use App\Models\Salle;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Les étudiants migrants (FM) vus par l'admin : qui ils sont, d'où ils
 * viennent, dans quelle salle de jour ils sont accueillis, depuis quand,
 * et comment ils suivent — regroupés par salle d'accueil, puisque c'est
 * par salle qu'on tire leur liste de présence.
 */
class MigrantController extends Controller
{
    public function index()
    {
        $migrants = User::query()
            ->whereIn('role', [UserRole::Etudiant->value, UserRole::Delegue->value])
            ->where('formation', FormationType::FM->value)
            ->with(['salle.filiere.niveau', 'salle.filiere.departement'])
            ->orderBy('name')
            ->get();

        // La dernière migration acceptée de chacun : origine et date.
        $migrations = DemandeFormation::query()
            ->whereIn('etudiant_id', $migrants->pluck('id'))
            ->where('statut', RequestStatus::Acceptee->value)
            ->with('salleOrigine')
            ->orderByDesc('date_traitement')
            ->get()
            ->unique('etudiant_id')
            ->keyBy('etudiant_id');

        // Assiduité depuis la migration : présences sur les séances où ils ont été appelés.
        $presences = PresenceEtudiant::query()
            ->whereIn('etudiant_id', $migrants->pluck('id'))
            ->selectRaw('etudiant_id, count(*) as appels, sum(case when etat = ? then 1 else 0 end) as presents', [PresenceState::Present->value])
            ->groupBy('etudiant_id')
            ->get()
            ->keyBy('etudiant_id');

        $parSalle = $migrants->groupBy('salle_id')->map(function ($etudiants, $salleId) use ($migrations, $presences) {
            /** @var Salle|null $salle */
            $salle = $etudiants->first()->salle;

            return [
                'salle' => $salle ? [
                    'id' => $salle->id,
                    'nom' => $salle->nom,
                    'formation' => $salle->formation->value,
                    'filiere' => $salle->filiere?->nom,
                    'niveau' => $salle->filiere?->niveau?->nom,
                    'departement' => $salle->filiere?->departement?->code,
                ] : null,
                'etudiants' => $etudiants->map(function (User $u) use ($migrations, $presences) {
                    $migration = $migrations[$u->id] ?? null;
                    $stats = $presences[$u->id] ?? null;
                    $appels = (int) ($stats->appels ?? 0);
                    $presents = (int) ($stats->presents ?? 0);

                    return [
                        'id' => $u->id,
                        'name' => $u->name,
                        'phone' => $u->phone,
                        'role' => $u->role->value,
                        'statut_compte' => $u->statut_compte->value,
                        'presence_automatique' => $u->presence_automatique,
                        'salle_origine' => $migration?->salleOrigine?->nom,
                        'migre_le' => $migration?->date_traitement?->toIso8601String(),
                        'appels' => $appels,
                        'presents' => $presents,
                        'taux' => $appels > 0 ? (int) round($presents / $appels * 100) : null,
                    ];
                })->values(),
            ];
        })->values()->sortBy(fn ($groupe) => $groupe['salle']['nom'] ?? '')->values();

        return response()->json([
            'total' => $migrants->count(),
            'salles' => $parSalle,
            'demandes_en_attente' => DemandeFormation::where('statut', RequestStatus::EnAttente->value)->count(),
        ]);
    }

    /**
     * Le privilège « toujours présent » : accordé ou retiré par l'admin, avec
     * un motif quand il est accordé — c'est une faveur, elle doit rester
     * explicable en cas de contestation.
     */
    public function presenceAutomatique(Request $request, User $etudiant)
    {
        abort_unless(in_array($etudiant->role, [UserRole::Etudiant, UserRole::Delegue], true), 404);

        $data = $request->validate([
            'actif' => ['required', 'boolean'],
            'motif' => ['required_if:actif,true', 'nullable', 'string', 'max:500'],
        ]);

        $etudiant->update([
            'presence_automatique' => $data['actif'],
            'presence_automatique_motif' => $data['actif'] ? $data['motif'] : null,
            'presence_automatique_le' => $data['actif'] ? now() : null,
        ]);

        return response()->json($this->privilegie($etudiant->fresh(['salle'])));
    }

    /** Les étudiants qui ont le privilège, pour l'onglet qui les gère. */
    public function privilegies()
    {
        return response()->json(
            User::query()
                ->whereIn('role', [UserRole::Etudiant->value, UserRole::Delegue->value])
                ->where('presence_automatique', true)
                ->with('salle')
                ->orderBy('name')
                ->get()
                ->map(fn (User $u) => $this->privilegie($u))
                ->values()
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function privilegie(User $u): array
    {
        return [
            'id' => $u->id,
            'name' => $u->name,
            'phone' => $u->phone,
            'formation' => $u->formation?->value,
            'salle' => $u->salle ? ['id' => $u->salle->id, 'nom' => $u->salle->nom] : null,
            'presence_automatique' => $u->presence_automatique,
            'motif' => $u->presence_automatique_motif,
            'depuis' => $u->presence_automatique_le?->toIso8601String(),
        ];
    }
}
