<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Comptabilite;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * La comptabilité générale sur une période — par défaut l'année académique
 * en cours. Le calcul vit dans App\Services\Comptabilite, partagé avec
 * l'export Excel.
 */
class ComptabiliteController extends Controller
{
    public function index(Request $request, Comptabilite $comptabilite)
    {
        $data = $request->validate([
            'du' => ['sometimes', 'date'],
            'au' => ['sometimes', 'date', 'after_or_equal:du'],
        ]);

        return response()->json($comptabilite->pour(
            isset($data['du']) ? Carbon::parse($data['du']) : null,
            isset($data['au']) ? Carbon::parse($data['au']) : null,
        ));
    }
}
