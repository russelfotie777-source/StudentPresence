<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

date_default_timezone_set('Africa/Douala');
$enseignant_id = $_SESSION['user_id'];

// ======================================================================
// GESTION DE L'EXPORT PDF
// ======================================================================
if (isset($_GET['export'])) {
    require_once('includes/tcpdf/tcpdf.php');
    
    $query = "SELECT 
                s.id as seance_id,
                s.heure_debut as heure_prevue,
                s.debut_reel as debut_reel,
                s.fin_reelle as fin_reelle,
                s.etat_final,
                TIMESTAMPDIFF(MINUTE, s.heure_debut, s.debut_reel) as retard_minutes,
                TIMESTAMPDIFF(MINUTE, s.debut_reel, s.fin_reelle) as duree_effectuee_minutes,
                n.id as niveau_id,
                n.nom as niveau_nom,
                t.tarif_heure,
                sa.nom as salle_nom,
                m.nom as matiere_nom,
                s.date_seance
              FROM seances s
              JOIN cours c ON s.cours_id = c.id
              JOIN salles sa ON s.salle_id = sa.id
              JOIN filieres f ON sa.filiere_id = f.id
              JOIN niveaux n ON f.niveau_id = n.id
              JOIN tarifs_heures t ON n.id = t.niveau_id
              JOIN matieres m ON c.matiere_id = m.id
              WHERE s.enseignant_id = :enseignant_id
              AND s.etat_final = 'present'
              ORDER BY s.date_seance, s.heure_debut";

    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':enseignant_id', $enseignant_id, PDO::PARAM_INT);
    $stmt->execute();
    $seances = $stmt->fetchAll();

    $total_minutes = 0;
    $total_salaire = 0;
    $total_penalites = 0;
    $details_seances = [];
    
    foreach ($seances as $seance) {
        $retard = $seance['retard_minutes'] ?? 0;
        $duree_minutes = $seance['duree_effectuee_minutes'];
        $tarif_horaire = $seance['tarif_heure'];
        $tarif_minute = $tarif_horaire / 60;
        $penalite = 0;
        $salaire_seance = 0;

        $heures_completes = floor($duree_minutes / 60);
        $minutes_restantes = $duree_minutes % 60;

        if ($retard < 30) {
            $salaire_seance = $heures_completes * $tarif_horaire;
            if ($minutes_restantes > 0) {
                if ($minutes_restantes >= 40) {
                    $salaire_seance += $tarif_horaire; 
                } elseif ($minutes_restantes >= 30) {
                    $salaire_seance += $tarif_horaire / 2;
                } else {
                    $salaire_seance += $minutes_restantes * $tarif_minute;
                }
            }
        } elseif ($retard >= 30) {
             if ($retard < 50) {
                $salaire_seance = $heures_completes * $tarif_horaire;
                if ($minutes_restantes > 0) {
                    $salaire_seance += ($minutes_restantes * $tarif_minute) / 2;
                    $penalite = ($minutes_restantes * $tarif_minute) / 2;
                }
            } else { 
                $heures_payees = max(0, $heures_completes - 1);
                $salaire_seance = $heures_payees * $tarif_horaire;
                $penalite += $tarif_horaire;

                if ($minutes_restantes > 0) {
                    $salaire_seance += ($minutes_restantes * $tarif_minute) / 2;
                    $penalite += ($minutes_restantes * $tarif_minute) / 2;
                }

                if ($duree_minutes < 60) {
                    $salaire_seance = 0;
                    $penalite = $duree_minutes * $tarif_minute;
                }
            }
        }
        
        $details_seances[] = [
            'date' => $seance['date_seance'],
            'heure' => date('H:i', strtotime($seance['debut_reel'])),
            'matiere' => $seance['matiere_nom'],
            'salle' => $seance['salle_nom'],
            'niveau' => $seance['niveau_nom'],
            'duree' => sprintf("%dh%02d", floor($duree_minutes/60), $duree_minutes%60),
            'retard' => $retard,
            'tarif_base' => $tarif_horaire,
            'penalite' => $penalite,
            'salaire' => $salaire_seance,
            'commentaire' => $retard >= 30 ? "Retard de $retard minutes" : "",
            'seance_id' => $seance['seance_id']
        ];
        
        $total_minutes += $duree_minutes;
        $total_salaire += $salaire_seance;
        $total_penalites += $penalite;
    }

    $pdf = new TCPDF('L', PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor('Établissement Scolaire');
    $pdf->SetTitle('Rapport de salaire détaillé');
    $pdf->SetMargins(10, 20, 10);
    $pdf->AddPage();

    $html = '<style>
                h1 { color: #6e48aa; text-align: center; }
                h2 { color: #6e48aa; font-size: 16px; }
                table { width: 100%; border-collapse: collapse; font-size: 10px; }
                th { background-color: #6e48aa; color: white; padding: 3px; }
                td { padding: 3px; border: 1px solid #ddd; }
                .total-row { font-weight: bold; background-color: #f2f2f2; }
                .penalite { color: #cc0000; }
            </style>
            <h1>Détail de mon Salaire</h1>
            <h2>Période: ' . date('d/m/Y') . '</h2>';
    
    $html .= '<table>
                <tr>
                    <th>Date</th>
                    <th>Heure</th>
                    <th>Matière</th>
                    <th>Salle</th>
                    <th>Niveau</th>
                    <th>Durée</th>
                    <th>Retard (min)</th>
                </tr>';
    
    foreach ($details_seances as $seance) {
        $html .= '<tr>
                    <td>' . htmlspecialchars($seance['date']) . '</td>
                    <td>' . htmlspecialchars($seance['heure']) . '</td>
                    <td>' . htmlspecialchars($seance['matiere']) . '</td>
                    <td>' . htmlspecialchars($seance['salle']) . '</td>
                    <td>' . htmlspecialchars($seance['niveau']) . '</td>
                    <td>' . $seance['duree'] . '</td>
                    <td>' . $seance['retard'] . '</td>
                </tr>';
    }
    
    $total_heures = floor($total_minutes / 60);
    $total_minutes_rest = $total_minutes % 60;
    
    $html .= '<tr class="total-row">
                <td colspan="5"><strong>TOTAUX</strong></td>
                <td><strong>' . sprintf("%dh%02d", $total_heures, $total_minutes_rest) . '</strong></td>
                <td></td>
            </tr>';
    
    $html .= '</table>';

    ob_end_clean();
    $pdf->writeHTML($html, true, false, true, false, '');
    $pdf->Output('rapport_salaire_detail_' . date('Y-m-d') . '.pdf', 'D');
    exit();
}

// ======================================================================
// AFFICHAGE NORMAL (HTML)
// ======================================================================

$query = "SELECT 
            s.id as seance_id,
            s.heure_debut as heure_prevue,
            s.debut_reel as debut_reel,
            s.fin_reelle as fin_reelle,
            s.etat_final,
            TIMESTAMPDIFF(MINUTE, s.heure_debut, s.debut_reel) as retard_minutes,
            TIMESTAMPDIFF(MINUTE, s.debut_reel, s.fin_reelle) as duree_effectuee_minutes,
            n.id as niveau_id,
            n.nom as niveau_nom,
            t.tarif_heure,
            sa.nom as salle_nom,
            m.nom as matiere_nom,
            s.date_seance
          FROM seances s
          JOIN cours c ON s.cours_id = c.id
          JOIN salles sa ON s.salle_id = sa.id
          JOIN filieres f ON sa.filiere_id = f.id
          JOIN niveaux n ON f.niveau_id = n.id
          JOIN tarifs_heures t ON n.id = t.niveau_id
          JOIN matieres m ON c.matiere_id = m.id
          WHERE s.enseignant_id = :enseignant_id
          AND s.etat_final = 'present'
          ORDER BY s.date_seance, s.heure_debut";

$stmt = $pdo->prepare($query);
$stmt->bindParam(':enseignant_id', $enseignant_id, PDO::PARAM_INT);
$stmt->execute();
$seances = $stmt->fetchAll();

$total_minutes = 0;
$total_salaire = 0;
$total_penalites = 0;
$details_seances = [];

foreach ($seances as $seance) {
    $retard = $seance['retard_minutes'] ?? 0;
    $duree_minutes = $seance['duree_effectuee_minutes'];
    $tarif_horaire = $seance['tarif_heure'];
    $tarif_minute = $tarif_horaire / 60;
    $penalite = 0;
    $salaire_seance = 0;
    
    $heures_completes = floor($duree_minutes / 60);
    $minutes_restantes = $duree_minutes % 60;

    if ($retard < 30) {
        $salaire_seance = $heures_completes * $tarif_horaire;
        if ($minutes_restantes > 0) {
            if ($minutes_restantes >= 40) {
                $salaire_seance += $tarif_horaire; 
            } elseif ($minutes_restantes >= 30) {
                $salaire_seance += $tarif_horaire / 2;
            } else {
                $salaire_seance += $minutes_restantes * $tarif_minute;
            }
        }
    } elseif ($retard >= 30) {
         if ($retard < 50) {
            $salaire_seance = $heures_completes * $tarif_horaire;
            if ($minutes_restantes > 0) {
                $salaire_seance += ($minutes_restantes * $tarif_minute) / 2;
                $penalite = ($minutes_restantes * $tarif_minute) / 2;
            }
        } else { 
            $heures_payees = max(0, $heures_completes - 1);
            $salaire_seance = $heures_payees * $tarif_horaire;
            $penalite += $tarif_horaire;
            
            if ($minutes_restantes > 0) {
                $salaire_seance += ($minutes_restantes * $tarif_minute) / 2;
                $penalite += ($minutes_restantes * $tarif_minute) / 2;
            }
            
            if ($duree_minutes < 60) {
                $salaire_seance = 0;
                $penalite = $duree_minutes * $tarif_minute;
            }
        }
    }
    
    $details_seances[] = [
        'date' => $seance['date_seance'],
        'heure' => date('H:i', strtotime($seance['debut_reel'])),
        'matiere' => $seance['matiere_nom'],
        'salle' => $seance['salle_nom'],
        'niveau' => $seance['niveau_nom'],
        'duree' => sprintf("%dh%02d", floor($duree_minutes/60), $duree_minutes%60),
        'retard' => $retard,
        'tarif_base' => $tarif_horaire,
        'penalite' => $penalite,
        'salaire' => $salaire_seance,
        'commentaire' => $retard >= 30 ? "Retard de $retard minutes" : "",
        'seance_id' => $seance['seance_id']
    ];
    
    $total_minutes += $duree_minutes;
    $total_salaire += $salaire_seance;
    $total_penalites += $penalite;
}

$total_heures = floor($total_minutes / 60);
$total_minutes_rest = $total_minutes % 60;
$total_duree = sprintf("%dh%02d", $total_heures, $total_minutes_rest);

$query_enseignant = "SELECT name, phone FROM users WHERE id = :enseignant_id";
$stmt = $pdo->prepare($query_enseignant);
$stmt->bindParam(':enseignant_id', $enseignant_id, PDO::PARAM_INT);
$stmt->execute();
$enseignant = $stmt->fetch();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Suivi de mon Salaire</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --cosmic-primary: #6e48aa;
            --cosmic-secondary: #9d50bb;
            --cosmic-dark: #2a0a42;
            --cosmic-light: #b388ff;
            --cosmic-gradient: linear-gradient(135deg, var(--cosmic-primary), var(--cosmic-secondary));
        }
        
        body {
            background: linear-gradient(135deg, #1a1a2e, #16213e);
            color: #fff;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .container {
            max-width: 1400px;
            margin: 2rem auto;
            padding: 0 1rem;
        }
        
        .card {
            background: rgba(26, 26, 46, 0.8);
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
            border: 1px solid var(--cosmic-primary);
            backdrop-filter: blur(10px);
        }
        
        h1 {
            color: var(--cosmic-light);
            text-align: center;
            margin-bottom: 1.5rem;
            font-size: 2.5rem;
            background: linear-gradient(to right, var(--cosmic-light), #fff);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .btn {
            display: inline-block;
            padding: 0.75rem 1.5rem;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }
        
        .btn-primary {
            background: var(--cosmic-gradient);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(110, 72, 170, 0.4);
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #ff416c, #ff4b2b);
            color: white;
        }
        
        .btn-danger:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(255, 65, 108, 0.4);
        }
        
        .btn-request {
            background: linear-gradient(135deg, #4e54c8, #8f94fb);
            color: white;
            padding: 0.4rem 0.8rem;
            font-size: 0.85rem;
        }
        
        .btn-request:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(78, 84, 200, 0.4);
        }
        
        .table-responsive {
            overflow-x: auto;
            margin-top: 1.5rem;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            background: rgba(26, 26, 46, 0.6);
            border-radius: 10px;
            overflow: hidden;
        }
        
        th, td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid rgba(110, 72, 170, 0.3);
        }
        
        th {
            background: var(--cosmic-gradient);
            color: white;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 1px;
        }
        
        tr:hover {
            background: rgba(110, 72, 170, 0.1);
        }
        
        .total-row {
            font-weight: bold;
            background: rgba(157, 80, 187, 0.2);
        }
        
        .text-center {
            text-align: center;
        }
        
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            background: rgba(110, 72, 170, 0.2);
            border-left: 4px solid var(--cosmic-light);
        }
        
        .float-end {
            float: right;
        }
        
        .penalite {
            color: #ff6b6b;
            font-weight: bold;
        }
        
        .badge-retard {
            display: inline-block;
            padding: 0.25em 0.4em;
            font-size: 75%;
            font-weight: 700;
            line-height: 1;
            text-align: center;
            white-space: nowrap;
            vertical-align: baseline;
            border-radius: 0.25rem;
        }
        
        .badge-retard-0 {
            background-color: #28a745;
        }
        
        .badge-retard-1 {
            background-color: #ffc107;
            color: #212529;
        }
        
        .badge-retard-2 {
            background-color: #fd7e14;
        }
        
        .badge-retard-3 {
            background-color: #dc3545;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 0 0.5rem;
            }
            
            .card {
                padding: 1rem;
            }
            
            h1 {
                font-size: 2rem;
            }
            
            th, td {
                padding: 0.5rem;
                font-size: 0.8rem;
            }
        }
    </style>
</head>
<body>
    
    <div class="container py-5">
        <h1 class="text-center mb-4">
            <i class="fas fa-file-invoice-dollar me-2"></i> Suivi de mes Heures
        </h1>

        <div class="card shadow-lg">
            <div class="card-body">
                <div class="d-flex justify-content-between mb-4">
                    <div>
                        <h3 style="color: var(--cosmic-light);"><?= htmlspecialchars($enseignant['name']) ?></h3>
                        <p style="color: var(--cosmic-light);" class="mb-0">Téléphone: <?= htmlspecialchars($enseignant['phone']) ?></p>
                    </div>
                    <div class="text-end">
                        <a href="?export=pdf" class="btn btn-danger">
                            <i class="fas fa-file-pdf me-2"></i> Exporter en PDF
                        </a>
                    </div>
                </div>

                <div class="alert" style="color:#fff;">
                    <h4 class="alert-heading">Résumé</h4>
                    <p>
                        <strong>Total heures:</strong> <?= $total_duree ?> | 
                        <strong>Salaire net:</strong> <?= number_format($total_salaire, 0, ',', ' ') ?> FCFA
                    </p>
                </div>

                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Heure</th>
                                <th>Matière</th>
                                <th>Salle</th>
                                <th>Niveau</th>
                                <th>Durée</th>
                                <th>Retard</th>
                                <th>Requête</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($details_seances)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-4">
                                    Aucune séance effectuée
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($details_seances as $seance): 
                                $badge_class = 'badge-retard-0';
                                if ($seance['retard'] > 10) $badge_class = 'badge-retard-1';
                                if ($seance['retard'] >= 30) $badge_class = 'badge-retard-2';
                                if ($seance['retard'] >= 50) $badge_class = 'badge-retard-3';
                            ?>
                            <tr>
                                <td><?= htmlspecialchars($seance['date']) ?></td>
                                <td><?= htmlspecialchars($seance['heure']) ?></td>
                                <td><?= htmlspecialchars($seance['matiere']) ?></td>
                                <td><?= htmlspecialchars($seance['salle']) ?></td>
                                <td><?= htmlspecialchars($seance['niveau']) ?></td>
                                <td><?= $seance['duree'] ?></td>
                                <td>
                                    <span class="badge-retard <?= $badge_class ?>">
                                        <?= $seance['retard'] ?> min
                                    </span>
                                </td>
                                <td>
                                    <a href="requete.php?seance_id=<?= $seance['seance_id'] ?>&date=<?= urlencode($seance['date']) ?>&heure=<?= urlencode($seance['heure']) ?>&matiere=<?= urlencode($seance['matiere']) ?>&salle=<?= urlencode($seance['salle']) ?>&niveau=<?= urlencode($seance['niveau']) ?>&penalite=<?= $seance['penalite'] ?>" 
                                       class="btn btn-request">
                                        <i class="fas fa-paper-plane me-1"></i> Requête
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                        <tfoot>
                            <tr class="total-row">
                                <th colspan="5">TOTAUX</th>
                                <th><?= $total_duree ?></th>
                                <th></th>
                                <th></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>