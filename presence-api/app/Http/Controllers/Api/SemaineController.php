<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Semaine;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SemaineController extends Controller
{
    public function index()
    {
        // Le nombre de séances permet au back-office de dire ce qu'une
        // suppression emporterait — et de la refuser, voir destroy().
        return Semaine::withCount('seances')->orderBy('numero')->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'numero' => ['required', 'integer', 'min:1', 'unique:semaines,numero'],
            'date_debut' => ['required', 'date'],
            'date_fin' => ['required', 'date', 'after_or_equal:date_debut'],
        ]);

        return response()->json(Semaine::create($data), 201);
    }

    public function show(Semaine $semaine)
    {
        return $semaine;
    }

    public function update(Request $request, Semaine $semaine)
    {
        $data = $request->validate([
            'numero' => ['required', 'integer', 'min:1', 'unique:semaines,numero,'.$semaine->id],
            'date_debut' => ['required', 'date'],
            'date_fin' => ['required', 'date', 'after_or_equal:date_debut'],
        ]);

        $semaine->update($data);

        return $semaine;
    }

    /**
     * Une semaine qui porte des séances ne se supprime pas : ses séances
     * perdraient leur rattachement (semaine_id à null) et disparaîtraient
     * de la grille tout en restant visibles des étudiants à leur date.
     */
    public function destroy(Semaine $semaine)
    {
        if ($semaine->seances()->exists()) {
            throw ValidationException::withMessages([
                'semaine' => ['Cette semaine contient des séances : annulez-les d\'abord.'],
            ]);
        }

        $semaine->delete();

        return response()->noContent();
    }

    /**
     * Génère N semaines consécutives (lundi-dimanche) à partir d'une date de
     * début — remplace la saisie manuelle semaine par semaine. N'existait pas
     * du tout dans l'ancienne app (la table `semaines` n'était jamais
     * alimentée par le code).
     */
    public function generateSemester(Request $request)
    {
        $data = $request->validate([
            'date_debut' => ['required', 'date'],
            'nombre_semaines' => ['required', 'integer', 'min:1', 'max:52'],
        ]);

        $startingNumero = (int) Semaine::max('numero') + 1;
        $lundi = Carbon::parse($data['date_debut'])->startOfWeek(Carbon::MONDAY);

        $created = collect(range(0, $data['nombre_semaines'] - 1))->map(function (int $i) use ($lundi, $startingNumero) {
            $debut = $lundi->clone()->addWeeks($i);

            return Semaine::create([
                'numero' => $startingNumero + $i,
                'date_debut' => $debut->toDateString(),
                'date_fin' => $debut->clone()->addDays(6)->toDateString(),
            ]);
        });

        return response()->json($created, 201);
    }
}
