<?php
ob_start();
require 'includes/db.php';

// ======================================================================
// GESTION DE L'EXPORT PDF
// ======================================================================
if (isset($_GET['export'])) {
    require_once('includes/tcpdf/tcpdf.php');
    
    $niveau_id = $_GET['niveau_filter'] ?? null;
    
    // Requête pour calculer les salaires avec les heures réelles
    $query = "SELECT 
                u.id, u.name, u.phone,
                SUM(CASE WHEN s.etat_final = 'present' THEN TIMESTAMPDIFF(MINUTE, s.debut_reel, s.fin_reelle)/60 ELSE 0 END) as heures_effectuees,
                SUM(CASE WHEN s.etat_final = 'present' THEN TIMESTAMPDIFF(MINUTE, s.heure_debut, s.heure_fin)/60 ELSE 0 END) as heures_prevues,
                SUM(CASE WHEN s.etat_final = 'present' THEN TIMESTAMPDIFF(MINUTE, s.heure_debut, s.debut_reel) ELSE 0 END) as retard_minutes,
                GROUP_CONCAT(DISTINCT n.id ORDER BY n.id) as niveau_ids,
                GROUP_CONCAT(DISTINCT n.nom ORDER BY n.nom SEPARATOR ', ') as niveaux,
                GROUP_CONCAT(DISTINCT t.tarif_heure ORDER BY n.id) as tarifs
              FROM users u
              LEFT JOIN seances s ON u.id = s.enseignant_id
              LEFT JOIN cours c ON s.cours_id = c.id
              LEFT JOIN salles sa ON s.salle_id = sa.id
              LEFT JOIN filieres f ON sa.filiere_id = f.id
              LEFT JOIN niveaux n ON f.niveau_id = n.id
              LEFT JOIN tarifs_heures t ON n.id = t.niveau_id
              WHERE u.grade = 'Enseignant'";
    
    if (!empty($niveau_id)) {
        $query .= " AND n.id = :niveau_id";
    }
    
    $query .= " GROUP BY u.id ORDER BY u.name";
    
    $stmt = $pdo->prepare($query);
    
    if (!empty($niveau_id)) {
        $stmt->bindParam(':niveau_id', $niveau_id, PDO::PARAM_INT);
    }
    
    $stmt->execute();
    $enseignants = $stmt->fetchAll();

    // Calcul des salaires basé sur les heures réellement effectuées
foreach ($enseignants as &$enseignant) {
    $niveau_ids = explode(',', $enseignant['niveau_ids']);
    $tarifs = explode(',', $enseignant['tarifs']);
    
    $salaire_total = 0;
    
    if (!empty($enseignant['heures_effectuees']) && !empty($tarifs[0])) {
        // Requête pour obtenir les détails des séances
        $query_seances = "SELECT 
                            TIMESTAMPDIFF(MINUTE, s.heure_debut, s.debut_reel) as retard_minutes,
                            TIMESTAMPDIFF(MINUTE, s.debut_reel, s.fin_reelle) as duree_effectuee_minutes,
                            n.id as niveau_id
                          FROM seances s
                          JOIN cours c ON s.cours_id = c.id
                          JOIN salles sa ON s.salle_id = sa.id
                          JOIN filieres f ON sa.filiere_id = f.id
                          JOIN niveaux n ON f.niveau_id = n.id
                          WHERE s.enseignant_id = :enseignant_id
                          AND s.etat_final = 'present'";
        
        if (!empty($niveau_id)) {
            $query_seances .= " AND n.id = :niveau_id";
        }
        
        $stmt_seances = $pdo->prepare($query_seances);
        $stmt_seances->bindParam(':enseignant_id', $enseignant['id'], PDO::PARAM_INT);
        
        if (!empty($niveau_id)) {
            $stmt_seances->bindParam(':niveau_id', $niveau_id, PDO::PARAM_INT);
        }
        
        $stmt_seances->execute();
        $seances = $stmt_seances->fetchAll();
        
        foreach ($seances as $seance) {
            $retard = $seance['retard_minutes'] ?? 0;
            $duree_minutes = $seance['duree_effectuee_minutes'];
            $tarif = $tarifs[array_search($seance['niveau_id'], $niveau_ids)];
            
            // Nouvelle logique de calcul
            if ($retard < 15) {
                // Retard < 15 min : on arrondit à 1 heure si durée ≥ 45 min
                if ($duree_minutes >= 45) {
                    $salaire_seance = $tarif; // Paiement d'une heure complète
                } else {
                    // Moins de 45 min : paiement au réel
                    $salaire_seance = ($duree_minutes / 60) * $tarif;
                }
            } elseif ($retard >= 15 && $retard < 30) {
                // Retard 15-29 min : paiement normal sans arrondi
                $heures_completes = floor($duree_minutes / 60);
                $minutes_restantes = $duree_minutes % 60;
                
                $salaire_seance = $heures_completes * $tarif;
                $salaire_seance += ($minutes_restantes / 60) * $tarif;
            } elseif ($retard >= 30 && $retard < 40) {
                // Retard 30-39 min : paiement à moitié
                if ($duree_minutes >= 45) {
                    // 45 min ou plus = 1 heure payée à moitié
                    $salaire_seance = ($tarif / 2);
                } else {
                    // Moins de 45 min : paiement à moitié du temps réel
                    $salaire_seance = (($duree_minutes / 60) * $tarif) / 2;
                }
            } else {
                // Retard ≥40 min : séance non payée
                $salaire_seance = 0;
            }
            
            $salaire_total += $salaire_seance;
        }
    }
    
    $enseignant['salaire'] = round($salaire_total, 2);
}
unset($enseignant);

    // Calcul des totaux
    $total_heures = 0;
    $total_salaires = 0;

    foreach ($enseignants as $enseignant) {
        $total_heures += $enseignant['heures_effectuees'] ?? 0;
        $total_salaires += $enseignant['salaire'] ?? 0;
    }

    // Création du PDF
    $pdf = new TCPDF('L', PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor('Établissement Scolaire');
    $pdf->SetTitle('Rapport des salaires enseignants');
    $pdf->SetMargins(10, 20, 10);
    $pdf->AddPage();

    $html = '<style>
                h1 { color: #6e48aa; text-align: center; }
                table { width: 100%; border-collapse: collapse; }
                th { background-color: #6e48aa; color: white; padding: 5px; }
                td { padding: 4px; border: 1px solid #ddd; }
                .total-row { font-weight: bold; background-color: #f2f2f2; }
            </style>
            <h1>Suivi des Salaires des Enseignants</h1>';
    
    if (!empty($niveau_id)) {
        $stmt_niveau = $pdo->prepare("SELECT nom FROM niveaux WHERE id = ?");
        $stmt_niveau->execute([$niveau_id]);
        $niveau_nom = $stmt_niveau->fetchColumn();
        $html .= '<p style="text-align:center;font-weight:bold;">Filtre appliqué : Niveau '.htmlspecialchars($niveau_nom).'</p>';
    }
    
    $html .= '<table>
                <tr>
                    <th>Nom</th>
                    <th>Téléphone</th>
                    <th>Heures effectuées</th>
                    <th>Heures prévues</th>
                    <th>Retard moyen (min)</th>
                    <th>Niveaux</th>
                    <th>Salaire total (FCFA)</th>
                </tr>';

    foreach ($enseignants as $enseignant) {
        $html .= '<tr>
                    <td>'.htmlspecialchars($enseignant['name']).'</td>
                    <td>'.htmlspecialchars($enseignant['phone']).'</td>
                    <td>'.number_format($enseignant['heures_effectuees'] ?? 0, 2, ',', ' ').'h</td>
                    <td>'.number_format($enseignant['heures_prevues'] ?? 0, 2, ',', ' ').'h</td>
                    <td>'.round($enseignant['retard_minutes'] / max(1, $enseignant['heures_effectuees']), 1).'</td>
                    <td>'.htmlspecialchars($enseignant['niveaux'] ?? 'N/A').'</td>
                    <td>'.number_format($enseignant['salaire'] ?? 0, 2, ',', ' ').'</td>
                </tr>';
    }

    $html .= '<tr class="total-row">
                    <td colspan="2">Totaux</td>
                    <td>'.number_format($total_heures, 2, ',', ' ').'h</td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td>'.number_format($total_salaires, 2, ',', ' ').' FCFA</td>
                </tr>';

    $html .= '</table>';

    ob_end_clean();
    $pdf->writeHTML($html, true, false, true, false, '');
    $pdf->Output('rapport_salaires_'.date('Y-m-d').'.pdf', 'D');
    exit();
}

include 'includes/admin-header.php';

// Récupération des données
$niveau_id = $_GET['niveau_filter'] ?? null;

// Récupérer les niveaux pour le filtre
$niveaux = $pdo->query("SELECT * FROM niveaux ORDER BY nom")->fetchAll();

// Requête pour calculer les salaires avec les heures réelles
$query = "SELECT 
            u.id, u.name, u.phone,
            SUM(CASE WHEN s.etat_final = 'present' THEN TIMESTAMPDIFF(MINUTE, s.debut_reel, s.fin_reelle)/60 ELSE 0 END) as heures_effectuees,
            SUM(CASE WHEN s.etat_final = 'present' THEN TIMESTAMPDIFF(MINUTE, s.heure_debut, s.heure_fin)/60 ELSE 0 END) as heures_prevues,
            SUM(CASE WHEN s.etat_final = 'present' THEN TIMESTAMPDIFF(MINUTE, s.heure_debut, s.debut_reel) ELSE 0 END) as retard_minutes,
            GROUP_CONCAT(DISTINCT n.id ORDER BY n.id) as niveau_ids,
            GROUP_CONCAT(DISTINCT n.nom ORDER BY n.nom SEPARATOR ', ') as niveaux,
            GROUP_CONCAT(DISTINCT t.tarif_heure ORDER BY n.id) as tarifs
          FROM users u
          LEFT JOIN seances s ON u.id = s.enseignant_id
          LEFT JOIN cours c ON s.cours_id = c.id
          LEFT JOIN salles sa ON s.salle_id = sa.id
          LEFT JOIN filieres f ON sa.filiere_id = f.id
          LEFT JOIN niveaux n ON f.niveau_id = n.id
          LEFT JOIN tarifs_heures t ON n.id = t.niveau_id
          WHERE u.grade = 'Enseignant'";

if (!empty($niveau_id)) {
    $query .= " AND n.id = :niveau_id";
}

$query .= " GROUP BY u.id ORDER BY u.name";

$stmt = $pdo->prepare($query);

if (!empty($niveau_id)) {
    $stmt->bindParam(':niveau_id', $niveau_id, PDO::PARAM_INT);
}

$stmt->execute();
$enseignants = $stmt->fetchAll();

// Calcul des salaires basé sur les heures réellement effectuées
foreach ($enseignants as &$enseignant) {
    $niveau_ids = explode(',', $enseignant['niveau_ids']);
    $tarifs = explode(',', $enseignant['tarifs']);
    
    $salaire_total = 0;
    
    if (!empty($enseignant['heures_effectuees']) && !empty($tarifs[0])) {
        // Répartition des heures par niveau
        $heures_par_niveau = array_fill_keys($niveau_ids, $enseignant['heures_effectuees'] / count($niveau_ids));
        
        foreach ($heures_par_niveau as $n_id => $heures) {
            $tarif = $tarifs[array_search($n_id, $niveau_ids)];
            
            // Calcul des heures complètes et minutes restantes
            $heures_completes = floor($heures);
            $minutes_restantes = ($heures - $heures_completes) * 60;
            
            // Paiement des heures complètes
            $salaire_total += $heures_completes * $tarif;
            
            // Paiement des minutes restantes (à moitié)
            if ($minutes_restantes > 0) {
                $salaire_total += ($minutes_restantes * ($tarif/60)) / 2;
            }
        }
    }
    
    $enseignant['salaire'] = $salaire_total;
}
unset($enseignant);

// Calcul des totaux
$total_heures = 0;
$total_salaires = 0;

foreach ($enseignants as $enseignant) {
    $total_heures += $enseignant['heures_effectuees'] ?? 0;
    $total_salaires += $enseignant['salaire'] ?? 0;
}
?>

<div class="container py-5">
    <h1 class="text-center mb-5" style="color: #6e48aa;">
        <i class="fas fa-file-invoice-dollar me-2"></i> Suivi des Salaires des Enseignants
    </h1>

    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <form method="get" class="row g-3">
                        <div class="col-md-8">
                            <select name="niveau_filter" class="form-select" onchange="this.form.submit()">
                                <option value="">Tous les niveaux</option>
                                <?php foreach ($niveaux as $niveau): ?>
                                <option value="<?= $niveau['id'] ?>" <?= $niveau_id == $niveau['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($niveau['nom']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4 text-end">
                            <a href="?export=pdf<?= !empty($niveau_id) ? '&niveau_filter='.$niveau_id : '' ?>" class="btn btn-danger me-2">
                                <i class="fas fa-file-pdf me-2"></i> Exporter PDF
                            </a>
                            <a href="gestion_tarifs.php" class="btn btn-primary">
                                <i class="fas fa-cog me-2"></i> Gérer les tarifs
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <?php if (!empty($niveau_id)): 
        $niveau_nom = $niveaux[array_search($niveau_id, array_column($niveaux, 'id'))]['nom'];
    ?>
    <div class="alert alert-info mb-4">
        Filtre appliqué : <strong>Niveau <?= htmlspecialchars($niveau_nom) ?></strong>
        <a href="suivi_salaires.php" class="float-end">
            <i class="fas fa-times"></i> Supprimer le filtre
        </a>
    </div>
    <?php endif; ?>

    <!-- Tableau des enseignants -->
    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>Nom</th>
                            <th>Téléphone</th>
                            <th>Heures effectuées</th>
                            <th>Heures prévues</th>
                            <th>Retard moyen (min)</th>
                            <th>Niveaux</th>
                            <th>Salaire total (FCFA)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($enseignants)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-4">
                                Aucun enseignant trouvé avec les critères sélectionnés
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($enseignants as $enseignant): ?>
                        <tr>
                            <td><?= htmlspecialchars($enseignant['name']) ?></td>
                            <td><?= htmlspecialchars($enseignant['phone']) ?></td>
                            <td><?= number_format($enseignant['heures_effectuees'] ?? 0, 2, ',', ' ') ?>h</td>
                            <td><?= number_format($enseignant['heures_prevues'] ?? 0, 2, ',', ' ') ?>h</td>
                            <td><?= round($enseignant['retard_minutes'] / max(1, $enseignant['heures_effectuees']), 1) ?></td>
                            <td><?= htmlspecialchars($enseignant['niveaux'] ?? 'N/A') ?></td>
                            <td><?= number_format($enseignant['salaire'] ?? 0, 2, ',', ' ') ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                    <tfoot class="table-secondary">
                        <tr>
                            <th colspan="2">Totaux</th>
                            <th><?= number_format($total_heures, 2, ',', ' ') ?>h</th>
                            <th></th>
                            <th></th>
                            <th></th>
                            <th><?= number_format($total_salaires, 2, ',', ' ') ?> FCFA</th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/admin-footer.php'; ?>