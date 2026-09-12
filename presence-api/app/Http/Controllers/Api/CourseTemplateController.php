<?php

namespace App\Http\Controllers\Api;

use App\Enums\Weekday;
use App\Http\Controllers\Controller;
use App\Http\Resources\SeanceResource;
use App\Models\CourseTemplate;
use App\Models\Seance;
use App\Services\SeanceGenerator;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CourseTemplateController extends Controller
{
    public function index(Request $request)
    {
        return CourseTemplate::with(['matiere', 'enseignant', 'salle'])
            ->when($request->integer('salle_id'), fn ($q, $salleId) => $q->where('salle_id', $salleId))
            ->orderBy('jour')
            ->orderBy('heure_debut')
            ->get();
    }

    /**
     * Crée le cours et, si `generer` est vrai, matérialise ses séances dans
     * la foulée : c'est le geste que fait un admin qui « programme un
     * cours » — l'étape manuelle « Générer les séances » qui suivait
     * autrefois n'apportait que de la confusion. Un cours ponctuel est un
     * cours dont la plage de validité tient sur un seul jour.
     */
    public function store(Request $request, SeanceGenerator $generator)
    {
        $data = $this->validated($request);
        $generer = $request->boolean('generer');

        return DB::transaction(function () use ($data, $generer, $generator) {
            $template = CourseTemplate::create($data);

            if (! $generer) {
                return response()->json($template->load(['matiere', 'enseignant', 'salle']), 201);
            }

            $result = $generator->generate($template);

            if ($result->created->isEmpty()) {
                // Rien n'a pu être programmé : ne pas laisser un cours fantôme
                // sans aucune séance, l'admin corrigera et réessaiera.
                throw ValidationException::withMessages([
                    'seances' => $result->skipped->isEmpty()
                        ? ["Aucune semaine du semestre ne couvre la période choisie : définissez d'abord les semaines dans le calendrier du semestre."]
                        : $result->skipped->pluck('reason')->unique()->values()->all(),
                ]);
            }

            return response()->json([
                'template' => $template->load(['matiere', 'enseignant', 'salle']),
                'created' => SeanceResource::collection(
                    Seance::with(['salle', 'enseignant', 'courseTemplate.matiere'])
                        ->whereIn('id', $result->created->pluck('id'))
                        ->orderBy('date_seance')
                        ->get()
                ),
                'skipped' => $result->skipped->values(),
            ], 201);
        });
    }

    public function show(CourseTemplate $courseTemplate)
    {
        return $courseTemplate->load(['matiere', 'enseignant', 'salle', 'seances']);
    }

    public function update(Request $request, CourseTemplate $courseTemplate)
    {
        $data = $this->validated($request);

        $courseTemplate->update($data);

        return $courseTemplate->load(['matiere', 'enseignant', 'salle']);
    }

    /**
     * Supprimer un cours retire aussi ses séances à venir — c'est ce qu'un
     * admin attend quand il annule un cours. Les séances déjà tenues (ou
     * avec des présences enregistrées) restent : elles font partie de
     * l'historique et de la paie de l'enseignant.
     */
    public function destroy(CourseTemplate $courseTemplate)
    {
        $supprimees = DB::transaction(function () use ($courseTemplate) {
            $aVenir = $courseTemplate->seances()
                ->whereDate('date_seance', '>=', now()->toDateString())
                ->whereNull('etat_delegue')
                ->whereNull('etat_prof')
                ->where('presences_locked', false)
                ->whereDoesntHave('presences');

            $nombre = (clone $aVenir)->count();
            $aVenir->delete();
            $courseTemplate->delete();

            return $nombre;
        });

        return response()->json(['seances_supprimees' => $supprimees]);
    }

    /**
     * Matérialise les `seances` de ce template pour chaque semaine couverte
     * (voir SeanceGenerator). Rejouable sans risque : les semaines déjà
     * générées sont simplement ignorées.
     */
    public function generate(CourseTemplate $courseTemplate, SeanceGenerator $generator)
    {
        $result = $generator->generate($courseTemplate);

        return response()->json([
            'created' => SeanceResource::collection($result->created),
            'skipped' => $result->skipped->values(),
        ], 201);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'matiere_id' => ['required', 'exists:matieres,id'],
            'enseignant_id' => ['required', Rule::exists('users', 'id')->where('role', 'Enseignant')],
            'salle_id' => ['required', 'exists:salles,id'],
            'groupe' => ['sometimes', 'string', 'max:10'],
            'jour' => ['required', Rule::in(array_column(Weekday::cases(), 'value'))],
            'heure_debut' => ['required', 'date_format:H:i'],
            'heure_fin' => ['required', 'date_format:H:i', 'after:heure_debut'],
            'date_debut' => ['required', 'date'],
            'date_fin' => ['required', 'date', 'after_or_equal:date_debut'],
            'actif' => ['sometimes', 'boolean'],
        ]);

        // Un jour qui ne tombe jamais dans la plage (ex. « lundi » entre un
        // mardi et un jeudi) ne produirait aucune séance : le signaler tout
        // de suite plutôt que de laisser l'admin chercher pourquoi la grille
        // reste vide.
        $jour = Weekday::from($data['jour']);

        if (! $jour->tombeEntre(Carbon::parse($data['date_debut']), Carbon::parse($data['date_fin']))) {
            throw ValidationException::withMessages([
                'jour' => ['Aucun '.mb_strtolower($jour->value).' ne tombe entre ces deux dates.'],
            ]);
        }

        return $data;
    }
}
