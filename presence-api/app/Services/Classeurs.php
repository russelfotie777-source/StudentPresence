<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Les mêmes documents que les PDF, en classeur Excel : la liste de présence
 * hebdomadaire d'une salle (une feuille), celles d'un département (une
 * feuille par salle) et la comptabilité d'une période. Le secrétariat les
 * retravaille, les colle dans ses propres tableaux, les archive — ce qu'un
 * PDF ne permet pas.
 */
class Classeurs
{
    private const GRIS = 'FFE7EAE8';

    private const FM = 'FFFFF1D6';

    /**
     * @param  array<string, mixed>  $donnees  ce que produit ListeHebdomadaire::pour()
     */
    public function listeHebdomadaire(array $donnees): Spreadsheet
    {
        $classeur = new Spreadsheet;
        $this->feuilleDeListe($classeur->getActiveSheet(), $donnees);

        return $classeur;
    }

    /**
     * @param  array<string, mixed>  $donnees  ce que produit ListeHebdomadaire::pourDepartement()
     */
    public function listeDepartement(array $donnees): Spreadsheet
    {
        $classeur = new Spreadsheet;
        $classeur->removeSheetByIndex(0);

        /** @var Collection<int, array<string, mixed>> $listes */
        $listes = $donnees['listes'];
        foreach ($listes as $liste) {
            $this->feuilleDeListe($classeur->createSheet(), $liste);
        }
        $classeur->setActiveSheetIndex(0);

        return $classeur;
    }

