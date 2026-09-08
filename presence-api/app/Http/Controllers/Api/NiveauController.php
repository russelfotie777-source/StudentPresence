<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Niveau;
use App\Services\CatalogueCache;
use Illuminate\Http\Request;

class NiveauController extends Controller
{
    public function index()
    {
        return CatalogueCache::souvenir('niveaux', fn () => Niveau::orderBy('nom')->get()->toArray());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nom' => ['required', 'string', 'max:20', 'unique:niveaux,nom'],
        ]);

        return response()->json(Niveau::create($data), 201);
    }

    public function show(Niveau $niveau)
    {
        return $niveau->load(['filieres', 'tarifHeure']);
    }

    public function update(Request $request, Niveau $niveau)
    {
        $data = $request->validate([
            'nom' => ['required', 'string', 'max:20', 'unique:niveaux,nom,'.$niveau->id],
        ]);

        $niveau->update($data);

        return $niveau;
    }

    public function destroy(Niveau $niveau)
    {
        $niveau->delete();

        return response()->noContent();
    }
}
