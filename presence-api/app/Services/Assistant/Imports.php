<?php

namespace App\Services\Assistant;

use App\Enums\FormationType;
use App\Models\ConversationIA;
use App\Models\Salle;
use Closure;
use Illuminate\Support\Str;

/**
 * Les imports en masse : à partir d'un tableur (colonnes désignées par le
 * modèle) ou d'un long PDF (lu par tranches), le serveur construit lui-même
 * les lignes à importer — le modèle n'en recopie aucune. Le résultat est
 * une action unique « importer_etudiants » ou « importer_cours », avec
 * toutes ses lignes, son aperçu et ses anomalies.
 */
class Imports
{
    /** Lignes conservées dans l'aperçu montré à l'admin et au modèle. */
    private const APERCU = 8;

    public function __construct(
        private PiecesJointes $pieces,
        private LecteurTableur $tableur,
        private ExtracteurPDF $extracteur,
    ) {}

    /**
     * Étudiants d'une feuille de tableur, selon les colonnes indiquées.
     *
     * @param  array<string, mixed>  $p  paramètres de proposer_import_etudiants
     * @return array{action: array<string, mixed>, resultat: array<string, mixed>}
     */
    public function etudiantsDepuisTableur(ConversationIA $conversation, array $p): array
    {
        $fichier = $this->pieces->trouver($conversation, $p['fichier'])
            ?? throw new \InvalidArgumentException("Fichier n°{$p['fichier']} introuvable dans la conversation.");
        if (($fichier['genre'] ?? null) !== 'tableur') {
            throw new \InvalidArgumentException("Le fichier « {$fichier['nom']} » n'est pas un tableur.");
        }

        $chemin = $this->pieces->cheminAbsolu($fichier);
        $feuilles = $fichier['feuilles'] ?? $this->tableur->feuilles($chemin);
        $feuille = collect($feuilles)->first(fn ($f) => $f['nom'] === ($p['feuille'] ?? null)) ?? $feuilles[0]
            ?? throw new \InvalidArgumentException('Tableur sans feuille.');

        $entete = $feuille['colonnes'];
        $index = fn (?string $colonne): ?int => $colonne === null || $colonne === ''
            ? null
            : (($i = $this->indexColonne($entete, $colonne)) === null
                ? throw new \InvalidArgumentException("Colonne « {$colonne} » introuvable dans la feuille « {$feuille['nom']} » (colonnes : ".implode(', ', $entete).').')
                : $i);

        $colNom = $index($p['colonne_nom']);
        $colMatricule = $index($p['colonne_matricule']);
        $colFormation = $index($p['colonne_formation'] ?? null);
        $colSalle = $index($p['colonne_salle'] ?? null);
        $premiere = max((int) ($p['ligne_debut'] ?? 0), $feuille['entete_ligne'] + 1);

        $brutes = [];
        foreach ($this->tableur->lignes($chemin, $feuille['nom'], $premiere) as $ligne) {
            $c = $ligne['cellules'];
            $brutes[] = [
                'ligne' => $ligne['numero'],
                'nom' => $c[$colNom] ?? '',
                'matricule' => $c[$colMatricule] ?? '',
                'formation' => $colFormation !== null ? ($c[$colFormation] ?? null) : null,
                'salle' => $colSalle !== null ? ($c[$colSalle] ?? null) : null,
            ];
        }

        $action = $this->actionEtudiants(
            $this->normaliserEtudiants($brutes, $p['salle_id'] ?? null, $p['formation'] ?? null),
            ['fichier' => $fichier['nom'], 'feuille' => $feuille['nom']],
            $p['resume'] ?? null,
        );

        return ['action' => $action, 'resultat' => $this->bilan($action)];
    }

    /**
     * Étudiants d'un long PDF, lu par tranches.
     *
     * @param  array<string, mixed>  $p
     * @param  Closure(string, array{fait: int, total: int}): void|null  $progression
     * @return array{action: array<string, mixed>, resultat: array<string, mixed>}
     */
    public function etudiantsDepuisPdf(ConversationIA $conversation, array $p, ?Closure $progression = null): array
    {
        $fichier = $this->pdf($conversation, $p['fichier']);
        $lu = $this->extracteur->extraire($this->pieces->cheminAbsolu($fichier), 'etudiants', $progression);

        $brutes = array_map(fn ($l) => [
            'ligne' => 'p. '.$l['pages'],
            'nom' => $l['nom'] ?? '',
            'matricule' => $l['matricule'] ?? '',
            'formation' => $l['formation'] ?? null,
            'salle' => $l['salle'] ?? null,
        ], $lu['lignes']);

        $action = $this->actionEtudiants(
            $this->normaliserEtudiants($brutes, $p['salle_id'] ?? null, $p['formation'] ?? null),
            ['fichier' => $fichier['nom'], 'pages' => $lu['pages'], 'pages_illisibles' => $lu['pages_illisibles']],
            $p['resume'] ?? null,
        );

        return ['action' => $action, 'resultat' => $this->bilan($action) + ['pages_lues' => $lu['pages'], 'pages_illisibles' => $lu['pages_illisibles']]];
    }

