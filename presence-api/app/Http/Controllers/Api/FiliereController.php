<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Filiere;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FiliereController extends Controller
{
    public function index(Request $request)
    {
        return Filiere::with('niveau')
            ->when($request->integer('niveau_id'), fn ($q, $niveauId) => $q->where('niveau_id', $niveauId))
            ->orderBy('nom')
            ->get();
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        return response()->json(Filiere::create($data)->load('niveau'), 201);
    }

    public function show(Filiere $filiere)
    {
        return $filiere->load(['niveau', 'salles', 'groupes']);
    }

    public function update(Request $request, Filiere $filiere)
    {
        $data = $this->validated($request, $filiere->id);

        $filiere->update($data);

        return $filiere->load('niveau');
    }

    public function destroy(Filiere $filiere)
    {
        $filiere->delete();

        return response()->noContent();
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'nom' => [
                'required', 'string', 'max:50',
                Rule::unique('filieres', 'nom')->where('niveau_id', $request->input('niveau_id'))->ignore($ignoreId),
            ],
            'niveau_id' => ['required', 'exists:niveaux,id'],
        ]);
    }
}
