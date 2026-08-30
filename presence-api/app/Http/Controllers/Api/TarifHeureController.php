<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Niveau;
use App\Models\TarifHeure;
use Illuminate\Http\Request;

class TarifHeureController extends Controller
{
    public function index()
    {
        return Niveau::with('tarifHeure')->orderBy('nom')->get();
    }

    /**
     * Upsert par niveau — reprend gestion_tarifs.php de l'ancienne app.
     */
    public function update(Request $request, Niveau $niveau)
    {
        $data = $request->validate([
            'tarif_heure' => ['required', 'numeric', 'min:0'],
        ]);

        $tarif = TarifHeure::updateOrCreate(['niveau_id' => $niveau->id], $data);

        return $tarif;
    }
}
