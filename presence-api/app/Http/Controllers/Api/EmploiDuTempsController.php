<?php

namespace App\Http\Controllers\Api;

use App\Enums\PlanningUpdateScope;
use App\Enums\Weekday;
use App\Http\Controllers\Controller;
use App\Http\Resources\SeanceResource;
use App\Models\Seance;
use App\Models\Semaine;
use App\Services\RetouchesPlanning;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Grille hebdomadaire du back-office : les séances d'une salle (ou d'un
 * enseignant) sur une semaine, et les retouches séance par séance —
 * décaler un horaire, changer d'enseignant, annuler une occurrence — sans
 * toucher au cours récurrent dont elles proviennent.
 */
class EmploiDuTempsController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'salle_id' => ['required_without:enseignant_id', 'exists:salles,id'],
            'enseignant_id' => ['required_without:salle_id', 'exists:users,id'],
            'semaine_id' => ['sometimes', 'exists:semaines,id'],
        ]);

        // Sans semaine explicite : celle d'aujourd'hui, ou à défaut la plus
        // proche — pour qu'un admin qui ouvre l'onglet hors semestre voie
        // quand même quelque chose plutôt qu'une grille vide.
        $semaine = isset($data['semaine_id'])
            ? Semaine::findOrFail($data['semaine_id'])
            : Semaine::current();

        $seances = $semaine
            ? Seance::query()
                ->with(['salle', 'enseignant', 'courseTemplate.matiere'])
                ->withCount('presences')
                ->where('semaine_id', $semaine->id)
                ->when($data['salle_id'] ?? null, fn ($q, $v) => $q->where('salle_id', $v))
                ->when($data['enseignant_id'] ?? null, fn ($q, $v) => $q->where('enseignant_id', $v))
                ->orderBy('date_seance')
                ->orderBy('heure_debut')
                ->get()
            : collect();

        return SeanceResource::collection($seances)->additional([
            'semaine' => $semaine,
            'maintenant' => now()->toIso8601String(),
        ]);
    }

    /**
     * Retouche d'une occurrence ou propage les changements aux séances à
     * venir du même cours. La règle vit dans RetouchesPlanning, partagé avec
     * l'assistant IA.
     */
    public function update(Request $request, Seance $seance, RetouchesPlanning $retouches)
    {
        $data = $request->validate([
            'portee' => ['sometimes', Rule::enum(PlanningUpdateScope::class)],
            'date_seance' => ['sometimes', 'date'],
            'jour' => ['sometimes', Rule::enum(Weekday::class)],
            'heure_debut' => ['sometimes', 'date_format:H:i'],
            'heure_fin' => ['sometimes', 'date_format:H:i'],
            'enseignant_id' => ['sometimes', Rule::exists('users', 'id')->where('role', 'Enseignant')],
            'salle_id' => ['sometimes', 'exists:salles,id'],
        ]);

        $portee = PlanningUpdateScope::from($data['portee'] ?? PlanningUpdateScope::Occurrence->value);
        unset($data['portee']);

        if ($portee === PlanningUpdateScope::Occurrence && isset($data['jour'])) {
            throw ValidationException::withMessages(['jour' => ["Le jour ne s'utilise que pour modifier une série."]]);
        }

        if ($portee !== PlanningUpdateScope::Occurrence && isset($data['date_seance'])) {
            throw ValidationException::withMessages(['date_seance' => ["Pour une série, choisissez un jour de la semaine plutôt qu'une date isolée."]]);
        }

        $resultat = $retouches->modifierSelonPortee($seance, $data, $portee);

        return (new SeanceResource($resultat->seance))->additional([
            'seances_modifiees' => $resultat->updatedCount,
            'seances_preservees' => $resultat->preservedCount,
        ]);
    }

    public function destroy(Seance $seance, RetouchesPlanning $retouches)
    {
        $retouches->annuler($seance);

        return response()->noContent();
    }
}
