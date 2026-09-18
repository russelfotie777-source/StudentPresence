<?php

namespace App\Services;

use App\Enums\FormationType;
use App\Models\Departement;
use App\Models\Filiere;
use App\Models\Niveau;
use App\Models\Salle;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Ouverture et lecture d'un département.
 *
 * Un département est censé exister à chaque niveau de l'établissement dès sa
 * création (L1, L2, L3…) : on y ouvre d'office une filière par niveau, du nom
 * du département, pour que l'admin puisse aussitôt y créer des salles et
 * programmer leur emploi du temps — sans passer d'abord par l'onglet
 * Filières. Les options (ASR, Cybersécurité…) s'ajoutent ensuite à côté.
 */
class StructureDepartement
{
    /**
     * @param  array{nom: string, code: string, nom_en?: string|null}  $attributs
     */
    public function creer(array $attributs): Departement
    {
        return DB::transaction(function () use ($attributs) {
            $departement = Departement::create($attributs);

            Niveau::query()->orderBy('nom')->each(fn (Niveau $niveau) => Filiere::create([
                'nom' => $departement->nom,
                'niveau_id' => $niveau->id,
                'departement_id' => $departement->id,
            ]));

            return $departement;
        });
    }

    /**
     * Le département déployé niveau par niveau — tous les niveaux de
     * l'établissement y figurent, même ceux où il n'a encore aucune filière,
     * c'est là que l'admin voit ce qui reste à créer.
     *
     * @return array<string, mixed>
     */
    public function arborescence(Departement $departement): array
    {
        $filieres = $departement->filieres()
            ->with(['salles' => fn ($q) => $q->orderBy('formation')->orderBy('nom')])
            ->orderBy('nom')
            ->get()
            ->groupBy('niveau_id');

        $niveaux = Niveau::query()->orderBy('nom')->get()->map(fn (Niveau $niveau) => [
            'id' => $niveau->id,
            'nom' => $niveau->nom,
            'filieres' => ($filieres[$niveau->id] ?? collect())->map(fn (Filiere $f) => [
                'id' => $f->id,
                'nom' => $f->nom,
                'salles' => $f->salles->map(fn (Salle $s) => [
                    'id' => $s->id,
                    'nom' => $s->nom,
                    'formation' => $s->formation->value,
                ])->values(),
            ])->values(),
        ]);

        return [
            'id' => $departement->id,
            'nom' => $departement->nom,
            'code' => $departement->code,
            'nom_en' => $departement->nom_en,
            'filieres_count' => $filieres->flatten(1)->count(),
            'salles_count' => $filieres->flatten(1)->sum(fn (Filiere $f) => $f->salles->count()),
            'niveaux' => $niveaux->values(),
        ];
    }

    /**
     * Les salles d'un département dans l'ordre où l'on imprime leurs listes :
     * niveau, puis filière, puis formation (FI avant FA), puis nom.
     *
     * @return Collection<int, Salle>
     */
    public function salles(Departement $departement): Collection
    {
        return Salle::query()
            ->duDepartement($departement->id)
            ->with(['filiere.niveau', 'filiere.departement'])
            ->get()
            ->sortBy([
                fn (Salle $a, Salle $b) => strnatcasecmp($a->filiere->niveau->nom, $b->filiere->niveau->nom),
                fn (Salle $a, Salle $b) => strnatcasecmp($a->filiere->nom, $b->filiere->nom),
                fn (Salle $a, Salle $b) => ($a->formation === FormationType::FA) <=> ($b->formation === FormationType::FA),
                fn (Salle $a, Salle $b) => strnatcasecmp($a->nom, $b->nom),
            ])
            ->values();
    }
}
