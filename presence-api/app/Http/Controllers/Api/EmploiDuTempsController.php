<?php

namespace App\Http\Controllers\Api;

use App\Enums\Weekday;
use App\Http\Controllers\Controller;
use App\Http\Resources\SeanceResource;
use App\Models\Seance;
use App\Models\Semaine;
use App\Services\DetecteurConflits;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
     * Retouche d'une occurrence : horaires, jour (dans la même semaine ou
     * une autre), enseignant. Refusée dès que la séance a été tenue — ses
     * présences et ses heures payées sont figées.
     */
    public function update(Request $request, Seance $seance, DetecteurConflits $conflits)
    {
        $this->assertModifiable($seance);

        $data = $request->validate([
            'date_seance' => ['sometimes', 'date'],
            'heure_debut' => ['sometimes', 'date_format:H:i'],
            'heure_fin' => ['sometimes', 'date_format:H:i'],
            'enseignant_id' => ['sometimes', Rule::exists('users', 'id')->where('role', 'Enseignant')],
            'salle_id' => ['sometimes', 'exists:salles,id'],
        ]);

        $date = Carbon::parse($data['date_seance'] ?? $seance->date_seance->toDateString());
        $debut = $data['heure_debut'] ?? substr($seance->heure_debut, 0, 5);
        $fin = $data['heure_fin'] ?? substr($seance->heure_fin, 0, 5);

        if ($fin <= $debut) {
            throw ValidationException::withMessages(['heure_fin' => ["L'heure de fin doit être après l'heure de début."]]);
        }

        $semaine = Semaine::couvrant($date);

        if (! $semaine) {
            throw ValidationException::withMessages(['date_seance' => ['Aucune semaine du semestre ne couvre cette date.']]);
        }

        $creneau = [
            'salle_id' => (int) ($data['salle_id'] ?? $seance->salle_id),
            'groupe' => $seance->groupe,
            'enseignant_id' => (int) ($data['enseignant_id'] ?? $seance->enseignant_id),
            'date_seance' => $date->toDateString(),
            'semaine_id' => $semaine->id,
            'jour' => Weekday::fromCarbon($date)->value,
            'heure_debut' => $debut,
            'heure_fin' => $fin,
        ];

        if ($conflit = $conflits->pour($creneau, $seance->id)) {
            throw ValidationException::withMessages(['creneau' => [$conflit]]);
        }

        $seance->update($creneau);

        return new SeanceResource($seance->fresh(['salle', 'enseignant', 'courseTemplate.matiere']));
    }

    public function destroy(Seance $seance)
    {
        $this->assertModifiable($seance);

        DB::transaction(function () use ($seance) {
            $template = $seance->courseTemplate;
            $seance->delete();

            // Un cours ponctuel n'existe que pour porter sa séance : l'annuler
            // ne doit pas laisser un cours vide dans la liste.
            if ($template && $template->date_debut->equalTo($template->date_fin) && ! $template->seances()->exists()) {
                $template->delete();
            }
        });

        return response()->noContent();
    }

    private function assertModifiable(Seance $seance): void
    {
        $tenue = $seance->etat_delegue !== null
            || $seance->etat_prof !== null
            || $seance->presences_locked
            || $seance->presences()->exists();

        if ($tenue) {
            throw ValidationException::withMessages([
                'seance' => ['Cette séance a déjà été tenue ou a des présences enregistrées : elle ne peut plus être modifiée ni supprimée.'],
            ]);
        }
    }
}
