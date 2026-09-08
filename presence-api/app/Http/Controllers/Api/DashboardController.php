<?php

namespace App\Http\Controllers\Api;

use App\Enums\PresenceState;
use App\Enums\RequestStatus;
use App\Enums\UserRole;
use App\Enums\ValidationStatus;
use App\Http\Controllers\Controller;
use App\Models\DemandeFormation;
use App\Models\Filiere;
use App\Models\Matiere;
use App\Models\Niveau;
use App\Models\RequeteEnseignant;
use App\Models\Salle;
use App\Models\Seance;
use App\Models\User;
use Illuminate\Http\JsonResponse;

/**
 * Chiffres agrégés de la page d'accueil du back-office.
 *
 * Tout est compté en SQL. L'écran chargeait auparavant les collections
 * entières (tous les comptes en attente, toutes les requêtes) uniquement
 * pour en afficher la longueur — ce qui grossit avec l'établissement alors
 * que le besoin réel tient dans quelques entiers.
 */
class DashboardController extends Controller
{
    /**
     * Fenêtre d'observation du taux de présence. Une semaine glissante :
     * assez court pour refléter la situation actuelle, assez long pour ne pas
     * dépendre d'une seule journée creuse.
     */
    private const JOURS_OBSERVES = 7;

    public function index(): JsonResponse
    {
        return response()->json([
            'a_traiter' => $this->aTraiter(),
            'activite' => $this->activite(),
            'catalogue' => $this->catalogue(),
        ]);
    }

    /**
     * Ce qui attend une décision de l'admin — la raison première pour
     * laquelle il ouvre cet écran.
     *
     * @return array<string, int>
     */
    private function aTraiter(): array
    {
        $validations = User::query()
            ->where('validation_status', ValidationStatus::Pending->value)
            ->whereIn('role', [UserRole::Delegue->value, UserRole::Enseignant->value])
            ->selectRaw('role, count(*) as total')
            ->groupBy('role')
            ->pluck('total', 'role');

        return [
            'delegues' => (int) ($validations[UserRole::Delegue->value] ?? 0),
            'enseignants' => (int) ($validations[UserRole::Enseignant->value] ?? 0),
            'requetes' => RequeteEnseignant::where('statut', RequestStatus::EnAttente->value)->count(),
            'migrations' => DemandeFormation::where('statut', RequestStatus::EnAttente->value)->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function activite(): array
    {
        $depuis = now()->subDays(self::JOURS_OBSERVES)->toDateString();

        $recentes = Seance::query()
            ->whereNotNull('date_seance')
            ->whereBetween('date_seance', [$depuis, now()->toDateString()])
            ->selectRaw('count(*) as total')
            ->selectRaw('sum(case when etat_final = ? then 1 else 0 end) as presentes', [PresenceState::Present->value])
            ->first();

        $total = (int) ($recentes->total ?? 0);
        $presentes = (int) ($recentes->presentes ?? 0);

        return [
            'seances_aujourdhui' => Seance::whereDate('date_seance', now()->toDateString())->count(),
            'jours_observes' => self::JOURS_OBSERVES,
            'seances_recentes' => $total,
            'seances_presentes' => $presentes,
            // `null` plutôt que zéro quand aucune séance n'a eu lieu : un taux
            // de 0 % laisserait croire à un problème d'assiduité, alors qu'il
            // n'y a simplement rien à mesurer (vacances, début d'année).
            'taux_presence' => $total > 0 ? round($presentes / $total * 100) : null,
        ];
    }

    /**
     * @return array<string, int>
     */
    private function catalogue(): array
    {
        return [
            'niveaux' => Niveau::count(),
            'filieres' => Filiere::count(),
            'salles' => Salle::count(),
            'matieres' => Matiere::count(),
            'enseignants' => User::where('role', UserRole::Enseignant->value)
                ->where('validation_status', ValidationStatus::Approved->value)
                ->count(),
            'etudiants' => User::whereIn('role', [UserRole::Etudiant->value, UserRole::Delegue->value])->count(),
        ];
    }
}
