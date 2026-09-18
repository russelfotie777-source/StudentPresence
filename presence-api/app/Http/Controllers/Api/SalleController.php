<?php

namespace App\Http\Controllers\Api;

use App\Enums\FormationType;
use App\Http\Controllers\Controller;
use App\Models\Salle;
use App\Services\CatalogueCache;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SalleController extends Controller
{
    public function index(Request $request)
    {
        $filiereId = $request->integer('filiere_id');
        $departementId = $request->integer('departement_id');

        return CatalogueCache::souvenir(
            "salles:filiere:{$filiereId}:departement:{$departementId}",
            fn () => Salle::with(['filiere.niveau', 'filiere.departement'])
                ->when($filiereId, fn ($q) => $q->where('filiere_id', $filiereId))
                ->when($departementId, fn ($q) => $q->duDepartement($departementId))
                ->orderBy('formation')
                ->orderBy('nom')
                ->get()
                ->toArray()
        );
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        return response()->json(Salle::create($data)->load('filiere.departement'), 201);
    }

    public function show(Salle $salle)
    {
        return $salle->load(['filiere.niveau', 'filiere.departement']);
    }

    public function update(Request $request, Salle $salle)
    {
        $data = $this->validated($request, $salle->id);

        $salle->update($data);

        return $salle->load('filiere.departement');
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