    /**
     * Cours d'un long PDF (emploi du temps de plusieurs pages ou classes).
     *
     * @param  array<string, mixed>  $p
     * @param  Closure(string, array{fait: int, total: int}): void|null  $progression
     * @return array{action: array<string, mixed>, resultat: array<string, mixed>}
     */
    public function coursDepuisPdf(ConversationIA $conversation, array $p, ?Closure $progression = null): array
    {
        $fichier = $this->pdf($conversation, $p['fichier']);
        $lu = $this->extracteur->extraire($this->pieces->cheminAbsolu($fichier), 'cours', $progression);
        $salleDefaut = $p['salle_id'] ?? null;

        $lignes = [];
        foreach ($lu['lignes'] as $l) {
            $salle = $l['salle'] ? $this->salleParNom($l['salle']) : null;
            $salleId = $salle?->id ?? $salleDefaut;
            $lignes[] = [
                'source' => 'p. '.$l['pages'],
                'salle_id' => $salleId,
                'salle_nom' => $salle?->nom ?? $l['salle'],
                'matiere_nom' => $l['matiere'] ?? '',
                'matiere_code' => $l['code'] ?? null,
                'enseignant_nom' => $l['enseignant'] ?? null,
                'jour' => $l['jour'] ?? null,
                'heure_debut' => $this->heure($l['heure_debut'] ?? ''),
                'heure_fin' => $this->heure($l['heure_fin'] ?? ''),
                'anomalie' => $salleId === null ? 'salle non reconnue'.($l['salle'] ? " (« {$l['salle']} »)" : '') : null,
            ];
        }

        $action = [
            'id' => (string) Str::uuid(),
            'type' => 'importer_cours',
            'resume' => $p['resume'] ?? sprintf('Importer %d cours depuis « %s »', count($lignes), $fichier['nom']),
            'parametres' => [
                'source' => ['fichier' => $fichier['nom'], 'pages' => $lu['pages']],
                'cours' => $lignes,
                'total' => count($lignes),
                'apercu' => array_slice($lignes, 0, self::APERCU),
                'anomalies' => count(array_filter($lignes, fn ($l) => $l['anomalie'] !== null)),
            ],
            'statut' => 'en_attente',
            'resultat' => null,
            'proposee_le' => now()->toIso8601String(),
        ];

        return ['action' => $action, 'resultat' => [
            'statut' => 'proposition d\'import enregistrée, en attente de confirmation de l\'admin',
            'action_id' => $action['id'],
            'total' => count($lignes),
            'anomalies' => $action['parametres']['anomalies'],
            'apercu' => $action['parametres']['apercu'],
            'pages_illisibles' => $lu['pages_illisibles'],
        ]];
    }

    /**
     * @param  list<array{ligne: int|string, nom: string, matricule: string, formation: ?string, salle: ?string}>  $brutes
     * @return list<array<string, mixed>>
     */
    private function normaliserEtudiants(array $brutes, ?int $salleDefaut, ?string $formationDefaut): array
    {
        $cacheSalles = [];
        $lignes = [];

        foreach ($brutes as $b) {
            $nom = trim(preg_replace('/\s+/u', ' ', $b['nom']) ?? '');
            $matricule = trim($b['matricule']);
            if ($nom === '' && $matricule === '') {
                continue;
            }

            $salleNom = trim((string) ($b['salle'] ?? ''));
            $salle = null;
            if ($salleNom !== '') {
                $salle = $cacheSalles[Str::lower($salleNom)] ??= $this->salleParNom($salleNom) ?? false;
                $salle = $salle ?: null;
            }
            $salleId = $salle?->id ?? $salleDefaut;
            $formation = $this->formation($b['formation'] ?? null) ?? $formationDefaut ?? ($salleId ? Salle::find($salleId)?->formation?->value : null);

            $anomalie = match (true) {
                $nom === '' => 'nom manquant',
                $matricule === '' => 'matricule manquant',
                $salleId === null => 'salle non reconnue'.($salleNom !== '' ? " (« {$salleNom} »)" : ''),
                $formation === null => 'formation manquante',
                default => null,
            };

            $lignes[] = [
                'ligne' => $b['ligne'],
                'nom' => $nom,
                'matricule' => $matricule,
                'formation' => $formation,
                'salle_id' => $salleId,
                'salle_nom' => $salle?->nom ?? ($salleId ? Salle::find($salleId)?->nom : $salleNom),
                'anomalie' => $anomalie,
            ];
        }

        return $lignes;
    }

