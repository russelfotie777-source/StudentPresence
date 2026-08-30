<?php

namespace App\Services;

use App\Enums\PresenceState;
use App\Models\Seance;
use App\Models\User;
use Carbon\Carbon;

/**
 * Formule canonique retenue pour la v2 (paliers 15/30/40 min) — c'est la
 * formule marquée "nouvelle logique" dans l'export PDF de
 * superprotect/suivi_salaires.php de l'ancienne app, calculée séance par
 * séance. L'ancienne app avait en réalité 3 formules différentes et
 * contradictoires selon le fichier (root salaireprof.php : paliers
 * 30/50min ; suivi_salaires.php écran : moyenne plate sans tenir compte du
 * retard ; export PDF : celle-ci) — tranché avec l'utilisateur en faveur de
 * cette dernière.
 */
class PayrollCalculator
{
    public function forTeacher(User $enseignant, ?Carbon $from = null, ?Carbon $to = null): PayrollResult
    {
        $seances = Seance::query()
            ->where('enseignant_id', $enseignant->id)
            ->where('etat_final', PresenceState::Present->value)
            ->whereNotNull('debut_reel')
            ->whereNotNull('fin_reelle')
            ->when($from, fn ($q) => $q->where('date_seance', '>=', $from->toDateString()))
            ->when($to, fn ($q) => $q->where('date_seance', '<=', $to->toDateString()))
            ->with(['salle.filiere.niveau.tarifHeure', 'courseTemplate.matiere'])
            ->orderBy('date_seance')
            ->get();

        $lines = $seances->map(fn (Seance $seance) => $this->lineFor($seance));

        return new PayrollResult(
            lines: $lines,
            totalSalaire: round((float) $lines->sum('salaire'), 2),
            totalPenaliteRetard: round((float) $lines->sum('penaliteRetard'), 2),
            totalMinutes: (int) $lines->sum('dureeMinutes'),
        );
    }

    public function lineFor(Seance $seance): PayrollLine
    {
        $tarif = (float) ($seance->salle->filiere->niveau->tarifHeure?->tarif_heure ?? 0);

        // Différence en secondes plutôt que Carbon::diffInMinutes() : Carbon 3
        // renvoie un diff *signé* dont le sens dépend de l'ordre des appels,
        // alors qu'ici le signe est significatif (retard positif = en retard,
        // négatif = en avance, jamais pénalisé — voir les paliers ci-dessous).
        $retard = (int) round(
            (Carbon::parse($seance->debut_reel)->timestamp - Carbon::parse($seance->heure_debut)->timestamp) / 60
        );
        $duree = (int) round(
            (Carbon::parse($seance->fin_reelle)->timestamp - Carbon::parse($seance->debut_reel)->timestamp) / 60
        );
        $duree = max(0, $duree);

        $tarifPlein = $this->proRate($duree, $tarif);
        $salaire = match (true) {
            $retard < 15 => $tarifPlein,
            $retard < 30 => ($duree / 60) * $tarif, // pro-rata strict, sans le "arrondi au plein" du palier <15
            $retard < 40 => $this->proRate($duree, $tarif) / 2,
            default => 0.0,
        };

        return new PayrollLine(
            seance: $seance,
            retardMinutes: $retard,
            dureeMinutes: $duree,
            tarifPlein: round($tarifPlein, 2),
            salaire: round($salaire, 2),
            penaliteRetard: round(max(0, $tarifPlein - $salaire), 2),
        );
    }

    /**
     * Une séance de 45 min ou plus est payée comme une heure pleine ; en
     * dessous, au prorata — reprend exactement la règle de l'ancienne app.
     */
    private function proRate(int $dureeMinutes, float $tarifHoraire): float
    {
        return $dureeMinutes >= 45 ? $tarifHoraire : ($dureeMinutes / 60) * $tarifHoraire;
    }
}
