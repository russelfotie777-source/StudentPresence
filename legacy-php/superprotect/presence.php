<?php
include 'includes/db.php';
require_once 'vendor/autoload.php';

use Dompdf\Dompdf;

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $semaine_id = $_POST['semaine'] ?? '';
    $jour = $_POST['jour'] ?? '';
    $salle_id = $_POST['salle'] ?? '';
    $formation = $_POST['formation'] ?? 'FI';
    $mode_affichage = $_POST['mode_affichage'] ?? 'par_jour';
    
    if (!empty($semaine_id)) {
        if ($mode_affichage === 'par_jour' && !empty($jour) && !empty($salle_id)) {
            // Mode original - par jour et salle
            $querySeances = "SELECT se.id 
                            FROM seances se
                            JOIN salles sa ON se.salle_id = sa.id
                            WHERE se.semaine_id = ?
                            AND se.jour = ?
                            AND se.salle_id = ?
                            AND sa.formation = ?";
            
            $stmtSeances = $pdo->prepare($querySeances);
            $stmtSeances->execute([$semaine_id, $jour, $salle_id, $formation]);
            $seancesIds = $stmtSeances->fetchAll(PDO::FETCH_COLUMN);
            
            // Récupérer les présences pour ces séances
            if (!empty($seancesIds)) {
                $placeholders = rtrim(str_repeat('?,', count($seancesIds)), ',');
                
                $queryPresences = "SELECT 
                                    u.id AS etudiant_id,
                                    u.name AS etudiant_nom,
                                    u.classroom AS salle_classe,
                                    m.nom AS matiere_nom,
                                    se.heure_debut,
                                    se.heure_fin,
                                    pe.date_marquage,
                                    CONCAT(prof.name, ' (', TIME_FORMAT(se.debut_reel, '%H:%i'), '-', TIME_FORMAT(se.fin_reelle, '%H:%i'), ')') AS enseignant_info,
                                    w.numero AS semaine_numero,
                                    sa.nom AS salle_nom,
                                    pe.etat AS presence_etat
                                  FROM presences_etudiants pe
                                  JOIN users u ON pe.etudiant_id = u.id
                                  JOIN seances se ON pe.seance_id = se.id
                                  JOIN salles sa ON se.salle_id = sa.id
                                  JOIN semaines w ON se.semaine_id = w.id
                                  JOIN cours c ON se.cours_id = c.id
                                  JOIN matieres m ON c.matiere_id = m.id
                                  JOIN users prof ON se.enseignant_id = prof.id
                                  WHERE pe.seance_id IN ($placeholders)
                                  AND u.grade = 'Etudiant'
                                  ORDER BY u.classroom, u.name, pe.date_marquage, se.heure_debut";
                
                $stmtPresences = $pdo->prepare($queryPresences);
                $stmtPresences->execute($seancesIds);
                $presences = $stmtPresences->fetchAll();
                
                // Préparation des données pour le PDF (regroupement par classe et étudiant)
                $presencesPdf = [];
                foreach ($presences as $presence) {
                    $classe = $presence['salle_classe'];
                    $etudiant_id = $presence['etudiant_id'];
                    if (!isset($presencesPdf[$classe])) {
                        $presencesPdf[$classe] = [];
                    }
                    if (!isset($presencesPdf[$classe][$etudiant_id])) {
                        $presencesPdf[$classe][$etudiant_id] = [
                            'etudiant_nom' => $presence['etudiant_nom'],
                            'salle_classe' => $presence['salle_classe'],
                            'presences' => []
                        ];
                    }
                    $presencesPdf[$classe][$etudiant_id]['presences'][] = [
                        'matiere_nom' => $presence['matiere_nom'],
                        'date_marquage' => $presence['date_marquage'],
                        'heure_debut' => $presence['heure_debut'],
                        'heure_fin' => $presence['heure_fin'],
                        'enseignant_info' => $presence['enseignant_info'],
                        'salle_nom' => $presence['salle_nom'],
                        'semaine_numero' => $presence['semaine_numero'],
                        'presence_etat' => $presence['presence_etat']
                    ];
                }
            } else {
                $presences = [];
                $presencesPdf = [];
            }
        } elseif ($mode_affichage === 'par_semaine') {
            // Nouveau mode - par semaine pour toute la formation
            $queryPresences = "SELECT 
                                u.id AS etudiant_id,
                                u.name AS etudiant_nom,
                                u.classroom AS salle_classe,
                                u.formation,
                                pe.date_marquage,
                                m.nom AS matiere,
                                sa.nom AS salle_nom,
                                se.heure_debut,
                                se.heure_fin,
                                w.numero AS semaine_numero,
                                pe.etat AS presence_etat,
                                se.jour AS jour_seance
                              FROM presences_etudiants pe
                              JOIN users u ON pe.etudiant_id = u.id
                              JOIN seances se ON pe.seance_id = se.id
                              JOIN semaines w ON se.semaine_id = w.id
                              JOIN cours c ON se.cours_id = c.id
                              JOIN matieres m ON c.matiere_id = m.id
                              JOIN salles sa ON se.salle_id = sa.id
                              WHERE se.semaine_id = ?
                              AND u.grade = 'Etudiant'
                              AND u.formation = ?
                              ORDER BY u.classroom, u.name, se.jour, se.heure_debut";
            
            $stmtPresences = $pdo->prepare($queryPresences);
            $stmtPresences->execute([$semaine_id, $formation]);
            $presences = $stmtPresences->fetchAll();
            
            // Préparation des données pour le PDF (regroupement par classe et étudiant)
            $presencesPdf = [];
            foreach ($presences as $presence) {
                $classe = $presence['salle_classe'];
                $etudiant_id = $presence['etudiant_id'];
                if (!isset($presencesPdf[$classe])) {
                    $presencesPdf[$classe] = [];
                }
                if (!isset($presencesPdf[$classe][$etudiant_id])) {
                    $presencesPdf[$classe][$etudiant_id] = [
                        'etudiant_nom' => $presence['etudiant_nom'],
                        'salle_classe' => $presence['salle_classe'],
                        'presences' => []
                    ];
                }
                $presencesPdf[$classe][$etudiant_id]['presences'][] = [
                    'matiere' => $presence['matiere'],
                    'date_marquage' => $presence['date_marquage'],
                    'salle_nom' => $presence['salle_nom'],
                    'semaine_numero' => $presence['semaine_numero'],
                    'formation' => $presence['formation'],
                    'heure_debut' => $presence['heure_debut'],
                    'heure_fin' => $presence['heure_fin'],
                    'presence_etat' => $presence['presence_etat'],
                    'jour_seance' => $presence['jour_seance']
                ];
            }
        }
        
        // Génération du PDF
        if (isset($_POST['generate_pdf'])) {
            $html = generatePdfContent($presencesPdf, $jour, $formation, $semaine_id, $pdo, $mode_affichage);
            $dompdf = new Dompdf();
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'landscape'); // Changement en mode paysage pour plus d'espace
            $dompdf->render();
            
            $semaine_info = $pdo->prepare("SELECT numero FROM semaines WHERE id = ?");
            $semaine_info->execute([$semaine_id]);
            $semaine = $semaine_info->fetch();
            
            if ($mode_affichage === 'par_jour') {
                $salle_nom = $presences[0]['salle_nom'] ?? 'Salle';
                $filename = "presences_{$salle_nom}_{$jour}_semaine{$semaine['numero']}.pdf";
            } else {
                $filename = "presences_{$formation}_semaine{$semaine['numero']}.pdf";
            }
            
            $dompdf->stream($filename, ["Attachment" => true]);
            exit;
        }
    }
}

