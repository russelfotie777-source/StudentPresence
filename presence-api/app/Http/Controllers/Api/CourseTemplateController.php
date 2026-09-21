<?php

namespace App\Http\Controllers\Api;

use App\Enums\Weekday;
use App\Http\Controllers\Controller;
use App\Http\Resources\SeanceResource;
use App\Models\CourseTemplate;
use App\Models\Matiere;
use App\Models\Salle;
use App\Models\Seance;
use App\Models\Semaine;
use App\Services\ProlongationCours;
use App\Services\RetouchesPlanning;
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

    /**
     * Modifier un cours modifie la règle : ses séances à venir suivent (jour,
     * horaires, enseignant, salle), sauf celles qui tomberaient en conflit,
     * renvoyées dans `ignorees`. Les séances tenues restent ce qu'elles sont.
     */
    public function update(Request $request, CourseTemplate $courseTemplate, RetouchesPlanning $retouches)
    {
        $data = $this->validated($request, $courseTemplate);

        $resultat = $retouches->modifierCours($courseTemplate, $data);

        return response()->json([
            'template' => $courseTemplate->fresh(['matiere', 'enseignant', 'salle']),
            ...$resultat,
        ]);
    }

    /**
     * Prolonge l'emploi du temps jusqu'à la dernière semaine du calendrier
     * (ou une date donnée) : voir ProlongationCours.
     */
    public function prolonger(Request $request, ProlongationCours $prolongation)
    {
        $data = $request->validate(['jusqu_au' => ['sometimes', 'date']]);

        $jusquAu = isset($data['jusqu_au']) ? Carbon::parse($data['jusqu_au']) : Semaine::max('date_fin');
        abort_unless($jusquAu, 422, "Aucune semaine n'est définie : créez d'abord le calendrier.");

        return response()->json($prolongation->jusquA(Carbon::parse($jusquAu)));
    }

    /**
     * Supprimer un cours retire aussi ses séances à venir (voir
     * RetouchesPlanning, partagé avec l'assistant IA).
     */
    public function destroy(CourseTemplate $courseTemplate, RetouchesPlanning $retouches)
    {
        return response()->json(['seances_supprimees' => $retouches->supprimerCours($courseTemplate)]);
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

    /**
     * À la création, tout est requis ; à la modification, seuls les champs
     * envoyés changent, et les règles se vérifient sur le cours résultant.
     *
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?CourseTemplate $existant = null): array
    {
        $requis = $existant ? 'sometimes' : 'required';

        $data = $request->validate([
            'matiere_id' => [$requis, 'exists:matieres,id'],
            'enseignant_id' => [$requis, Rule::exists('users', 'id')->where('role', 'Enseignant')],
            'salle_id' => [$requis, 'exists:salles,id'],
            'groupe' => ['sometimes', 'string', 'max:10'],
            'jour' => [$requis, Rule::in(array_column(Weekday::cases(), 'value'))],
            'heure_debut' => [$requis, 'date_format:H:i'],
            'heure_fin' => [$requis, 'date_format:H:i'],
            'date_debut' => [$requis, 'date'],
            'date_fin' => [$requis, 'date'],
            'actif' => ['sometimes', 'boolean'],
        ]);

        // Le cours tel qu'il sera : ce qui est envoyé, complété par l'existant.
        $cours = $existant ? array_merge([
            'matiere_id' => $existant->matiere_id,
            'enseignant_id' => $existant->enseignant_id,
            'salle_id' => $existant->salle_id,
            'jour' => $existant->jour instanceof Weekday ? $existant->jour->value : $existant->jour,
            'heure_debut' => substr($existant->heure_debut, 0, 5),
            'heure_fin' => substr($existant->heure_fin, 0, 5),
            'date_debut' => $existant->date_debut->toDateString(),
            'date_fin' => $existant->date_fin->toDateString(),
        ], $data) : $data;

        if ($cours['heure_fin'] <= $cours['heure_debut']) {
            throw ValidationException::withMessages(['heure_fin' => ["L'heure de fin doit être après l'heure de début."]]);
        }
        if ($cours['date_fin'] < $cours['date_debut']) {
            throw ValidationException::withMessages(['date_fin' => ['La date de fin doit suivre la date de début.']]);
        }

        // Chaque filière a ses matières : un cours ne peut porter qu'une
        // matière de la filière de sa salle, ou une matière commune.
        $filiereId = Salle::whereKey($cours['salle_id'])->value('filiere_id');
        if (! Matiere::whereKey($cours['matiere_id'])->pourFiliere($filiereId)->exists()) {
            throw ValidationException::withMessages([
                'matiere_id' => ["Cette matière n'appartient pas à la filière de la salle choisie."],
            ]);
        }

        // Un jour qui ne tombe jamais dans la plage (ex. « lundi » entre un
        // mardi et un jeudi) ne produirait aucune séance : le signaler tout
        // de suite plutôt que de laisser l'admin chercher pourquoi la grille
        // reste vide.
        $jour = Weekday::from($cours['jour']);

        if (! $jour->tombeEntre(Carbon::parse($cours['date_debut']), Carbon::parse($cours['date_fin']))) {
            throw ValidationException::withMessages([
                'jour' => ['Aucun '.mb_strtolower($jour->value).' ne tombe entre ces deux dates.'],
            ]);
        }

        return $data;
    }
}
