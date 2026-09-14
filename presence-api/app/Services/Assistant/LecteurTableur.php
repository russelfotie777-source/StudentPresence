<?php

namespace App\Services\Assistant;

use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Csv;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Lit un classeur (xlsx, xls, csv) feuille par feuille, en texte : le
 * modèle n'accepte pas les tableurs, et surtout un import de 2 000 lignes
 * n'a pas à passer par lui — il ne lit qu'un aperçu pour repérer les
 * colonnes, le serveur fait le reste.
 */
class LecteurTableur
{
    public const EXTENSIONS = ['xlsx', 'xls', 'csv'];

    /**
     * @return list<array{nom: string, index: int, lignes: int, colonnes: list<string>, entete_ligne: int}>
     */
    public function feuilles(string $chemin): array
    {
        $classeur = $this->ouvrir($chemin);
        $feuilles = [];

        foreach ($classeur->getWorksheetIterator() as $index => $feuille) {
            $entete = $this->ligneEntete($feuille);
            $feuilles[] = [
                'nom' => $feuille->getTitle(),
                'index' => $index,
                'lignes' => max(0, $feuille->getHighestDataRow() - $entete),
                'colonnes' => $entete > 0 ? $this->cellules($feuille, $entete) : [],
                'entete_ligne' => $entete,
            ];
        }

        return $feuilles;
    }

    /**
     * Lignes d'une feuille (numéros 1-based), chaque ligne étant la liste
     * de ses cellules en texte.
     *
     * @return list<array{numero: int, cellules: list<string>}>
     */
    public function lignes(string $chemin, string $feuille, int $depuis = 1, int $nombre = 100000): array
    {
        $ws = $this->feuille($this->ouvrir($chemin), $feuille);
        $fin = min($ws->getHighestDataRow(), $depuis + $nombre - 1);
        $lignes = [];

        for ($i = max(1, $depuis); $i <= $fin; $i++) {
            $cellules = $this->cellules($ws, $i);
            if (implode('', $cellules) === '') {
                continue;
            }
            $lignes[] = ['numero' => $i, 'cellules' => $cellules];
        }

        return $lignes;
    }

    /**
     * Aperçu textuel destiné au modèle : pour chaque feuille, ses colonnes et
     * ses premières lignes, numérotées, cellules séparées par « | ».
     */
    public function apercu(string $chemin, string $nom, int $lignesParFeuille = 25): string
    {
        $parties = ["Tableur « {$nom} »"];

        foreach ($this->feuilles($chemin) as $f) {
            $parties[] = sprintf(
                "\nFeuille « %s » — %d ligne(s) de données, en-tête ligne %d : %s",
                $f['nom'], $f['lignes'], $f['entete_ligne'], $f['colonnes'] ? implode(' | ', $f['colonnes']) : '(aucun en-tête détecté)',
            );
            foreach ($this->lignes($chemin, $f['nom'], 1, $lignesParFeuille + $f['entete_ligne']) as $ligne) {
                $parties[] = sprintf('%4d | %s', $ligne['numero'], implode(' | ', $ligne['cellules']));
            }
            if ($f['lignes'] > $lignesParFeuille) {
                $parties[] = '  … ('.($f['lignes'] - $lignesParFeuille).' autres lignes, voir lire_tableur si nécessaire)';
            }
        }

        return implode("\n", $parties);
    }

    private function ouvrir(string $chemin): Spreadsheet
    {
        $lecteur = IOFactory::createReaderForFile($chemin);
        $lecteur->setReadDataOnly(true);

        if ($lecteur instanceof Csv) {
            // Point-virgule ou virgule : on prend le séparateur le plus fréquent
            // sur la première ligne, comme le ferait un tableur.
            $premiere = (string) fgets(fopen($chemin, 'r') ?: throw new \RuntimeException("Impossible d'ouvrir {$chemin}"));
            $lecteur->setDelimiter(substr_count($premiere, ';') > substr_count($premiere, ',') ? ';' : ',');
            $lecteur->setInputEncoding(mb_check_encoding($premiere, 'UTF-8') ? 'UTF-8' : 'ISO-8859-1');
        }

        return $lecteur->load($chemin);
    }

    private function feuille(Spreadsheet $classeur, string $nom): Worksheet
    {
        return $classeur->getSheetByName($nom)
            ?? (is_numeric($nom) ? $classeur->getSheet((int) $nom) : null)
            ?? throw new \InvalidArgumentException("Feuille « {$nom} » introuvable.");
    }

    /**
     * Première ligne dont au moins deux cellules sont remplies : c'est
     * l'en-tête, les titres du document au-dessus ne comptent pas.
     */
    private function ligneEntete(Worksheet $feuille): int
    {
        $max = min($feuille->getHighestDataRow(), 30);
        for ($i = 1; $i <= $max; $i++) {
            $remplies = count(array_filter($this->cellules($feuille, $i), fn ($c) => $c !== ''));
            if ($remplies >= 2) {
                return $i;
            }
        }

        return 0;
    }

    /**
     * @return list<string>
     */
    private function cellules(Worksheet $feuille, int $ligne): array
    {
        $derniere = $feuille->getHighestDataColumn();
        $cellules = [];

        foreach ($feuille->getRowIterator($ligne, $ligne) as $row) {
            $it = $row->getCellIterator('A', $derniere);
            $it->setIterateOnlyExistingCells(false);
            foreach ($it as $cellule) {
                $cellules[] = $this->texte($cellule);
            }
        }

        // Colonnes vides en fin de ligne : sans intérêt.
        while ($cellules && end($cellules) === '') {
            array_pop($cellules);
        }

        return $cellules;
    }

    private function texte(Cell $cellule): string
    {
        $valeur = $cellule->getValue();
        if ($valeur === null) {
            return '';
        }
        if (Date::isDateTime($cellule) && is_numeric($valeur)) {
            return Date::excelToDateTimeObject((float) $valeur)->format('Y-m-d');
        }
        if (is_float($valeur) && floor($valeur) == $valeur) {
            return (string) (int) $valeur;
        }

        return trim(preg_replace('/\s+/u', ' ', (string) $cellule->getFormattedValue()) ?? '');
    }
}
