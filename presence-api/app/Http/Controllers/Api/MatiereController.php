<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Matiere;
use Illuminate\Http\Request;

class MatiereController extends Controller
{
    public function index()
    {
        return Matiere::orderBy('nom')->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nom' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:20', 'unique:matieres,code'],
        ]);

        return response()->json(Matiere::create($data), 201);
    }

    public function show(Matiere $matiere)
    {
        return $matiere;
    }

    public function update(Request $request, Matiere $matiere)
    {
        $data = $request->validate([
            'nom' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:20', 'unique:matieres,code,'.$matiere->id],
        ]);

        $matiere->update($data);

        return $matiere;
    }

    public function destroy(Matiere $matiere)
    {
        $matiere->delete();

        return response()->noContent();
    }
}
