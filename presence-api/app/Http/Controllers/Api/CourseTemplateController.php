<?php

namespace App\Http\Controllers\Api;

use App\Enums\Weekday;
use App\Http\Controllers\Controller;
use App\Http\Resources\SeanceResource;
use App\Models\CourseTemplate;
use App\Services\SeanceGenerator;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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

    public function store(Request $request)
    {
        $data = $this->validated($request);

        return response()->json(CourseTemplate::create($data)->load(['matiere', 'enseignant', 'salle']), 201);
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

    public function destroy(CourseTemplate $courseTemplate)
    {
        $courseTemplate->delete();

        return response()->noContent();
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
        return $request->validate([
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
    }
}
