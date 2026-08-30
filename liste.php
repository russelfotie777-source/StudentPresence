<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Vérification de l'accès délégué

// Vérification de l'ID de séance
if (!isset($_GET['seance_id'])) {
    $_SESSION['error'] = "Aucune séance spécifiée.";
    header('Location: dashboard.php');
    exit();
}

$seance_id = intval($_GET['seance_id']);

// Récupération des infos de la séance avec vérification d'accès
$query = "SELECT s.*, sl.nom as salle_nom, sl.formation as salle_formation, p.etudiants_presents, p.status as push_status,
                 m.nom as matiere_nom, m.code as matiere_code,
                 u.name as enseignant_nom
          FROM seances s
          JOIN salles sl ON s.salle_id = sl.id
          JOIN cours c ON s.cours_id = c.id
          JOIN matieres m ON c.matiere_id = m.id
          JOIN users u ON s.enseignant_id = u.id
          LEFT JOIN pushes p ON s.id = p.seance_id
          WHERE s.id = ? AND sl.nom = ?";
$stmt = $pdo->prepare($query);
$stmt->execute([$seance_id, $_SESSION['classroom']]);
$seance = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$seance) {
    $_SESSION['error'] = "Séance introuvable ou vous n'avez pas accès à cette séance.";
    header('Location: dashboard.php');
    exit();
}

// Vérification si la séance est verrouillée
$is_locked = ($seance['commentaires'] === 'PRESENCES_VERROUILLEES');

// Traitement du formulaire de confirmation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$is_locked) {
    if (isset($_POST['confirm_presences'])) {
        // Déterminer la formation de la salle pour la requête
        $salle_formation = $seance['salle_formation'] ?? 'FI';
        
        // Récupérer tous les étudiants de la salle avec les bons filtres (sans doublons)
        $query_all_students = "SELECT DISTINCT id FROM users 
                               WHERE grade = 'Etudiant' 
                               AND classroom = ? 
                               AND niveau_id = ?";
        
        // Ajouter le filtre de formation selon le type de salle
        if ($salle_formation === 'FI') {
            $query_all_students .= " AND (formation = 'FI' OR formation = 'FM')";
        } else {
            $query_all_students .= " AND formation = 'FA'";
        }
        
        $stmt_all_students = $pdo->prepare($query_all_students);
        $stmt_all_students->execute([$_SESSION['classroom'], $_SESSION['niveau_id']]);
        $all_students = $stmt_all_students->fetchAll(PDO::FETCH_COLUMN);
        
        // Récupérer les étudiants confirmés
        $confirmed_students = isset($_POST['students']) ? $_POST['students'] : [];
        $confirmed_count = count($confirmed_students);
        
        // Validation du nombre de présences
        if ($confirmed_count > $seance['etudiants_presents']) {
            $_SESSION['error'] = "Le nombre de confirmations ($confirmed_count) dépasse le nombre d'étudiants présents déclaré ({$seance['etudiants_presents']}).";
            header("Location: liste.php?seance_id=$seance_id");
            exit();
        }
        
        // Début de la transaction
        $pdo->beginTransaction();
        
        try {
            // Marquer tous les étudiants de la liste filtrée comme absents d'abord
            foreach ($all_students as $student_id) {
                $query = "INSERT INTO presences_etudiants (seance_id, etudiant_id, etat, date_marquage) 
                          VALUES (?, ?, 'absent', NOW())
                          ON DUPLICATE KEY UPDATE etat = 'absent', date_marquage = NOW()";
                $stmt = $pdo->prepare($query);
                $stmt->execute([$seance_id, $student_id]);
            }
            
            // Ensuite marquer les présents (le choix du délégué est définitif)
            foreach ($confirmed_students as $student_id) {
                $query = "UPDATE presences_etudiants SET etat = 'present', date_marquage = NOW() 
                          WHERE seance_id = ? AND etudiant_id = ?";
                $stmt = $pdo->prepare($query);
                $stmt->execute([$seance_id, $student_id]);
            }
            
            // Verrouiller la séance
            $query = "UPDATE seances SET commentaires = 'PRESENCES_VERROUILLEES' WHERE id = ?";
            $stmt = $pdo->prepare($query);
            $stmt->execute([$seance_id]);
            
            // Mettre à jour le statut du push
            if ($seance['push_status'] === 'pending') {
                $query = "UPDATE pushes SET status = 'approved' WHERE seance_id = ?";
                $stmt = $pdo->prepare($query);
                $stmt->execute([$seance_id]);
            }
            
            $pdo->commit();
            
            // Générer le PDF
            generatePresencePDF($seance_id, $seance, $confirmed_students, $_SESSION['classroom'], $salle_formation, $_SESSION['niveau_id']);
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['error'] = "Une erreur est survenue lors de l'enregistrement des présences.";
            header("Location: liste.php?seance_id=$seance_id");
            exit();
        }
    }
}

