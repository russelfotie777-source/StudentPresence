<?php

namespace App\Services\Assistant;

use Closure;

/**
 * Lit un long PDF par tranches de pages et en tire des lignes structurées
 * (étudiants ou cours) via la sortie JSON du modèle. Quand une tranche
 * déborde la réponse, elle est coupée en deux et relue — jusqu'à la page
 * seule. Chaque tranche est un appel indépendant : 200 pages passent, une
 * par une s'il le faut.
 */
class ExtracteurPDF
{
    /** Pages par tranche au départ : assez pour une liste dense, sans risquer le débordement. */
    public const PAGES_PAR_TRANCHE = 8;

    public const SCHEMAS = [
        'etudiants' => [
            'type' => 'object',
            'properties' => [
                'etudiants' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'nom' => ['type' => 'string', 'description' => 'Nom et prénoms tels qu\'écrits'],
                            'matricule' => ['type' => 'string', 'description' => 'Matricule ou identifiant'],
                            'formation' => ['type' => ['string', 'null'], 'enum' => ['FI', 'FA', 'FM', null], 'description' => 'Si le document le précise'],
                            'salle' => ['type' => ['string', 'null'], 'description' => 'Classe / salle / groupe si le document le précise (ex. A23-FI, L3 GI)'],
                        ],
                        'required' => ['nom', 'matricule', 'formation', 'salle'],
                        'additionalProperties' => false,
                    ],
                ],
            ],
            'required' => ['etudiants'],
            'additionalProperties' => false,
        ],
        'cours' => [
            'type' => 'object',
            'properties' => [
                'cours' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'salle' => ['type' => ['string', 'null'], 'description' => 'Classe / salle concernée telle qu\'écrite'],
                            'matiere' => ['type' => 'string', 'description' => 'Intitulé de la matière'],
                            'code' => ['type' => ['string', 'null'], 'description' => 'Code de la matière (ex. INF321) si présent'],
                            'enseignant' => ['type' => ['string', 'null'], 'description' => 'Nom de l\'enseignant si présent'],
                            'jour' => ['type' => 'string', 'enum' => ['LUNDI', 'MARDI', 'MERCREDI', 'JEUDI', 'VENDREDI', 'SAMEDI', 'DIMANCHE']],
                            'heure_debut' => ['type' => 'string', 'description' => 'HH:MM sur 24 h'],
                            'heure_fin' => ['type' => 'string', 'description' => 'HH:MM sur 24 h'],
                        ],
                        'required' => ['salle', 'matiere', 'code', 'enseignant', 'jour', 'heure_debut', 'heure_fin'],
                        'additionalProperties' => false,
                    ],
                ],
            ],
            'required' => ['cours'],
            'additionalProperties' => false,
        ],
    ];

    private const CONSIGNES = [
        'etudiants' => 'Ce document contient une liste d\'étudiants (extrait de pages %d à %d sur %d). Recopie chaque étudiant exactement une fois, sans en omettre ni en inventer : nom et prénoms tels qu\'écrits, matricule tel qu\'écrit. Renseigne formation (FI/FA/FM) et salle/classe seulement si le document les indique, sinon null. Ignore les en-têtes, totaux et lignes vides. Réponds uniquement avec le JSON demandé.',
        'cours' => 'Ce document contient un emploi du temps (extrait de pages %d à %d sur %d). Recopie chaque créneau de cours exactement une fois : salle/classe concernée si indiquée, matière, code si présent, enseignant si présent, jour en majuscules, heures de début et de fin sur 24 h (HH:MM). Un créneau qui s\'étend sur plusieurs lignes d\'une grille reste un seul cours. Réponds uniquement avec le JSON demandé.',
    ];

    public function __construct(
        private Modele $modele,
        private DecoupeurPDF $decoupeur,
    ) {}

    /**
     * @param  'etudiants'|'cours'  $nature
     * @param  Closure(string, array{fait: int, total: int}): void|null  $progression
     * @return array{lignes: list<array<string, mixed>>, pages: int, tranches: int, pages_illisibles: list<int>}
     */
    public function extraire(string $chemin, string $nature, ?Closure $progression = null): array
    {
        $total = $this->decoupeur->nombreDePages($chemin);
        $lignes = [];
        $illisibles = [];
        $tranches = 0;
        $file = [];

        for ($de = 1; $de <= $total; $de += self::PAGES_PAR_TRANCHE) {
            $file[] = [$de, min($total, $de + self::PAGES_PAR_TRANCHE - 1)];
        }

        while ($file) {
            [$de, $a] = array_shift($file);
            $progression?->__invoke("Lecture des pages {$de}–{$a} sur {$total}", ['fait' => $de - 1, 'total' => $total]);

            $tranches++;
            $resultat = $this->modele->structurer(
                sprintf(self::CONSIGNES[$nature], $de, $a, $total),
                base64_encode($this->decoupeur->pages($chemin, $de, $a)),
                self::SCHEMAS[$nature],
            );

            if ($resultat === null) {
                if ($a > $de) {
                    // Débordement : on coupe la tranche en deux et on remet les moitiés en tête de file.
                    $milieu = intdiv($de + $a, 2);
                    array_unshift($file, [$de, $milieu], [$milieu + 1, $a]);
                } else {
                    $illisibles[] = $de;
                }

                continue;
            }

            foreach ($resultat[$nature] ?? [] as $ligne) {
                $lignes[] = [...$ligne, 'pages' => "{$de}-{$a}"];
            }
        }

        $progression?->__invoke("Lecture terminée : {$total} pages", ['fait' => $total, 'total' => $total]);

        return ['lignes' => $lignes, 'pages' => $total, 'tranches' => $tranches, 'pages_illisibles' => $illisibles];
    }
}
