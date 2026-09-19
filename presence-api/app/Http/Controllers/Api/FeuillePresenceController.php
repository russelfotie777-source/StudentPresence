<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Parametre;
use App\Models\Salle;
use App\Models\Seance;
use App\Models\Semaine;
use App\Models\User;
use App\Services\FeuilleDePresence;
use Illuminate\Http\Request;

/**
 * La feuille de présence d'une salle sur une semaine, telle que l'onglet
 * Étudiants l'affiche et que le PDF l'imprime : étudiants en lignes,
 * séances en colonnes, un état par case.
 */
class FeuillePresenceController extends Controller
{
    public function semaine(Request $request, Salle $salle, FeuilleDePresence $feuille)
    {
        $data = $request->validate([
            'semaine_id' => ['sometimes', 'exists:semaines,id'],
        ]);

        $semaine = isset($data['semaine_id'])
            ? Semaine::findOrFail($data['semaine_id'])
            : Semaine::current();

        $salle->loadMissing(['filiere.niveau', 'filiere.departement']);

        if (! $semaine) {
            return response()->json([
                'salle' => $this->salle($salle),
                'semaine' => null,
                'symboles' => Parametre::symbolesPresence(),
                'delegue' => null,
                'seances' => [],
                'etudiants' => [],
            ]);
        }

        $donnees = $feuille->pour($salle, $semaine);
        $seances = $donnees['seances'];

        return response()->json([
            'salle' => $this->salle($salle),
            'semaine' => $semaine,
            'symboles' => Parametre::symbolesPresence(),
            'maintenant' => now()->toIso8601String(),
            'delegue' => $donnees['delegue'] ? ['id' => $donnees['delegue']->id, 'name' => $donnees['delegue']->name] : null,
            'seances' => $seances->map(fn (Seance $s) => [
                'id' => $s->id,
                'date_seance' => $s->date_seance?->toDateString(),
                'jour' => $s->jour->value,
                'heure_debut' => substr($s->heure_debut, 0, 5),
                'heure_fin' => substr($s->heure_fin, 0, 5),
                'matiere' => $s->courseTemplate?->matiere?->nom,
                'enseignant' => $s->enseignant?->name,
                'statut' => $feuille->statut($s),
                'presences_locked' => $s->presences_locked,
                'presences_count' => $s->presences_count,
            ])->values(),
            'etudiants' => $donnees['etudiants']->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'phone' => $u->phone,
                'role' => $u->role->value,
                'formation' => $u->formation?->value,
                'statut_compte' => $u->statut_compte->value,
                'motif_statut' => $u->motif_statut,
                'presence_automatique' => (bool) $u->presence_automatique,
                'presence_automatique_motif' => $u->presence_automatique_motif,
                'presences' => $seances->mapWithKeys(fn (Seance $s) => [
                    $s->id => $feuille->marque($s, $u)?->value,
                ]),
            ])->values(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function salle(Salle $salle): array
    {
        return [
            'id' => $salle->id,
            'nom' => $salle->nom,
            'formation' => $salle->formation->value,
            'filiere' => $salle->filiere?->nom,
            'niveau' => $salle->filiere?->niveau?->nom,
            'departement' => $salle->filiere?->departement
                ? ['id' => $salle->filiere->departement->id, 'nom' => $salle->filiere->departement->nom, 'code' => $salle->filiere->departement->code]
                : null,
        ];
    }
}
