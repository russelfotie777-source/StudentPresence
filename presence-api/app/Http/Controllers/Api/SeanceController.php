<?php

namespace App\Http\Controllers\Api;

use App\Enums\PresenceState;
use App\Enums\PushStatus;
use App\Enums\UserRole;
use App\Enums\Weekday;
use App\Http\Controllers\Controller;
use App\Http\Resources\SeanceResource;
use App\Models\Parametre;
use App\Models\Seance;
use App\Models\Semaine;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SeanceController extends Controller
{
    private const HISTORIQUE_PAR_PAGE = 20;

    /**
     * Séances du jour pour l'utilisateur connecté, selon son rôle effectif
     * (tient compte d'une promotion temporaire active). Reprend les requêtes
     * de dashboard.php / dashEtudiant.php de l'ancienne app.
     */
    public function today(Request $request)
    {
        $user = $request->user();
        // « Aujourd'hui » = la date du jour à Douala (fuseau de l'application),
        // jamais celle du téléphone. On filtre sur la date réelle de la
        // séance : l'ancien filtre « même jour de semaine + semaine
        // courante » se repliait sur la semaine la plus proche hors semestre
        // et affichait alors des séances d'une autre semaine.
        $maintenant = now();
        $jour = Weekday::fromCarbon($maintenant);
        $semaine = Semaine::couvrant($maintenant);

        $query = Seance::query()
            ->with(['salle', 'enseignant', 'courseTemplate.matiere', 'pushRequest', 'position'])
            ->where(fn ($q) => $q
                ->whereDate('date_seance', $maintenant->toDateString())
                // Séances sans date (saisies à la main dans l'ancienne app) :
                // repérées par leur jour dans la semaine qui couvre aujourd'hui.
                ->orWhere(fn ($q2) => $q2
                    ->whereNull('date_seance')
                    ->where('jour', $jour->value)
                    ->where('semaine_id', $semaine?->id ?? -1)));

        $role = $user->effectiveRole();

        match ($role) {
            UserRole::Delegue => $query->where('salle_id', $user->salle_id),
            UserRole::Enseignant => $query->where('enseignant_id', $user->id),
            UserRole::Etudiant => $query->where('salle_id', $user->salle_id)
                ->with(['presences' => fn ($q) => $q->where('etudiant_id', $user->id)]),
            default => $query->whereRaw('1 = 0'),
        };

        return SeanceResource::collection($query->orderBy('heure_debut')->get());
    }

    /**
     * Historique des séances passées de l'utilisateur connecté (onglet
     * Historique côté app — étudiant/délégué/enseignant), même périmètre par
     * rôle que today() mais sur les jours précédents. N'existait pas dans
     * l'ancienne app pour ces rôles (réservé à l'admin/superprotect).
     */
    public function history(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Seance::query()
            ->with(['salle', 'enseignant', 'courseTemplate.matiere', 'pushRequest', 'position'])
            ->where('date_seance', '<', now()->toDateString());

        $role = $user->effectiveRole();

        match ($role) {
            UserRole::Delegue => $query->where('salle_id', $user->salle_id),
            UserRole::Enseignant => $query->where('enseignant_id', $user->id),
            UserRole::Etudiant => $query->where('salle_id', $user->salle_id)
                ->with(['presences' => fn ($q) => $q->where('etudiant_id', $user->id)]),
            default => $query->whereRaw('1 = 0'),
        };

        // Paginé plutôt que tronqué à 200 : sur une année complète l'ancien
        // plafond finissait par masquer silencieusement les séances les plus
        // anciennes, tout en chargeant d'un coup bien plus que ce qu'un écran
        // mobile affiche.
        return SeanceResource::collection(
            $query->orderByDesc('date_seance')
                ->orderByDesc('heure_debut')
                ->paginate($data['per_page'] ?? self::HISTORIQUE_PAR_PAGE)
        );
    }

    /**
     * Marquage etat_delegue par le délégué, avec horodatage début/fin réel.
     * Fenêtre active ±15 min (Seance::isActive), comme dashboard.php.
     */
    public function markDelegue(Request $request, Seance $seance)
    {
        $this->authorizeDelegue($request, $seance);

        $data = $request->validate([
            'etat' => ['required', 'in:present,absent'],
            'set_debut_reel' => ['sometimes', 'boolean'],
            'set_fin_reelle' => ['sometimes', 'boolean'],
        ]);

        if (! $seance->is_active) {
            throw ValidationException::withMessages([
                'etat' => 'Impossible de modifier le statut en dehors des heures de cours (marge de 15 minutes).',
            ]);
        }

        $updates = ['etat_delegue' => $data['etat']];

        if ($request->boolean('set_debut_reel')) {
            $updates['debut_reel'] = now()->toTimeString();
        }
        if ($request->boolean('set_fin_reelle')) {
            $updates['fin_reelle'] = now()->toTimeString();
        }

        $seance->update($updates);
        $seance->refresh();
        $seance->crediterQuotaEnseignant();

        return new SeanceResource($seance->fresh(['salle', 'enseignant']));
    }

    public function markProf(Request $request, Seance $seance)
    {
        if ($seance->enseignant_id !== $request->user()->id) {
            abort(403);
        }

        $data = $request->validate(['etat' => ['required', 'in:present,absent']]);

        if (! $seance->is_active) {
            throw ValidationException::withMessages([
                'etat' => 'Impossible de modifier le statut en dehors des heures de cours (marge de 15 minutes).',
            ]);
        }

        // L'enseignant qui répond lui-même reprend la main sur une
        // confirmation posée par le délégué à sa place.
        $seance->update(['etat_prof' => $data['etat'], 'etat_prof_marque_par_id' => null]);

        return new SeanceResource($seance->fresh());
    }

    /**
     * Le délégué confirme la présence de l'enseignant à sa place — pour les
     * enseignants qui n'utilisent pas l'application, dont les séances
     * resteraient sinon « non tenues » faute de leur réponse. Soumis au
     * réglage admin, à la fenêtre active, et à ce que le délégué n'ait pas
     * lui-même constaté une absence. Un enseignant qui a déjà répondu garde
     * le dernier mot.
     */
    public function confirmerEnseignant(Request $request, Seance $seance)
    {
        $this->authorizeDelegue($request, $seance);

        abort_unless(Parametre::delegueConfirmeEnseignant(), 403, "La confirmation de l'enseignant par le délégué est désactivée.");

        if (! $seance->is_active) {
            throw ValidationException::withMessages([
                'etat' => 'Impossible de modifier le statut en dehors des heures de cours (marge de 15 minutes).',
            ]);
        }

        if ($seance->etat_delegue === PresenceState::Absent) {
            throw ValidationException::withMessages([
                'etat' => "Vous avez marqué l'enseignant absent : marquez-le d'abord présent.",
            ]);
        }

        if ($seance->etat_prof !== null && $seance->etat_prof_marque_par_id === null) {
            throw ValidationException::withMessages([
                'etat' => "L'enseignant a déjà répondu lui-même.",
            ]);
        }

        $seance->update([
            'etat_prof' => PresenceState::Present,
            'etat_prof_marque_par_id' => $request->user()->id,
            // Un seul geste suffit : confirmer pour l'enseignant vaut aussi
            // constat de sa présence par le délégué, heure d'arrivée comprise.
            'etat_delegue' => PresenceState::Present,
            'debut_reel' => $seance->debut_reel ?? now()->toTimeString(),
        ]);

        return new SeanceResource($seance->fresh(['salle', 'enseignant']));
    }

    /**
     * Demande de report ("push") par l'enseignant — pas de contrainte de
     * fenêtre active, comme dans l'ancienne app.
     */
    public function push(Request $request, Seance $seance)
    {
        if ($seance->enseignant_id !== $request->user()->id) {
            abort(403);
        }

        $data = $request->validate(['etudiants_presents' => ['required', 'integer', 'min:0']]);

        $seance->pushRequest()->updateOrCreate([], [
            'etudiants_presents' => $data['etudiants_presents'],
            'status' => PushStatus::Pending,
        ]);

        return new SeanceResource($seance->fresh('pushRequest'));
    }

    private function authorizeDelegue(Request $request, Seance $seance): void
    {
        $user = $request->user();

        if ($user->effectiveRole() !== UserRole::Delegue || $seance->salle_id !== $user->salle_id) {
            abort(403);
        }
    }
}