    /**
     * @param  array<string, mixed>  $donnees
     */
    private function feuilleDeListe(Worksheet $feuille, array $donnees): void
    {
        $feuille->setTitle(self::titreDeFeuille($donnees['salle']));
        $signe = $donnees['symboles'] === 'valeur' ? ['present' => '+1', 'absent' => '−1'] : ['present' => '✓', 'absent' => '✗'];
        $e = $donnees['etablissement'];

        // En-tête officiel : la colonne française, le centre, la colonne anglaise.
        $gauche = [
            'REPUBLIQUE DU CAMEROUN', 'Paix – Travail – Patrie', "MINISTERE DE L'ENSEIGNEMENT SUPERIEUR", 'UNIVERSITE DE DOUALA',
            'INSTITUT UNIVERSITAIRE DE TECHNOLOGIE', $donnees['departement_fr'], "BP. {$e['bp']} · Tél : {$e['tel']} · {$e['email']}",
        ];
        $droite = [
            'REPUBLIC OF CAMEROON', 'Peace – Work – Fatherland', 'MINISTRY OF HIGHER EDUCATION', 'THE UNIVERSITY OF DOUALA',
            'UNIVERSITY INSTITUTE OF TECHNOLOGY', $donnees['departement_en'], "PO Box : {$e['bp']} · Phone : {$e['tel']} · {$e['email']}",
        ];
        $centre = [
            '', $donnees['departement_fr'],
            "OPTION : {$donnees['option']}   NIVEAU : {$donnees['niveau_romain']}   ANNEE ACADEMIQUE : {$donnees['annee_academique']}",
            $donnees['titre'], "SEMESTRE {$donnees['semestre']}", '', '',
        ];
        foreach ($gauche as $i => $texte) {
            $ligne = $i + 1;
            $feuille->mergeCells("A{$ligne}:C{$ligne}")->setCellValue("A{$ligne}", $texte);
            $feuille->mergeCells("D{$ligne}:F{$ligne}")->setCellValue("D{$ligne}", $centre[$i]);
            $feuille->mergeCells("G{$ligne}:I{$ligne}")->setCellValue("G{$ligne}", $droite[$i]);
            $feuille->getStyle("A{$ligne}:I{$ligne}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setWrapText(true);
            $feuille->getStyle("A{$ligne}:I{$ligne}")->getFont()->setSize(8);
        }
        $feuille->getStyle('A6:C6')->getFont()->setBold(true);
        $feuille->getStyle('G6:I6')->getFont()->setBold(true);
        $feuille->getStyle('D2:F2')->getFont()->setBold(true)->setSize(10);
        $feuille->getStyle('D4:F4')->getFont()->setBold(true)->setSize(11);

        // La ligne salle / groupe / semaine.
        $feuille->mergeCells('A9:C9')->setCellValue('A9', "SALLE : {$donnees['salle']}");
        $feuille->mergeCells('D9:F9')->setCellValue('D9', $donnees['groupe']);
        $feuille->mergeCells('G9:I9')->setCellValue('G9', "SEMAINE DU {$donnees['semaine_du']} AU {$donnees['semaine_au']}");
        $feuille->getStyle('A9:I9')->getFont()->setBold(true);
        $feuille->getStyle('G9')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        // Les étudiants : une colonne de marques par jour, comme sur la liste papier.
        $ligne = 11;
        $this->ligne($feuille, $ligne, ['N°', 'Matricule', 'Noms & Prénoms', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi']);
        $this->entete($feuille, "A{$ligne}:I{$ligne}");

        /** @var Collection<int, array<string, mixed>> $etudiants */
        $etudiants = $donnees['etudiants'];
        foreach ($etudiants as $etudiant) {
            $ligne++;
            $feuille->setCellValue("A{$ligne}", $etudiant['numero']);
            $feuille->setCellValueExplicit("B{$ligne}", $etudiant['matricule'], DataType::TYPE_STRING);
            $feuille->setCellValue("C{$ligne}", $etudiant['nom'].($etudiant['fm'] ? '  (FM)' : ''));
            $colonne = 'D';
            foreach ($etudiant['jours'] as $marques) {
                $feuille->setCellValue("{$colonne}{$ligne}", collect($marques)->filter()->map(fn ($m) => $signe[$m])->implode(' '));
                $feuille->getStyle("{$colonne}{$ligne}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $colonne++;
            }
            if ($etudiant['fm']) {
                $feuille->getStyle("A{$ligne}:I{$ligne}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::FM);
            }
        }
        if ($etudiants->isEmpty()) {
            $ligne++;
            $feuille->mergeCells("A{$ligne}:I{$ligne}")->setCellValue("A{$ligne}", 'Aucun étudiant rattaché à cette salle.');
        }
        $this->bordures($feuille, "A11:I{$ligne}");

        // Légende, seulement quand elle sert.
        if ($donnees['contient_marques'] || $etudiants->contains('fm', true)) {
            $ligne += 2;
            $legende = [];
            if ($donnees['contient_marques']) {
                $legende[] = "{$signe['present']} présent   {$signe['absent']} absent   (une marque par séance, dans l'ordre des horaires ; case vide : séance à venir ou appel non validé)";
            }
            if ($etudiants->contains('fm', true)) {
                $legende[] = '(FM) : étudiant en formation migrante, venu de l\'alternance, rattaché à cette salle de formation initiale.';
            }
            $feuille->mergeCells("A{$ligne}:I{$ligne}")->setCellValue("A{$ligne}", implode("\n", $legende));
            $feuille->getStyle("A{$ligne}")->getAlignment()->setWrapText(true);
            $feuille->getStyle("A{$ligne}")->getFont()->setItalic(true)->setSize(8);
            $feuille->getRowDimension($ligne)->setRowHeight(count($legende) * 13 + 4);
        }

        // Les séances de la semaine, à signer.
        $ligne += 2;
        foreach (['A' => 'Dates', 'B' => 'Codes et intitulés des EC', 'D' => 'Noms des enseignants', 'E' => 'Heure de début', 'F' => 'Heure de fin', 'G' => 'Durée', 'H' => 'Signature enseignant', 'I' => 'Signature délégué'] as $colonne => $texte) {
            $feuille->setCellValue("{$colonne}{$ligne}", $texte);
        }
        $feuille->mergeCells("B{$ligne}:C{$ligne}");
        $this->entete($feuille, "A{$ligne}:I{$ligne}");
        $debutSeances = $ligne;
        foreach ($donnees['jours'] as $jour) {
            $premiere = $ligne + 1;
            foreach ($jour['seances'] as $s) {
                $ligne++;
                $feuille->setCellValue("A{$ligne}", $jour['label']);
                $feuille->mergeCells("B{$ligne}:C{$ligne}")->setCellValue("B{$ligne}", $s['ec'] ?? '');
                $feuille->setCellValue("D{$ligne}", $s['enseignant'] ?? '');
                $feuille->setCellValue("E{$ligne}", $s['debut'] ?? '');
                $feuille->setCellValue("F{$ligne}", $s['fin'] ?? '');
                $feuille->setCellValue("G{$ligne}", $s['duree'] ?? '');
            }
            if ($ligne > $premiere) {
                $feuille->mergeCells("A{$premiere}:A{$ligne}");
            }
            $feuille->getStyle("A{$premiere}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $feuille->getStyle("A{$premiere}")->getFont()->setBold(true);
        }
        $this->bordures($feuille, "A{$debutSeances}:I{$ligne}");

        foreach (['A' => 6, 'B' => 14, 'C' => 34, 'D' => 12, 'E' => 12, 'F' => 12, 'G' => 12, 'H' => 14, 'I' => 14] as $colonne => $largeur) {
            $feuille->getColumnDimension($colonne)->setWidth($largeur);
        }
        $feuille->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)->setPaperSize(PageSetup::PAPERSIZE_A4)->setFitToWidth(1)->setFitToHeight(0);
        $feuille->freezePane('A12');
    }

    /**
     * La comptabilité d'une période : une feuille de synthèse (effectifs,
     * séances, assiduité, migrations) et une feuille de paie, une ligne par
     * enseignant avec les totaux — ce que la comptabilité reprend telle
     * quelle dans ses états.
     *
     * @param  array<string, mixed>  $c  ce que produit Comptabilite::pour()
     */
    public function comptabilite(array $c): Spreadsheet
    {
        $classeur = new Spreadsheet;
        $synthese = $classeur->getActiveSheet()->setTitle('Synthèse');
        $periode = "du {$c['periode']['du']} au {$c['periode']['au']}";

        $lignes = [
            ['Présence · Comptabilité', $periode],
            [],
            ['Effectifs'],
            ['Étudiants', $c['effectifs']['etudiants']],
            ['dont FI', $c['effectifs']['par_formation']['FI']],
            ['dont FA', $c['effectifs']['par_formation']['FA']],
            ['dont FM', $c['effectifs']['par_formation']['FM']],
            ['Délégués', $c['effectifs']['delegues']],
            ['Enseignants validés', $c['effectifs']['enseignants']],
            ['Comptes restreints ou bloqués', $c['effectifs']['comptes_restreints']],
            ['Présence automatique', $c['effectifs']['presence_automatique']],
            [],
            ['Par département', 'Étudiants', 'Salles'],
        ];
        foreach ($c['effectifs']['par_departement'] as $d) {
            $lignes[] = ["{$d['code']} — {$d['nom']}", $d['etudiants'], $d['salles']];
        }
        $lignes = array_merge($lignes, [
            [],
            ['Séances'],
            ['Programmées', $c['seances']['programmees']],
            ['Passées', $c['seances']['passees']],
            ['Tenues', $c['seances']['tenues']],
            ['Non tenues', $c['seances']['non_tenues']],
            ['Taux de tenue (%)', $c['seances']['taux_tenue']],
            ['Confirmées par le délégué', $c['seances']['confirmees_par_delegue']],
            ['Heures effectuées', $c['seances']['heures_effectuees']],
            ['À venir', $c['seances']['a_venir']],
            [],
            ['Assiduité'],
            ['Appels', $c['assiduite']['appels']],
            ['Présents', $c['assiduite']['presents']],
            ['Absents', $c['assiduite']['absents']],
            ['Taux de présence (%)', $c['assiduite']['taux']],
            ['Présences posées par l\'admin', $c['assiduite']['forcees_par_admin']],
            ['Présences automatiques', $c['assiduite']['automatiques']],
            [],
            ['Migrations FA → FI'],
            ['En attente', $c['migrations']['en_attente']],
            ['Acceptées', $c['migrations']['acceptees']],
            ['Rejetées', $c['migrations']['rejetees']],
        ]);
        foreach ($lignes as $i => $l) {
            $this->ligne($synthese, $i + 1, $l);
        }
        $synthese->getStyle('A1')->getFont()->setBold(true)->setSize(13);
        foreach ($lignes as $i => $l) {
            if (count($l) === 1 || (count($l) === 3 && $l[0] === 'Par département')) {
                $synthese->getStyle('A'.($i + 1).':C'.($i + 1))->getFont()->setBold(true);
            }
        }
        $synthese->getColumnDimension('A')->setWidth(36);
        $synthese->getColumnDimension('B')->setWidth(16);
        $synthese->getColumnDimension('C')->setWidth(12);

        $paie = $classeur->createSheet()->setTitle('Paie');
        $this->ligne($paie, 1, ['Enseignant', 'Séances tenues', 'Heures', 'Salaire (FCFA)', 'Pénalités de retard (FCFA)', 'Net (FCFA)']);
        $this->entete($paie, 'A1:F1');
        $ligne = 1;
        foreach ($c['paie']['enseignants'] as $l) {
            $ligne++;
            $this->ligne($paie, $ligne, [$l['name'], $l['seances'], $l['heures'], $l['salaire'], $l['penalites'], $l['salaire'] - $l['penalites']]);
        }
        $ligne++;
        $this->ligne($paie, $ligne, ['Total', null, $c['paie']['total_heures'], $c['paie']['total_salaire'], $c['paie']['total_penalites'], $c['paie']['total_salaire'] - $c['paie']['total_penalites']]);
        $paie->getStyle("A{$ligne}:F{$ligne}")->getFont()->setBold(true);
        $paie->getStyle("D2:F{$ligne}")->getNumberFormat()->setFormatCode('#,##0');
        $this->bordures($paie, "A1:F{$ligne}");
        foreach (['A' => 32, 'B' => 14, 'C' => 10, 'D' => 16, 'E' => 24, 'F' => 14] as $colonne => $largeur) {
            $paie->getColumnDimension($colonne)->setWidth($largeur);
        }
        $paie->freezePane('A2');
        $classeur->setActiveSheetIndex(0);

        return $classeur;
    }

    /**
     * Une ligne de cellules, à partir de la colonne A. Pas fromArray() : il
     * compare au sens large et laisse tomber les zéros, qui comptent ici.
     *
     * @param  array<int, mixed>  $valeurs
     */
    private function ligne(Worksheet $feuille, int $numero, array $valeurs): void
    {
        $colonne = 'A';
        foreach ($valeurs as $valeur) {
            if ($valeur !== null) {
                $feuille->setCellValue("{$colonne}{$numero}", $valeur);
            }
            $colonne++;
        }
    }

    private function entete(Worksheet $feuille, string $plage): void
    {
        $style = $feuille->getStyle($plage);
        $style->getFont()->setBold(true);
        $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::GRIS);
        $style->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
    }

    private function bordures(Worksheet $feuille, string $plage): void
    {
        $feuille->getStyle($plage)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    }

    /** Un titre de feuille Excel : 31 caractères au plus, sans les caractères interdits. */
    public static function titreDeFeuille(string $nom): string
    {
        $titre = trim((string) preg_replace('/\s+/', ' ', str_replace(['\\', '/', '?', '*', '[', ']', ':'], ' ', $nom)));

        return Str::limit($titre !== '' ? $titre : 'Liste', 31, '');
    }
}
