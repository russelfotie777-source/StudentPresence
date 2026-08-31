<?php

namespace App\Http\Controllers\Api;

use App\Enums\PresenceState;
use App\Enums\PushStatus;
use App\Enums\UserRole;
use App\Enums\Weekday;
use App\Http\Controllers\Controller;
use App\Http\Resources\SeanceResource;
use App\Models\Seance;
use App\Models\Semaine;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SeanceController extends Controller
{
    /**
     * Séances du jour pour l'utilisateur connecté, selon son rôle effectif
     * (tient compte d'une promotion temporaire active). Reprend les requêtes
     * de dashboard.php / dashEtudiant.php de l'ancienne app.
     */
    public function today(Request $request)
    {
        $user = $request->user();
        $today = Weekday::fromCarbon(now());
        $semaine = Semaine::current();

        $query = Seance::query()
            ->with(['salle', 'enseignant', 'courseTemplate.matiere', 'pushRequest', 'position'])
            ->where('jour', $today->value)
            ->when($semaine, fn ($q) => $q->where(fn ($q2) => $q2->where('semaine_id', $semaine->id)->orWhereNull('semaine_id')));

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

        return SeanceResource::collection(
            $query->orderByDesc('date_seance')->orderByDesc('heure_debut')->limit(200)->get()
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

        // Crédit d'heures idempotent : une seule fois par séance, marquée par
        // quota_credited_at. Corrige le bug de l'ancienne app, qui
        // incrémentait `quota` à chaque re-soumission "présent" du délégué.
        if ($seance->debut_reel && $seance->fin_reelle
            && $seance->etat_delegue === PresenceState::Present
            && ! $seance->quota_credited_at) {
            // abs() est indispensable : Carbon 3 renvoie un diff *signé* par
            // défaut (contrairement à Carbon 2) — sans ça, le sens de calcul
            // peut donner un nombre de minutes négatif.
            $minutes = abs(Carbon::parse($seance->fin_reelle)->diffInMinutes(Carbon::parse($seance->debut_reel)));
            $seance->enseignant->increment('quota', (int) round($minutes / 60));
            $seance->forceFill(['quota_credited_at' => now()])->save();
        }

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

        $seance->update(['etat_prof' => $data['etat']]);

        return new SeanceResource($seance->fresh());
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
