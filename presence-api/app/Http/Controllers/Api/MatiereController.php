<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Matiere;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MatiereController extends Controller
{
    /**
     * Toutes les matières, ou celles d'une filière (avec les communes), ou
     * celles d'un département — chacune avec sa filière, son niveau et son
     * département pour que les écrans les rangent.
     */
    public function index(Request $request)
    {
        $filiereId = $request->integer('filiere_id');
        $departementId = $request->integer('departement_id');

        return Matiere::query()
            ->with('filiere.niveau', 'filiere.departement')
            ->when($filiereId, fn ($q) => $q->pourFiliere($filiereId))
            ->when($departementId, fn ($q) => $q->where(fn ($q2) => $q2
                ->whereNull('filiere_id')
                ->orWhereHas('filiere', fn ($q3) => $q3->where('departement_id', $departementId))))
            ->orderBy('nom')
            ->get();
    }

    public function store(Request $request)
    {
        return response()->json(Matiere::create($this->validated($request))->load('filiere.niveau', 'filiere.departement'), 201);
    }

    public function show(Matiere $matiere)
    {
        return $matiere->load('filiere.niveau', 'filiere.departement');
    }

    public function update(Request $request, Matiere $matiere)
    {
        $matiere->update($this->validated($request, $matiere->id));

        return $matiere->load('filiere.niveau', 'filiere.departement');
    }

    public function destroy(Matiere $matiere)
    {
        $matiere->delete();

        return response()->noContent();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?int $ignoreId = null): array
    {
        $filiereId = $request->input('filiere_id') ?: null;

        return $request->validate([
            'nom' => ['required', 'string', 'max:100'],
            // Le même code peut exister dans deux filières (INF101 en GI et en GRT), pas deux fois dans la même.
            'code' => [
                'required', 'string', 'max:20',
                Rule::unique('matieres', 'code')
                    ->where(fn ($q) => $filiereId ? $q->where('filiere_id', $filiereId) : $q->whereNull('filiere_id'))
                    ->ignore($ignoreId),
            ],
            'filiere_id' => ['sometimes', 'nullable', 'integer', 'exists:filieres,id'],
        ]) + ['filiere_id' => $filiereId];
    }
}
