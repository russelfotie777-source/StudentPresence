<?php
include 'includes/db.php';
require_once 'vendor/autoload.php';
use Dompdf\Dompdf;

$filiere_id = $_GET['filiere_id'] ?? 0;

// 1. Récupération des informations de base
$filiere = $pdo->prepare("SELECT f.*, n.nom as niveau_nom FROM filieres f JOIN niveaux n ON f.niveau_id = n.id WHERE f.id = ?");
$filiere->execute([$filiere_id]);
$filiere = $filiere->fetch();

if (!$filiere) die("Filière non trouvée");

// 2. Récupération des salles de la filière
$salles_query = "SELECT * FROM salles WHERE filiere_id = ? ORDER BY formation DESC, nom";
$salles_stmt = $pdo->prepare($salles_query);
$salles_stmt->execute([$filiere_id]);
$salles = $salles_stmt->fetchAll();

// 3. Pour chaque salle, récupérer toutes les séances programmées
$emplois_details = [];
foreach ($salles as $salle) {
    $seances_query = "
        SELECT 
            sc.*, 
            m.nom as matiere_nom, 
            m.code as matiere_code,
            u.name as enseignant_nom,
            c.jour as jour_programme,
            c.heure_debut as heure_debut_programmee,
            c.heure_fin as heure_fin_programmee
        FROM seances sc
        JOIN cours c ON sc.cours_id = c.id
        JOIN matieres m ON c.matiere_id = m.id
        JOIN users u ON sc.enseignant_id = u.id
        WHERE sc.salle_id = ?
        ORDER BY sc.jour, sc.heure_debut";
    
    $seances_stmt = $pdo->prepare($seances_query);
    $seances_stmt->execute([$salle['id']]);
    $emplois_details[$salle['id']] = $seances_stmt->fetchAll();
}

// 4. Génération du PDF
$dompdf = new Dompdf();
$html = generateHtml($filiere, $salles, $emplois_details);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();

// 5. Envoi du PDF
$filename = 'emplois_'.normalize($filiere['niveau_nom']).'_'.normalize($filiere['nom']).'.pdf';
$dompdf->stream($filename, ["Attachment" => true]);

// Fonctions utilitaires
function normalize($string) {
    return preg_replace('/[^a-zA-Z0-9]/', '_', $string);
}

function generateHtml($filiere, $salles, $emplois_details) {
    $html = '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Emplois du temps - '.htmlspecialchars($filiere['niveau_nom']).' '.htmlspecialchars($filiere['nom']).'</title>
        <style>
            body { font-family: Arial; margin: 0; padding: 15px; font-size: 12px; }
            .header { text-align: center; margin-bottom: 20px; }
            .header h2 { font-size: 17px; margin: 5px 0; }
            .header h3 { font-size: 15px; margin: 4px 0; }
            .filiere-info { background: #f8f9fa; padding: 10px; text-align: center; margin-bottom: 20px; font-size: 13px; }
            .section-title { 
                color: white; 
                padding: 8px 12px; 
                border-radius: 4px; 
                margin: 25px 0 15px 0;
                font-size: 15px;
            }
            .section-title.fi { background: #0d6efd; }
            .section-title.fa { background: #ffc107; }
            .salle-title { 
                font-weight: bold; 
                margin: 20px 0 12px 0;
                padding-bottom: 5px;
                border-bottom: 2px solid #eee;
                font-size: 14px;
            }
            table { width: 100%; border-collapse: collapse; margin-bottom: 25px; font-size: 11px; }
            th, td { border: 1px solid #ddd; padding: 6px; text-align: center; }
            th { background: #f2f2f2; font-weight: bold; }
            .page-break { page-break-after: always; }
            .no-data { color: #6c757d; font-style: italic; text-align: center; padding: 15px; font-size: 12px; }
            .salle-container { page-break-inside: avoid; }
        </style>
    </head>
    <body>
        <div class="header">
            <h2>Département Génie Informatique</h2>
            <h3>Emplois de temps hebdomadaire (15/09/2025 au 20/09/2025)</h3>
        </div>
        
        <div class="filiere-info">
            <h4>'.$filiere['niveau_nom'].' - '.htmlspecialchars($filiere['nom']).'</h4>
        </div>';
    
    // Séparation FI/FA
    $salles_fi = array_filter($salles, fn($s) => $s['formation'] === 'FI');
    $salles_fa = array_filter($salles, fn($s) => $s['formation'] === 'FA');
    
    // Génération des emplois FI
    if (!empty($salles_fi)) {
        $html .= '<div class="section-title fi">Formation Initiale (FI)</div>';
        
        foreach ($salles_fi as $i => $salle) {
            $html .= '<div class="salle-container">';
            $html .= generateSalleHtml($salle, $emplois_details);
            $html .= '</div>';
            if ($i < count($salles_fi) - 1 || !empty($salles_fa)) {
                $html .= '<div class="page-break"></div>';
            }
        }
    }
    
    // Génération des emplois FA
    if (!empty($salles_fa)) {
        $html .= '<div class="section-title fa">Formation en Alternance (FA)</div>';
        
        foreach ($salles_fa as $i => $salle) {
            $html .= '<div class="salle-container">';
            $html .= generateSalleHtml($salle, $emplois_details);
            $html .= '</div>';
            if ($i < count($salles_fa) - 1) {
                $html .= '<div class="page-break"></div>';
            }
        }
    }
    
    $html .= '</body></html>';
    return $html;
}

function generateSalleHtml($salle, $emplois_details) {
    $html = '<div class="salle-title">Salle '.htmlspecialchars($salle['nom']).'</div>';
    
    if (empty($emplois_details[$salle['id']])) {
        return $html . '<div class="no-data">Aucune séance programmée pour cette salle</div>';
    }
    
    $cours_par_jour = [];
    foreach ($emplois_details[$salle['id']] as $seance) {
        $jour = $seance['jour'] ?? $seance['jour_programme'];
        $cours_par_jour[$jour][] = $seance;
    }
    
    $jours_ordre = ['LUNDI','MARDI','MERCREDI','JEUDI','VENDREDI','SAMEDI','DIMANCHE'];
    $html .= '<table>
                <thead>
                    <tr>
                        <th style="width: 12%;">Jour</th>
                        <th style="width: 35%;">Matière</th>
                        <th style="width: 25%;">Enseignant</th>
                        <th style="width: 14%;">Heure début</th>
                        <th style="width: 14%;">Heure fin</th>
                    </tr>
                </thead>
                <tbody>';
    
    foreach ($jours_ordre as $jour) {
        if (!empty($cours_par_jour[$jour])) {
            $first = true;
            foreach ($cours_par_jour[$jour] as $seance) {
                $html .= '<tr>';
                if ($first) {
                    $html .= '<td rowspan="'.count($cours_par_jour[$jour]).'">'.ucfirst(strtolower($jour)).'</td>';
                    $first = false;
                }
                
                $html .= '<td style="text-align: left;">'.htmlspecialchars($seance['matiere_code']).' - '.htmlspecialchars($seance['matiere_nom']).'</td>
                          <td style="text-align: left;">'.htmlspecialchars($seance['enseignant_nom']).'</td>
                          <td>'.substr($seance['heure_debut'], 0, 5).'</td>
                          <td>'.substr($seance['heure_fin'], 0, 5).'</td>
                      </tr>';
            }
        } else {
            $html .= '<tr>
                        <td>'.ucfirst(strtolower($jour)).'</td>
                        <td colspan="4" class="no-data">Aucun cours</td>
                      </tr>';
        }
    }
    
    $html .= '</tbody></table>';
    return $html;
}