function generatePresencePDF($seance_id, $seance, $confirmed_students, $classroom, $salle_formation, $niveau_id) {
    require_once 'includes/tcpdf/tcpdf.php';
    
    // Création du PDF
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    
    // Configuration
    $pdf->SetCreator('Système de Gestion des Présences');
    $pdf->SetAuthor($_SESSION['name']);
    $pdf->SetTitle("Liste de présence - Séance $seance_id");
    $pdf->SetSubject('Liste de présence');
    
    // Police par défaut
    $pdf->SetFont('helvetica', '', 10);
    
    // Marges
    $pdf->SetMargins(15, 15, 15);
    $pdf->SetHeaderMargin(5);
    $pdf->SetFooterMargin(10);
    
    // Ajout d'une page
    $pdf->AddPage();
    
    // Logo et en-tête
    $header = '
    <table cellpadding="5" border="0" width="100%">
        <tr>
            <td width="20%" style="text-align:center;">
                <img src="images/logo.png" height="50" />
            </td>
            <td width="60%" style="text-align:center;">
                <h1 style="color:#4b2a70;font-size:18px;">LISTE DE PRÉSENCE</h1>
                <p style="font-size:12px;color:#555;">Séance du ' . date('d/m/Y', strtotime($seance['date_seance'] ?? 'now')) . '</p>
            </td>
            <td width="20%" style="text-align:center;font-size:10px;">
                <p>Généré le ' . date('d/m/Y H:i') . '</p>
                <p>Par ' . htmlspecialchars($_SESSION['name']) . '</p>
            </td>
        </tr>
    </table>
    <hr style="color:#7b4b9e;">';
    
    $pdf->writeHTML($header, true, false, true, false, '');
    
    // Informations de la séance
    $info_seance = '
    <table cellpadding="5" width="100%" style="font-size:10px;">
        <tr>
            <td width="25%"><strong>Matière:</strong></td>
            <td width="25%">' . htmlspecialchars($seance['matiere_nom']) . '</td>
            <td width="25%"><strong>Code:</strong></td>
            <td width="25%">' . htmlspecialchars($seance['matiere_code']) . '</td>
        </tr>
        <tr>
            <td><strong>Enseignant:</strong></td>
            <td>' . htmlspecialchars($seance['enseignant_nom']) . '</td>
            <td><strong>Salle:</strong></td>
            <td>' . htmlspecialchars($seance['salle_nom']) . '</td>
        </tr>
        <tr>
            <td><strong>Heure:</strong></td>
            <td>' . htmlspecialchars($seance['heure_debut']) . ' - ' . htmlspecialchars($seance['heure_fin']) . '</td>
            <td><strong>Créneau:</strong></td>
            <td>' . htmlspecialchars($seance['jour']) . '</td>
        </tr>
        <tr>
            <td><strong>Formation:</strong></td>
            <td>' . htmlspecialchars($seance['salle_formation']) . '</td>
            <td></td>
            <td></td>
        </tr>
    </table>
    <br>';
    
    $pdf->writeHTML($info_seance, true, false, true, false, '');
    
    // Récupération des étudiants avec leur statut définitif (après confirmation du délégué)
    // --- DÉBUT DE LA CORRECTION ---
    $query = "SELECT u.id, u.name, u.formation, f.nom as filiere, n.nom as niveau, 
                     MIN(pe.etat) as presence_etat
              FROM users u
              LEFT JOIN filieres f ON u.filiere_id = f.id
              LEFT JOIN niveaux n ON u.niveau_id = n.id
              LEFT JOIN presences_etudiants pe ON pe.seance_id = ? AND pe.etudiant_id = u.id
              WHERE u.grade = 'Etudiant' AND u.classroom = ? AND u.niveau_id = ?";
    
    // Ajouter le filtre de formation selon le type de salle
    if ($salle_formation === 'FI') {
        $query .= " AND (u.formation = 'FI' OR u.formation = 'FM')";
    } else {
        $query .= " AND u.formation = 'FA'";
    }
    
    $query .= " GROUP BY u.id, u.name, u.formation, f.nom, n.nom";
    $query .= " ORDER BY u.name";
    // --- FIN DE LA CORRECTION ---
    
    $stmt = $GLOBALS['pdo']->prepare($query);
    $stmt->execute([$seance_id, $classroom, $niveau_id]);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calcul des statistiques
    $stats = [
        'total' => count($students),
        'present' => 0,
        'absent' => 0,
        'FA_present' => 0,
        'FI_present' => 0,
        'FM_present' => 0
    ];
    
    foreach ($students as $student) {
        if ($student['presence_etat'] === 'present') {
            $stats['present']++;
            if ($student['formation'] === 'FA') {
                $stats['FA_present']++;
            } elseif ($student['formation'] === 'FI') {
                $stats['FI_present']++;
            } else {
                $stats['FM_present']++;
            }
        } else {
            $stats['absent']++;
        }
    }
    
    // Statistiques
    $stats_html = '
    <table cellpadding="5" width="100%" style="font-size:10px;background-color:#f5f5f5;">
        <tr>
            <td width="25%"><strong>Total étudiants:</strong> ' . $stats['total'] . '</td>
            <td width="25%"><strong>Présents:</strong> ' . $stats['present'] . '</td>
            <td width="25%"><strong>Absents:</strong> ' . $stats['absent'] . '</td>
            <td width="25%"><strong>Taux présence:</strong> ' . round(($stats['present'] / $stats['total']) * 100) . '%</td>
        </tr>
    </table>
    <br>';
    
    $pdf->writeHTML($stats_html, true, false, true, false, '');
    
    // Liste des étudiants
    $students_html = '
    <table cellpadding="5" width="100%" border="1" style="font-size:9px;">
        <thead>
            <tr style="background-color:#4b2a70;color:white;">
                <th width="5%">N°</th>
                <th width="35%">Nom</th>
                <th width="15%">Formation</th>
                <th width="25%">Filière</th>
                <th width="10%">Niveau</th>
                <th width="10%">Présence</th>
            </tr>
        </thead>
        <tbody>';
    
    $counter = 1;
    
    foreach ($students as $student) {
        $presence = ($student['presence_etat'] === 'present') ? 'Présent' : 'Absent';
        $presence_color = ($student['presence_etat'] === 'present') ? '#4CAF50' : '#F44336';
        
        // Vérifier si l'étudiant est dans la mauvaise formation pour cette salle
        $is_wrong_formation = ($salle_formation !== $student['formation'] && $student['formation'] !== 'FM');
        
        $students_html .= '
        <tr>
            <td>' . $counter++ . '</td>
            <td>' . htmlspecialchars($student['name']) . '</td>
            <td>' . htmlspecialchars($student['formation']) . '</td>
            <td>' . htmlspecialchars($student['filiere']) . '</td>
            <td>' . htmlspecialchars($student['niveau']) . '</td>
            <td style="color:' . $presence_color . ';font-weight:bold;text-align:center;">' . $presence . '</td>
        </tr>';
    }
    
    $students_html .= '
        </tbody>
    </table>';
    
    $pdf->writeHTML($students_html, true, false, true, false, '');
    
    // Notes et signature
    $notes = '
    <br><br>
    <table cellpadding="5" width="100%" style="font-size:9px;">
        <tr>
            <td width="50%">
                <p><strong>Notes:</strong></p>
                <p>- Les étudiants en formation différente de la salle sont marqués en jaune</p>
                <p>- Document généré automatiquement</p>
            </td>
            <td width="50%" style="text-align:center;">
                <p>Signature du délégué</p>
                <br><br><br>
                <p>........................................</p>
            </td>
        </tr>
    </table>';
    
    $pdf->writeHTML($notes, true, false, true, false, '');
    
    // Génération du fichier
    $filename = 'presence_seance_' . $seance_id . '_' . date('Ymd_His') . '.pdf';
    $pdf->Output($filename, 'D'); // Téléchargement direct
    exit();
}

