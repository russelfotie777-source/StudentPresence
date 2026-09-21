<?php

namespace App\Services;

use App\Models\CourseTemplate;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Quand le calendrier s'allonge, l'emploi du temps continue : les cours
 * qui allaient jusqu'au bout des semaines connues sont prolongés jusqu'aux
 * nouvelles, et leurs séances générées — avec les mêmes contrôles de
 * conflit qu'à la création. Un cours arrêté plus tôt par choix (S1 à S8)
 * n'est pas concerné : il s'est arrêté.
 */
class ProlongationCours
{
    public function __construct(private SeanceGenerator $generateur) {}

    /**
     * @return array{cours: int, seances: int, ignorees: list<array<string, mixed>>}
     */
    public function jusquA(Carbon $jusquAu): array
    {
        $jusquAu = $jusquAu->copy()->startOfDay();
        $finActuelle = CourseTemplate::max('date_fin');
        if (! $finActuelle) {
            return ['cours' => 0, 'seances' => 0, 'ignorees' => []];
        }

        // « Jusqu'au bout » : les cours dont la fin tombe dans la dernière
        // semaine couverte par un cours, à la journée près.
        $seuil = Carbon::parse($finActuelle)->subDays(6)->toDateString();

        $cours = CourseTemplate::query()
            ->whereDate('date_fin', '>=', $seuil)
            ->whereDate('date_fin', '<', $jusquAu->toDateString())
            ->orderBy('id')
            ->get();

        $seances = 0;
        $ignorees = [];

        foreach ($cours as $c) {
            $resultat = DB::transaction(function () use ($c, $jusquAu) {
                $c->update(['date_fin' => $jusquAu->toDateString()]);

                return $this->generateur->generate($c->fresh());
            });
            $seances += $resultat->created->count();
            foreach ($resultat->skipped as $s) {
                // Une semaine déjà générée n'est pas une anomalie : c'est le cours d'avant.
                if (! str_contains($s['reason'], 'déjà générée')) {
                    $ignorees[] = ['cours_id' => $c->id, 'cours' => $c->matiere?->nom] + $s;
                }
            }
        }

        return ['cours' => $cours->count(), 'seances' => $seances, 'ignorees' => $ignorees];
    }
}
