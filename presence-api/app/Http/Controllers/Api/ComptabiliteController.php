<?php

namespace App\Http\Controllers\Api;

use App\Enums\FormationType;
use App\Enums\PresenceState;
use App\Enums\RequestStatus;
use App\Enums\UserRole;
use App\Enums\ValidationStatus;
use App\Http\Controllers\Controller;
use App\Models\DemandeFormation;
use App\Models\Departement;
use App\Models\PresenceEtudiant;
use App\Models\Seance;
use App\Models\User;
use App\Services\PayrollCalculator;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * La comptabilité générale de l'application sur une période : effectifs,
 * séances tenues et heures faites, assiduité des étudiants, et la paie des
 * enseignants (le même calcul que leur onglet Salaire, sommé). Par défaut,
 * l'année académique en cours.
 */
class ComptabiliteController extends Controller
{
    public function index(Request $request, PayrollCalculator $paie)
    {
        $data = $request->validate([
            'du' => ['sometimes', 'date'],
            'au' => ['sometimes', 'date', 'after_or_equal:du'],
        ]);

        $du = isset($data['du']) ? Carbon::parse($data['du'])->startOfDay() : $this->debutAnneeAcademique();
        $au = isset($data['au']) ? Carbon::parse($data['au'])->endOfDay() : now()->endOfDay();

        return response()->json([
            'periode' => ['du' => $du->toDateString(), 'au' => $au->toDateString()],
            'effectifs' => $this->effectifs(),
            'seances' => $this->seances($du, $au),
            'assiduite' => $this->assiduite($du, $au),
            'migrations' => $this->migrations($du, $au),
            'paie' => $this->paie($paie, $du, $au),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function effectifs(): array
    {
        $etudiants = User::query()->whereIn('role', [UserRole::Etudiant->value, UserRole::Delegue->value]);

        $parFormation = (clone $etudiants)
            ->selectRaw('formation, count(*) as total')
            ->groupBy('formation')
            ->pluck('total', 'formation');

        $parDepartement = Departement::query()
            ->orderBy('code')
            ->get()
            ->map(fn (Departement $d) => [
                'code' => $d->code,
                'nom' => $d->nom,
                'etudiants' => (clone $etudiants)->whereHas('filiere', fn ($q) => $q->where('departement_id', $d->id))->count(),
                'salles' => $d->salles()->count(),
            ])
            ->values();

        return [
            'etudiants' => (clone $etudiants)->count(),
            'par_formation' => [
                'FI' => (int) ($parFormation[FormationType::FI->value] ?? 0),
                'FA' => (int) ($parFormation[FormationType::FA->value] ?? 0),
                'FM' => (int) ($parFormation[FormationType::FM->value] ?? 0),
            ],
            'delegues' => User::where('role', UserRole::Delegue->value)->count(),
            'enseignants' => User::where('role', UserRole::Enseignant->value)
                ->where('validation_status', ValidationStatus::Approved->value)
                ->count(),
            'comptes_restreints' => (clone $etudiants)->where('statut_compte', '!=', 'actif')->count(),
            'presence_automatique' => (clone $etudiants)->where('presence_automatique', true)->count(),
            'par_departement' => $parDepartement,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function seances(Carbon $du, Carbon $au): array
    {
        $base = Seance::query()->whereBetween('date_seance', [$du->toDateString(), $au->toDateString()]);
        $passees = (clone $base)->where('date_seance', '<=', now()->toDateString());

        $totaux = (clone $passees)
            ->selectRaw('count(*) as total')
            ->selectRaw('sum(case when etat_final = ? then 1 else 0 end) as tenues', [PresenceState::Present->value])
            ->selectRaw('sum(case when etat_prof_marque_par_id is not null then 1 else 0 end) as confirmees_par_delegue')
            ->selectRaw('sum(case when etat_final = ? and debut_reel is not null and fin_reelle is not null then timestampdiff(minute, debut_reel, fin_reelle) else 0 end) as minutes', [PresenceState::Present->value])
            ->first();

        $total = (int) ($totaux->total ?? 0);
        $tenues = (int) ($totaux->tenues ?? 0);

        return [
            'programmees' => (clone $base)->count(),
            'passees' => $total,
            'tenues' => $tenues,
            'non_tenues' => $total - $tenues,
            'taux_tenue' => $total > 0 ? (int) round($tenues / $total * 100) : null,
            'confirmees_par_delegue' => (int) ($totaux->confirmees_par_delegue ?? 0),
            'heures_effectuees' => round(((int) ($totaux->minutes ?? 0)) / 60, 1),
            'a_venir' => (clone $base)->where('date_seance', '>', now()->toDateString())->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function assiduite(Carbon $du, Carbon $au): array
    {
        $totaux = PresenceEtudiant::query()
            ->whereHas('seance', fn ($q) => $q->whereBetween('date_seance', [$du->toDateString(), $au->toDateString()]))
            ->selectRaw('count(*) as appels')
            ->selectRaw('sum(case when etat = ? then 1 else 0 end) as presents', [PresenceState::Present->value])
            ->selectRaw('sum(case when forcee_par_id is not null then 1 else 0 end) as forcees')
            ->selectRaw('sum(case when automatique = 1 then 1 else 0 end) as automatiques')
            ->first();

        $appels = (int) ($totaux->appels ?? 0);
        $presents = (int) ($totaux->presents ?? 0);

        return [
            'appels' => $appels,
            'presents' => $presents,
            'absents' => $appels - $presents,
            'taux' => $appels > 0 ? (int) round($presents / $appels * 100) : null,
            'forcees_par_admin' => (int) ($totaux->forcees ?? 0),
            'automatiques' => (int) ($totaux->automatiques ?? 0),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function migrations(Carbon $du, Carbon $au): array
    {
        $base = DemandeFormation::query()->whereBetween('date_creation', [$du, $au]);

        return [
            'en_attente' => (clone $base)->where('statut', RequestStatus::EnAttente->value)->count(),
            'acceptees' => (clone $base)->where('statut', RequestStatus::Acceptee->value)->count(),
            'rejetees' => (clone $base)->where('statut', RequestStatus::Rejetee->value)->count(),
        ];
    }

    /**
     * La paie de chaque enseignant sur la période, par le même calcul que
     * son onglet Salaire — pour que le total de l'admin et ce que voit
     * chacun ne divergent jamais.
     *
     * @return array<string, mixed>
     */
    private function paie(PayrollCalculator $calculateur, Carbon $du, Carbon $au): array
    {
        $lignes = User::query()
            ->where('role', UserRole::Enseignant->value)
            ->orderBy('name')
            ->get()
            ->map(function (User $enseignant) use ($calculateur, $du, $au) {
                $resultat = $calculateur->forTeacher($enseignant, $du, $au);

                return [
                    'id' => $enseignant->id,
                    'name' => $enseignant->name,
                    'seances' => $resultat->lines->count(),
                    'heures' => round($resultat->totalMinutes / 60, 1),
                    'salaire' => $resultat->totalSalaire,
                    'penalites' => $resultat->totalPenaliteRetard,
                ];
            })
            ->filter(fn ($l) => $l['seances'] > 0)
            ->values();

        return [
            'enseignants' => $lignes,
            'total_salaire' => round((float) $lignes->sum('salaire'), 2),
            'total_penalites' => round((float) $lignes->sum('penalites'), 2),
            'total_heures' => round((float) $lignes->sum('heures'), 1),
        ];
    }

    /** Septembre → l'année académique commence le 1er septembre précédent. */
    private function debutAnneeAcademique(): Carbon
    {
        $annee = now()->month >= 9 ? now()->year : now()->year - 1;

        return Carbon::create($annee, 9, 1)->startOfDay();
    }
}
