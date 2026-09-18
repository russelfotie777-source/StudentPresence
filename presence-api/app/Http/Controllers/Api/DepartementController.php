<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Departement;
use App\Services\CatalogueCache;
use App\Services\StructureDepartement;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DepartementController extends Controller
{
    public function index()
    {
        return CatalogueCache::souvenir(
            'departements',
            fn () => Departement::withCount(['filieres', 'salles'])->orderBy('code')->get()->toArray()
        );
    }

    public function store(Request $request, StructureDepartement $structure)
    {
        $data = $this->validated($request);

        return response()->json($structure->arborescence($structure->creer($data)), 201);
    }

    public function show(Departement $departement, StructureDepartement $structure)
    {
        return $structure->arborescence($departement);
    }

    public function update(Request $request, Departement $departement)
    {
        $departement->update($this->validated($request, $departement->id));

        return $departement->loadCount(['filieres', 'salles']);
    }

    public function destroy(Departement $departement)
    {
        $departement->delete();

        return response()->noContent();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?int $ignoreId = null): array
    {
        // Le sigle s'écrit toujours en capitales ("gi" et "GI" sont le même
        // département) : on le normalise avant de juger de son unicité.
        $request->merge(['code' => mb_strtoupper(trim((string) $request->input('code')))]);

        return $request->validate([
            'nom' => ['required', 'string', 'max:100', Rule::unique('departements', 'nom')->ignore($ignoreId)],
            'code' => ['required', 'string', 'max:10', 'regex:/^[A-Z0-9]+$/', Rule::unique('departements', 'code')->ignore($ignoreId)],
            'nom_en' => ['sometimes', 'nullable', 'string', 'max:100'],
        ]);
    }
}