// Récupération de la liste des étudiants pour la salle (sans doublons)
$salle_formation = $seance['salle_formation'] ?? 'FI';
$delegue_niveau_id = $_SESSION['niveau_id'];

$query = "SELECT DISTINCT u.id, u.name, u.formation, f.nom as filiere, n.nom as niveau, 
                 pe.etat as presence_etat
          FROM users u
          LEFT JOIN filieres f ON u.filiere_id = f.id
          LEFT JOIN niveaux n ON u.niveau_id = n.id
          LEFT JOIN presences_etudiants pe ON pe.seance_id = ? AND pe.etudiant_id = u.id
          WHERE u.grade = 'Etudiant' AND u.classroom = ? 
          AND u.niveau_id = ?";
          
// Ajouter le filtre de formation selon le type de salle
if ($salle_formation === 'FI') {
    $query .= " AND (u.formation = 'FI' OR u.formation = 'FM')";
} else {
    $query .= " AND u.formation = 'FA'";
}

$query .= " ORDER BY u.name";

$stmt = $pdo->prepare($query);
$stmt->execute([$seance_id, $_SESSION['classroom'], $delegue_niveau_id]);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calcul du nombre de confirmations
$confirmed_count = 0;
foreach ($students as $student) {
    if ($student['presence_etat'] === 'present') {
        $confirmed_count++;
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmation des Présences</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --violet-dark: #2a0a42;
            --violet-medium: #4b2a70;
            --violet-light: #7b4b9e;
            --violet-neon: #b388ff;
            --white: #ffffff;
            --gray-light: #f0f0f0;
            --black: #0a0a0a;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, var(--violet-dark), var(--black));
            color: var(--white);
            min-height: 100vh;
            padding: 2rem;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: rgba(10, 10, 10, 0.7);
            border-radius: 8px;
            padding: 2rem;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
            border: 1px solid var(--violet-medium);
        }
        
        h1 {
            color: var(--violet-neon);
            margin-bottom: 1rem;
            text-align: center;
        }
        
        .seance-info {
            background: rgba(74, 20, 140, 0.3);
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 2rem;
            border-left: 3px solid var(--violet-neon);
        }
        
        .seance-info p {
            margin-bottom: 0.5rem;
        }
        
        .seance-info strong {
            color: var(--violet-neon);
        }
        
        .counter {
            background: var(--violet-medium);
            padding: 0.5rem 1rem;
            border-radius: 4px;
            margin-bottom: 1rem;
            text-align: center;
            font-weight: bold;
        }
        
        .counter span {
            color: var(--violet-neon);
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 2rem;
        }
        
        th, td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid var(--violet-medium);
        }
        
        th {
            background-color: var(--violet-dark);
            color: var(--violet-neon);
            font-weight: 600;
        }
        
        tr:hover {
            background-color: rgba(123, 75, 158, 0.1);
        }
        
        .checkbox-cell {
            text-align: center;
            width: 50px;
        }
        
        input[type="checkbox"] {
            transform: scale(1.5);
        }
        
        .form-actions {
            display: flex;
            justify-content: center;
            gap: 1rem;
            margin-top: 2rem;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn-primary {
            background-color: var(--violet-neon);
            color: white;
        }
        
        .btn-secondary {
            background-color: #666;
            color: #ccc;
        }
        
        .btn-primary:hover {
            background-color: var(--violet-light);
            transform: translateY(-2px);
        }
        
        .presence-badge {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .present {
            background-color: #4caf50;
            color: white;
        }
        
        .absent {
            background-color: #f44336;
            color: white;
        }
        
        .message {
            padding: 1rem;
            margin-bottom: 1rem;
            border-radius: 4px;
            text-align: center;
        }
        
        .success {
            background-color: rgba(76, 175, 80, 0.2);
            border: 1px solid #4caf50;
            color: #a5d6a7;
        }
        
        .error {
            background-color: rgba(244, 67, 54, 0.2);
            border: 1px solid #f44336;
            color: #ef9a9a;
        }
        
        .warning {
            background-color: rgba(255, 193, 7, 0.2);
            border: 1px solid #ffc107;
            color: #ffecb3;
        }
        
        .disabled-checkbox {
            opacity: 0.6;
            pointer-events: none;
        }
        
        .locked-info {
            background-color: rgba(244, 67, 54, 0.2);
            border-left: 3px solid #f44336;
            padding: 1rem;
            margin-bottom: 1rem;
            border-radius: 4px;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }
            
            table {
                display: block;
                overflow-x: auto;
            }
            
            th, td {
                padding: 0.75rem;
                font-size: 0.9rem;
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1><i class="fas fa-users"></i> Confirmation des Présences</h1>
        
        <?php if (isset($_SESSION['message'])): ?>
            <div class="message success">
                <?php echo $_SESSION['message']; unset($_SESSION['message']); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="message error">
                <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($is_locked): ?>
            <div class="locked-info">
                <i class="fas fa-lock"></i>
                <div>
                    <strong>Les présences pour cette séance ont été verrouillées.</strong>
                    <p>Aucune modification n'est plus possible.</p>
                </div>
            </div>
        <?php endif; ?>
        
        <div class="seance-info">
            <p><strong>Matière:</strong> <?php echo htmlspecialchars($seance['matiere_nom']) . ' (' . htmlspecialchars($seance['matiere_code']) . ')'; ?></p>
            <p><strong>Enseignant:</strong> <?php echo htmlspecialchars($seance['enseignant_nom']); ?></p>
            <p><strong>Salle:</strong> <?php echo htmlspecialchars($seance['salle_nom']); ?></p>
            <p><strong>Formation:</strong> <?php echo htmlspecialchars($seance['salle_formation']); ?></p>
            <p><strong>Heure:</strong> <?php echo htmlspecialchars($seance['heure_debut']) . ' - ' . htmlspecialchars($seance['heure_fin']); ?></p>
            <p><strong>Étudiants présents déclarés:</strong> <?php echo htmlspecialchars($seance['etudiants_presents']); ?></p>
            <?php if ($is_locked): ?>
                <p><strong>Statut:</strong> <span style="color: #f44336;">Verrouillé</span></p>
            <?php endif; ?>
        </div>
        
        <div class="counter">
            Confirmations: <span id="confirmedCount"><?php echo $confirmed_count; ?></span> / <?php echo htmlspecialchars($seance['etudiants_presents']); ?>
            <?php if ($confirmed_count > $seance['etudiants_presents']): ?>
                <span style="color: #f44336; margin-left: 1rem;">
                    <i class="fas fa-exclamation-triangle"></i> Nombre supérieur au déclaré
                </span>
            <?php endif; ?>
        </div>
        
        <form method="POST" id="presenceForm">
            <table>
                <thead>
                    <tr>
                        <th>Nom</th>
                        <th>Formation</th>
                        <th>Filière</th>
                        <th>Niveau</th>
                        <th class="checkbox-cell">Présent</th>
                        <th>État</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($students as $student): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($student['name']); ?></td>
                        <td><?php echo htmlspecialchars($student['formation']); ?></td>
                        <td><?php echo htmlspecialchars($student['filiere']); ?></td>
                        <td><?php echo htmlspecialchars($student['niveau']); ?></td>
                        <td class="checkbox-cell">
                            <input type="checkbox" name="students[]" value="<?php echo $student['id']; ?>" 
                                <?php echo $student['presence_etat'] === 'present' ? 'checked' : ''; ?>
                                <?php echo $is_locked ? 'disabled class="disabled-checkbox"' : 'onchange="updateCounter()"'; ?>>
                        </td>
                        <td>
                            <?php if ($student['presence_etat'] === 'present'): ?>
                                <span class="presence-badge present"><i class="fas fa-check"></i> Présent</span>
                            <?php else: ?>
                                <span class="presence-badge absent"><i class="fas fa-times"></i> Absent</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <div class="form-actions">
                <a href="dashboard.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Retour
                </a>
                <?php if (!$is_locked): ?>
                <button type="submit" name="confirm_presences" class="btn btn-primary" id="confirmBtn">
                    <i class="fas fa-file-pdf"></i> Confirmer et Générer PDF
                </button>
                <?php else: ?>
                <a href="javascript:window.print()" class="btn btn-secondary">
                    <i class="fas fa-print"></i> Imprimer
                </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
    
    <script>
        function updateCounter() {
            const checkboxes = document.querySelectorAll('input[name="students[]"]:checked');
            const confirmedCount = document.getElementById('confirmedCount');
            const confirmBtn = document.getElementById('confirmBtn');
            const expectedCount = <?php echo $seance['etudiants_presents']; ?>;
            
            confirmedCount.textContent = checkboxes.length;
            
            // Avertissement si nombre de confirmations > nombre déclaré
            if (checkboxes.length > expectedCount) {
                confirmedCount.style.color = '#f44336';
                confirmBtn.disabled = true;
                confirmBtn.title = 'Le nombre de confirmations dépasse le nombre déclaré';
            } else {
                confirmedCount.style.color = '';
                confirmBtn.disabled = false;
                confirmBtn.title = '';
            }
        }
        
        // Initialiser le compteur
        document.addEventListener('DOMContentLoaded', updateCounter);
    </script>
</body>
</html>