<?php

namespace App\Services;

use App\Enums\FormationType;
use App\Enums\Weekday;
use App\Models\Departement;
use App\Models\Parametre;
use App\Models\Salle;
use App\Models\Seance;
use App\Models\Semaine;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Assemble les données de la liste de présence hebdomadaire officielle
 * d'une salle, au format du département (en-tête bilingue, tableau des
 * étudiants avec une colonne par jour, tableau des séances de la semaine).
 *
 * L'en-tête porte le département de la salle — celui de sa filière — et non
 * plus un nom fixé dans la configuration : la liste d'une salle GRT sort au
 * nom du GRT, celle d'une salle GI au nom du Génie Informatique.
 */
class ListeHebdomadaire
{
    public function __construct(private FeuilleDePresence $feuille, private StructureDepartement $structure) {}

    /** Mots vides ignorés pour former le sigle d'une filière. */
    private const MOTS_VIDES = ['de', 'des', 'du', 'et', 'la', 'le', 'les', 'l', 'd', 'en', 'a', 'à'];

    private const ROMAINS = [1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V'];

    /** Lundi → samedi : les colonnes de la liste officielle. */
    private const JOURS_OUVRES = [
        Weekday::Lundi, Weekday::Mardi, Weekday::Mercredi, Weekday::Jeudi, Weekday::Vendredi, Weekday::Samedi,
    ];

    /**
     * @return array<string, mixed>
     */
    public function pour(Salle $salle, Semaine $semaine, ?int $semestre = null, ?string $annee = null, ?string $symboles = null): array
    {
        $salle->loadMissing(['filiere.niveau', 'filiere.departement']);
        $departement = $salle->filiere->departement;
        $niveauChiffre = $this->chiffreDuNiveau($salle->filiere->niveau->nom);
        $option = $this->option($salle);
        $donnees = $this->feuille->pour($salle, $semaine);

        return [
            'etablissement' => config('presence.etablissement'),
            'logo' => $this->logoEnDataUri(),
            'departement_fr' => $departement->enteteFr(),
            'departement_en' => $departement->enteteEn(),
            'option' => $option,
            'niveau_romain' => self::ROMAINS[$niveauChiffre] ?? (string) $niveauChiffre,
            'annee_academique' => $annee ?? $this->anneeAcademiqueCourante(),
            'semestre' => $semestre ?? $this->semestreCourant($niveauChiffre),
            'salle' => $salle->nom,
            // "GI2 – FI" : sigle + niveau + formation, comme sur la maquette.
            'groupe' => "{$option}{$niveauChiffre} – {$salle->formation->value}",
            'semaine_du' => $semaine->date_debut->format('d/m/Y'),
            'semaine_au' => $semaine->date_fin->format('d/m/Y'),
            'symboles' => $symboles ?? Parametre::symbolesPresence(),
            'etudiants' => $this->etudiants($donnees['etudiants'], $donnees['seances']),
            'jours' => $this->seancesParJour($donnees['seances']),
            'contient_fm' => $salle->formation === FormationType::FI,
            'contient_marques' => $donnees['seances']->contains(fn (Seance $s) => $this->feuille->statut($s) === 'tenue'),
        ];
    }

    /**
     * Les listes de toutes les salles d'un département pour une semaine, dans
     * l'ordre d'impression (niveau, filière, FI puis FA, nom) : une page par
     * salle dans un seul document, c'est ce que le secrétariat affiche au
     * tableau du département en début de semaine.
     *
     * @return array<string, mixed>
     */
    public function pourDepartement(Departement $departement, Semaine $semaine, ?int $semestre = null, ?string $annee = null, ?string $symboles = null): array
    {
        $listes = $this->structure->salles($departement)
            ->map(fn (Salle $salle) => $this->pour($salle, $semaine, $semestre, $annee, $symboles));

        return [
            'departement' => $departement,
            'semaine' => $semaine,
            'listes' => $listes,
        ];
    }

    /**
     * L'option imprimée en en-tête : le sigle de la filière, sauf pour la
     * filière "tronc commun" ouverte au nom du département, où c'est le code
     * du département lui-même qui fait foi ("GI", pas un sigle recalculé).
     */
    private function option(Salle $salle): string
    {
        $filiere = $salle->filiere;

        return $filiere->nom === $filiere->departement->nom
            ? $filiere->departement->code
            : $this->sigle($filiere->nom);
    }

    /**
     * Étudiants suivant les cours dans cette salle, par ordre alphabétique —
     * les FM (migrants FA → FI) y figurent dans une salle FI et sont
     * signalés, c'est le point que la liste papier doit faire ressortir.
     * Chaque jour porte la marque de chacune de ses séances, dans l'ordre
     * horaire (null = rien à noter : séance à venir ou appel non validé).
     *
     * @param  Collection<int, User>  $etudiants
     * @param  Collection<int, Seance>  $seances
     * @return Collection<int, array<string, mixed>>
     */
    private function etudiants(Collection $etudiants, Collection $seances): Collection
    {
        $parJour = $seances->groupBy(fn (Seance $s) => $s->jour->value);

        return $etudiants->values()->map(fn (User $u, int $i) => [
            'numero' => $i + 1,
            'matricule' => $u->phone,
            'nom' => Str::upper($u->name),
            'fm' => $u->formation === FormationType::FM,
            'jours' => collect(self::JOURS_OUVRES)->mapWithKeys(fn (Weekday $jour) => [
                $jour->value => ($parJour[$jour->value] ?? collect())
                    ->map(fn (Seance $s) => $this->feuille->marque($s, $u)?->value)
                    ->values()
                    ->all(),
            ])->all(),
        ]);
    }

    /**
     * Séances de la salle sur la semaine, groupées par jour (lundi → samedi),
     * pour préremplir le tableau du bas. Deux lignes par jour au minimum,
     * comme la maquette, même sans séance.
     *
     * @return array<string, array{label: string, seances: array<int, array<string, mixed>>}>
     */
    private function seancesParJour(Collection $seances): array
    {
        $seances = $seances->groupBy(fn (Seance $s) => $s->jour->value);

        $jours = [];
        foreach (self::JOURS_OUVRES as $jour) {
            $lignes = ($seances[$jour->value] ?? collect())->map(fn (Seance $s) => [
                'ec' => trim(($s->courseTemplate?->matiere?->code ?? '').' '.($s->courseTemplate?->matiere?->nom ?? '')),
                'enseignant' => $s->enseignant?->name ?? '',
                'debut' => substr($s->heure_debut, 0, 5),
                'fin' => substr($s->heure_fin, 0, 5),
                'duree' => $this->duree($s->heure_debut, $s->heure_fin),
            ])->values()->all();

            $jours[$jour->value] = [
                'label' => Str::ucfirst(Str::lower($jour->value)),
                'seances' => array_pad($lignes, max(2, count($lignes)), null),
            ];
        }

        return $jours;
    }

    /**
     * "Génie Informatique" → "GI", "Génie des Réseaux et Télécoms" → "GRT",
     * "Informatique" → "INF" : un nom d'un seul mot garde ses trois premières
     * lettres plutôt qu'une initiale seule, illisible en tête de liste.
     */
    public function sigle(string $nom): string
    {
        $mots = collect(preg_split("/[\s'’-]+/u", Str::ascii($nom)) ?: [])
            ->filter(fn ($m) => $m !== '' && ! in_array(Str::lower($m), self::MOTS_VIDES, true))
            ->values();

        if ($mots->count() === 1) {
            // Un nom déjà en sigle ("GRT") n'a pas à être réduit.
            return Str::upper($nom) === $nom ? $nom : Str::upper(Str::substr($mots[0], 0, 3));
        }

        return $mots->map(fn ($m) => Str::upper(Str::substr($m, 0, 1)))->implode('');
    }

    /** "L2" → 2, "DUT2" → 2, "Niveau 3" → 3. */
    public function chiffreDuNiveau(string $nom): int
    {
        return preg_match('/(\d+)/', $nom, $m) ? (int) $m[1] : 1;
    }

    /** Septembre → janvier : année N-N+1 ; sinon N-1-N. */
    public function anneeAcademiqueCourante(): string
    {
        $annee = (int) now()->format('Y');

        return now()->month >= 9 ? "{$annee}-".($annee + 1) : ($annee - 1)."-{$annee}";
    }

    /**
     * Le niveau II est en semestres 3 et 4 : semestre impair de septembre à
     * janvier, pair ensuite.
     */
    public function semestreCourant(int $niveau): int
    {
        $premier = ($niveau - 1) * 2 + 1;
        $mois = now()->month;

        return ($mois >= 9 || $mois <= 1) ? $premier : $premier + 1;
    }

    private function duree(string $debut, string $fin): string
    {
        $minutes = (strtotime($fin) - strtotime($debut)) / 60;
        if ($minutes <= 0) {
            return '';
        }

        return sprintf('%dh%02d', intdiv((int) $minutes, 60), (int) $minutes % 60);
    }

    /**
     * En data URI plutôt qu'en chemin : dompdf refuse les fichiers locaux
     * hors de son chroot, et une image manquante donnerait un document sans
     * logo sans aucun message.
     */
    private function logoEnDataUri(): ?string
    {
        $chemin = config('presence.etablissement.logo');

        if (! $chemin || ! is_file($chemin)) {
            return null;
        }

        return 'data:image/png;base64,'.base64_encode((string) file_get_contents($chemin));
    }
}