    /**
     * @param  list<array<string, mixed>>  $lignes
     * @param  array<string, mixed>  $source
     * @return array<string, mixed>
     */
    private function actionEtudiants(array $lignes, array $source, ?string $resume): array
    {
        $anomalies = count(array_filter($lignes, fn ($l) => $l['anomalie'] !== null));
        $salles = collect($lignes)->pluck('salle_nom')->filter()->unique()->values()->all();

        return [
            'id' => (string) Str::uuid(),
            'type' => 'importer_etudiants',
            'resume' => $resume ?? sprintf(
                'Inscrire %d étudiant%s%s depuis « %s »',
                count($lignes), count($lignes) > 1 ? 's' : '',
                $salles ? ' en '.implode(', ', array_slice($salles, 0, 3)).(count($salles) > 3 ? '…' : '') : '',
                $source['fichier'],
            ),
            'parametres' => [
                'source' => $source,
                'etudiants' => $lignes,
                'total' => count($lignes),
                'apercu' => array_slice($lignes, 0, self::APERCU),
                'anomalies' => $anomalies,
            ],
            'statut' => 'en_attente',
            'resultat' => null,
            'proposee_le' => now()->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $action
     * @return array<string, mixed>
     */
    private function bilan(array $action): array
    {
        $p = $action['parametres'];
        $anomalies = collect($p['etudiants'])->filter(fn ($l) => $l['anomalie'] !== null);

        return [
            'statut' => 'proposition d\'import enregistrée, en attente de confirmation de l\'admin',
            'action_id' => $action['id'],
            'total' => $p['total'],
            'anomalies' => $anomalies->count(),
            'exemples_anomalies' => $anomalies->take(5)->map(fn ($l) => "ligne {$l['ligne']} : {$l['anomalie']}")->values()->all(),
            'apercu' => $p['apercu'],
        ];
    }

    /**
     * @param  list<string>  $entete
     */
    private function indexColonne(array $entete, string $colonne): ?int
    {
        $cible = $this->cle($colonne);
        foreach ($entete as $i => $titre) {
            if ($this->cle($titre) === $cible) {
                return $i;
            }
        }
        // Lettre de colonne (A, B, …) en dernier recours.
        if (preg_match('/^[A-Za-z]{1,2}$/', $colonne)) {
            $n = 0;
            foreach (str_split(Str::upper($colonne)) as $lettre) {
                $n = $n * 26 + (ord($lettre) - 64);
            }

            return $n - 1;
        }

        return null;
    }

    private function cle(string $texte): string
    {
        return Str::lower(preg_replace('/[^a-z0-9]+/i', '', Str::ascii($texte)) ?? '');
    }

    private function salleParNom(string $nom): ?Salle
    {
        $propre = trim($nom);

        return Salle::whereRaw('LOWER(nom) = ?', [Str::lower($propre)])->first()
            ?? Salle::whereRaw('LOWER(REPLACE(nom, "-", "")) = ?', [Str::lower(str_replace(['-', ' '], '', $propre))])->first();
    }

    private function formation(?string $valeur): ?string
    {
        $v = Str::upper(trim((string) $valeur));
        if (in_array($v, array_column(FormationType::cases(), 'value'), true)) {
            return $v;
        }

        return match (true) {
            str_contains($v, 'INITIALE') => 'FI',
            str_contains($v, 'ALTERNANCE') => 'FA',
            str_contains($v, 'MIGRANT') => 'FM',
            default => null,
        };
    }

    private function heure(string $h): string
    {
        return preg_match('/^(\d{1,2})[:h](\d{2})/i', trim($h), $m) ? sprintf('%02d:%s', (int) $m[1], $m[2]) : trim($h);
    }

    /**
     * @return array<string, mixed>
     */
    private function pdf(ConversationIA $conversation, int|string $reference): array
    {
        $fichier = $this->pieces->trouver($conversation, $reference)
            ?? throw new \InvalidArgumentException("Fichier n°{$reference} introuvable dans la conversation.");
        if (($fichier['genre'] ?? null) !== 'pdf') {
            throw new \InvalidArgumentException("Le fichier « {$fichier['nom']} » n'est pas un PDF.");
        }

        return $fichier;
    }
}