function getPlageHoraire($heure_debut, $formation, $jour) {
    if ($formation === 'FA') {
        if ($jour === 'SAMEDI') {
            if ($heure_debut < '12:00:00') {
                return 'Matin (7h30-11h30)';    // â† Modifié ici
            } else {
                return 'Apre¨s-midi (12h-16h)';  // â† Modifié ici
            }
        } elseif ($jour === 'MERCREDI') {
            if ($heure_debut < '18:00:00') {
                return 'Apre¨s-midi (14h-18h)';
            } else {
                return 'Soir (18h-22h)';
            }
        } else {
            if ($heure_debut < '19:00:00') {
                return 'Soir 1 (16h-19h)';
            } else {
                return 'Soir 2 (19h-22h)';
            }
        }
    } else {
        // FI et FM
        if ($jour === 'MERCREDI') {
            if ($heure_debut < '11:00:00') {
                return 'Matin (7h30-10h30)';
            } else {
                return 'Apre¨s-midi (11h-14h)';
            }
        } else {
            if ($heure_debut < '12:00:00') {
                return 'Matin (7h30-11h30)';
            } else {
                return 'Apre¨s-midi (12h-16h)';
            }
        }
    }
}

function generatePdfContent($presencesPdf, $jour, $formation, $semaine_id, $pdo, $mode_affichage = 'par_jour') {
    // Récupérer les infos de la semaine
    $semaine_info = $pdo->prepare("SELECT numero, date_debut, date_fin FROM semaines WHERE id = ?");
    $semaine_info->execute([$semaine_id]);
    $semaine = $semaine_info->fetch();
    
    // Déterminer si on doit afficher le samedi selon la formation
    $showSaturday = ($formation === 'FA');
    
    $html = '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Liste de présence</title>
        <style>
            @font-face {
                font-family: \'Avenir\';
                font-weight: normal;
                src: url(\'https://fonts.gstatic.com/s/robotoslab/v14/BngbUXZYtxQPIvDITG9gO5PzS-bT6.woff2\') format(\'woff2\');
            }
            body { 
                font-family: "Avenir", sans-serif; 
                margin: 20px; 
                color: #333;
                font-size: 10px;
            }
            h1 { 
                color: #6e48aa; 
                text-align: center; 
                font-size: 18px; 
                margin-bottom: 5px; 
            }
            h2 {
                color: #6e48aa;
                text-align: center;
                font-size: 12px;
                margin-top: 0;
            }
            h3 {
                color: #6e48aa;
                font-size: 14px;
                margin-top: 20px;
                margin-bottom: 10px;
                border-bottom: 2px solid #6e48aa;
                padding-bottom: 5px;
            }
            .header-info { 
                margin-bottom: 15px; 
                text-align: center; 
                font-size: 10px; 
            }
            .header-info p {
                margin: 2px 0;
            }
            table { 
                width: 100%; 
                border-collapse: collapse; 
                margin-top: 10px; 
                font-size: 9px;
            }
            th, td { 
                padding: 6px; 
                text-align: center; 
                border: 1px solid #ddd;
            }
            th { 
                background-color: #6e48aa; 
                color: white; 
            }
            .present {
                color: green;
                font-weight: bold;
            }
            .absent {
                color: red;
                font-weight: bold;
            }
            .footer { 
                margin-top: 20px; 
                text-align: right; 
                font-size: 8px; 
            }
            .day-column {
                width: 8%;
            }
            .name-column {
                width: 15%;
            }
            .note-column {
                width: 5%;
                font-weight: bold;
            }
        </style>
    </head>
    <body>
        <h1>Liste de présence</h1>
        <h2>Département de génie Informatique</h2>
        <div class="header-info">
            <p><strong>Semaine:</strong> '.htmlspecialchars($semaine['numero']+1).' ('.htmlspecialchars($semaine['date_debut']).' au '.htmlspecialchars($semaine['date_fin']).')</p>
            <p><strong>Formation:</strong> '.htmlspecialchars($formation).'</p>';
    
    if ($mode_affichage === 'par_jour') {
        $html .= '<p><strong>Jour:</strong> '.htmlspecialchars($jour).'</p>';
        $firstPresence = array_key_first($presencesPdf);
        if ($firstPresence !== null && isset($presencesPdf[$firstPresence][0]['salle_nom'])) {
            $html .= '<p><strong>Salle:</strong> '.htmlspecialchars($presencesPdf[$firstPresence][0]['salle_nom']).'</p>';
        }
    } else {
        $html .= '<p><strong>Mode:</strong> Vue globale de la semaine</p>';
    }
    
    $html .= '</div>';
    
    if (!empty($presencesPdf)) {
        // Boucle sur chaque classe
        foreach ($presencesPdf as $classe => $etudiants) {
            $html .= '<h3>Classe : '.htmlspecialchars($classe).'</h3>';
            $html .= '<table>
                        <thead>
                            <tr>
                                <th class="name-column">étudiant</th>';
            
            // Jours de la semaine (samedi conditionnel)
            $jours = ['LUNDI', 'MARDI', 'MERCREDI', 'JEUDI', 'VENDREDI'];
            if ($showSaturday) {
                $jours[] = 'SAMEDI';
            }
            
            // En-teªtes des colonnes par jour
            foreach ($jours as $jour_semaine) {
                if ($formation === 'FA') {
                    if ($jour_semaine === 'MERCREDI') {
                        $html .= '<th class="day-column">'.$jour_semaine.'<br>Apres-midi (14h-18h)</th>
                                  <th class="day-column">'.$jour_semaine.'<br>Soir (18h-22h)</th>';
                    } elseif ($jour_semaine !== 'SAMEDI') {
                        $html .= '<th class="day-column">'.$jour_semaine.'<br>Soir 1 (16h-19h)</th>
                                  <th class="day-column">'.$jour_semaine.'<br>Soir 2 (19h-22h)</th>';
                    } else {
                        $html .= '<th class="day-column">'.$jour_semaine.'<br>Matin (7h30-11h30)</th>
                                  <th class="day-column">'.$jour_semaine.'<br>Apres-midi (12h-16h)</th>';
                    }
                } else {
                    if ($jour_semaine === 'MERCREDI') {
                        $html .= '<th class="day-column">'.$jour_semaine.'<br>Matin (7h30-10h30)</th>
                                  <th class="day-column">'.$jour_semaine.'<br>Apres-midi (11h-14h)</th>';
                    } else {
                        $html .= '<th class="day-column">'.$jour_semaine.'<br>Matin (7h30-11h30)</th>
                                  <th class="day-column">'.$jour_semaine.'<br>Apres-midi (12h-16h)</th>';
                    }
                }
            }
            
            $html .= '<th class="note-column">Note</th>
                            </tr>
                        </thead>
                        <tbody>';
            
            // Boucle sur les étudiants de la classe
            foreach ($etudiants as $etudiant_id => $etudiant_data) {
                $html .= '<tr>
                            <td>'.htmlspecialchars($etudiant_data['etudiant_nom']).'</td>';
                
                // Initialiser le compteur d'absences
                $absences = 0;
                
                // Pour chaque jour, vérifier les deux séances
                foreach ($jours as $jour_semaine) {
                    // Tableau pour stocker les présences par plage horaire
                    $presences_par_plage = [];
                    
                    // Vérifier les présences pour ce jour
                    foreach ($etudiant_data['presences'] as $presence) {
                        $jour_presence = isset($presence['jour_seance']) ? $presence['jour_seance'] : $jour;
                        
                        if ($jour_presence == $jour_semaine) {
                            $heure_debut = isset($presence['heure_debut']) ? $presence['heure_debut'] : '';
                            $plage = getPlageHoraire($heure_debut, $formation, $jour_semaine);
                            $presences_par_plage[$plage] = ($presence['presence_etat'] == 'present');
                        }
                    }
                    
                    // Déterminer les plages attendues pour ce jour
                    if ($formation === 'FA') {
                        if ($jour_semaine === 'MERCREDI') {
                            $plages_attendues = ['Apre¨s-midi (14h-18h)', 'Soir (18h-22h)'];
                        } elseif ($jour_semaine !== 'SAMEDI') {
                            $plages_attendues = ['Soir 1 (16h-19h)', 'Soir 2 (19h-22h)'];
                        } else {
                            $plages_attendues = ['Matin (7h30-11h30)', 'Apre¨s-midi (12h-16h)'];
                        }
                    } else {
                        if ($jour_semaine === 'MERCREDI') {
                            $plages_attendues = ['Matin (7h30-10h30)', 'Apre¨s-midi (11h-14h)'];
                        } else {
                            $plages_attendues = ['Matin (7h30-11h30)', 'Apre¨s-midi (12h-16h)'];
                        }
                    }
                    
                    // Afficher les cellules pour chaque plage horaire
                    foreach ($plages_attendues as $plage) {
                        if (isset($presences_par_plage[$plage])) {
                            if ($presences_par_plage[$plage]) {
                                $html .= '<td class="present">P</td>';
                            } else {
                                $html .= '<td class="absent">A</td>';
                                $absences++;
                            }
                        } else {
                            $html .= '<td class="absent">A</td>';
                            $absences++;
                        }
                    }
                }
                
                // Calcul de la note (0 si aucune absence, -X si X absences)
                $note = ($absences == 0) ? '+0' : '-'.$absences;
                
                $html .= '<td class="note-column">'.$note.'</td>
                        </tr>';
            }
            
            $html .= '</tbody>
                    </table>';
        }
    } else {
        $html .= '<p>Aucune donnée de présence trouvée pour les crite¨res sélectionnés.</p>';
    }
    
    $html .= '<div class="footer">
                <p>Généré le '.date('d/m/Y e  H:i').'</p>
            </div>
        </body>
    </html>';
    
    return $html;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Génération des listes de présence</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            padding: 20px;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background-color: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #6e48aa;
            margin-bottom: 30px;
            text-align: center;
        }
        .form-label {
            font-weight: bold;
        }
        .btn-primary {
            background-color: #6e48aa;
            border-color: #6e48aa;
        }
        .btn-primary:hover {
            background-color: #5a3a8a;
            border-color: #5a3a8a;
        }
    </style>
</head>

<?php include 'includes/admin-header.php'; ?>

<body>
    <div class="container">
        <h1>Génération des listes de présence</h1>
        
        <form method="POST">
            <div class="mb-3">
                <label for="mode_affichage" class="form-label">Mode d'affichage:</label>
                <select class="form-select" id="mode_affichage" name="mode_affichage" onchange="toggleModeFields()">
                    <option value="par_jour" <?= ($mode_affichage ?? 'par_jour') === 'par_jour' ? 'selected' : '' ?>>Par jour et salle</option>
                    <option value="par_semaine" <?= ($mode_affichage ?? '') === 'par_semaine' ? 'selected' : '' ?>>Par semaine (vue globale)</option>
                </select>
            </div>
            
            <div class="mb-3">
                <label for="semaine" class="form-label">Semaine:</label>
                <select class="form-select" id="semaine" name="semaine" required>
                    <option value="">Sélectionnez une semaine</option>
                    <?php
                    $querySemaines = "SELECT id, numero, date_debut, date_fin FROM semaines ORDER BY date_debut DESC";
                    $stmtSemaines = $pdo->query($querySemaines);
                    while ($semaine = $stmtSemaines->fetch()) {
                        $selected = ($semaine_id ?? '') == $semaine['id'] ? 'selected' : '';
                        echo "<option value='{$semaine['id']}' $selected>Semaine {$semaine['numero']} ({$semaine['date_debut']} au {$semaine['date_fin']})</option>";
                    }
                    ?>
                </select>
            </div>
            
            <div class="mb-3" id="formation-field">
                <label for="formation" class="form-label">Formation:</label>
                <select class="form-select" id="formation" name="formation">
                    <option value="FI" <?= ($formation ?? 'FI') === 'FI' ? 'selected' : '' ?>>Formation Initiale (FI)</option>
                    <option value="FA" <?= ($formation ?? '') === 'FA' ? 'selected' : '' ?>>Formation Alternance (FA)</option>
                    <option value="FM" <?= ($formation ?? '') === 'FM' ? 'selected' : '' ?>>Formation Mixte (FM)</option>
                </select>
            </div>
            
            <div class="mb-3" id="jour-field">
                <label for="jour" class="form-label">Jour:</label>
                <select class="form-select" id="jour" name="jour">
                    <option value="LUNDI" <?= ($jour ?? '') === 'LUNDI' ? 'selected' : '' ?>>Lundi</option>
                    <option value="MARDI" <?= ($jour ?? '') === 'MARDI' ? 'selected' : '' ?>>Mardi</option>
                    <option value="MERCREDI" <?= ($jour ?? '') === 'MERCREDI' ? 'selected' : '' ?>>Mercredi</option>
                    <option value="JEUDI" <?= ($jour ?? '') === 'JEUDI' ? 'selected' : '' ?>>Jeudi</option>
                    <option value="VENDREDI" <?= ($jour ?? '') === 'VENDREDI' ? 'selected' : '' ?>>Vendredi</option>
                    <option value="SAMEDI" <?= ($jour ?? '') === 'SAMEDI' ? 'selected' : '' ?>>Samedi</option>
                </select>
            </div>
            
            <div class="mb-3" id="salle-field">
                <label for="salle" class="form-label">Salle:</label>
                <select class="form-select" id="salle" name="salle">
                    <option value="">Sélectionnez une salle</option>
                    <?php
                    $querySalles = "SELECT id, nom FROM salles ORDER BY nom";
                    $stmtSalles = $pdo->query($querySalles);
                    while ($salle = $stmtSalles->fetch()) {
                        $selected = ($salle_id ?? '') == $salle['id'] ? 'selected' : '';
                        echo "<option value='{$salle['id']}' $selected>{$salle['nom']}</option>";
                    }
                    ?>
                </select>
            </div>
            
            <div class="d-grid gap-2">
                <button type="submit" name="generate_pdf" class="btn btn-primary">Générer le PDF</button>
            </div>
        </form>
    </div>

    <script>
        function toggleModeFields() {
            const mode = document.getElementById('mode_affichage').value;
            const jourField = document.getElementById('jour-field');
            const salleField = document.getElementById('salle-field');
            const formationField = document.getElementById('formation-field');
            
            if (mode === 'par_semaine') {
                jourField.style.display = 'none';
                salleField.style.display = 'none';
                formationField.style.display = 'block';
            } else {
                jourField.style.display = 'block';
                salleField.style.display = 'block';
                formationField.style.display = 'block';
            }
        }
        
        // Initialiser l'affichage au chargement
        document.addEventListener('DOMContentLoaded', function() {
            toggleModeFields();
        });
    </script>
</body>
</html>