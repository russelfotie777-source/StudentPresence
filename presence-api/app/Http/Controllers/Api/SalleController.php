<?php

namespace App\Http\Controllers\Api;

use App\Enums\FormationType;
use App\Http\Controllers\Controller;
use App\Models\Salle;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SalleController extends Controller
{
    public function index(Request $request)
    {
        return Salle::with('filiere.niveau')
            ->when($request->integer('filiere_id'), fn ($q, $filiereId) => $q->where('filiere_id', $filiereId))
            ->orderBy('formation')
            ->orderBy('nom')
            ->get();
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        return response()->json(Salle::create($data)->load('filiere'), 201);
    }

    public function show(Salle $salle)
    {
        return $salle->load('filiere.niveau');
    }

    public function update(Request $request, Salle $salle)
    {
        $data = $this->validated($request, $salle->id);

        $salle->update($data);

        return $salle->load('filiere');
    }

    public function destroy(Salle $salle)
    {
        $salle->delete();

        return response()->noContent();
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'nom' => [
                'required', 'string', 'max:20',
                Rule::unique('salles', 'nom')
                    ->where('filiere_id', $request->input('filiere_id'))
                    ->where('formation', $request->input('formation'))
                    ->ignore($ignoreId),
            ],
            'filiere_id' => ['required', 'exists:filieres,id'],
            'formation' => ['required', Rule::in([FormationType::FI->value, FormationType::FA->value])],
        ]);
    }
}
