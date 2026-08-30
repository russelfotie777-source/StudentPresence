<?php
ob_start();
require 'includes/db.php';

// ======================================================================
// GESTION DE L'EXPORT PDF
// ======================================================================
if (isset($_GET['export'])) {
    require_once('includes/tcpdf/tcpdf.php');
    
    $niveau_id = $_GET['niveau_filter'] ?? null;
    
    $query = "SELECT 
                u.id, u.name, u.phone,
                SUM(TIMESTAMPDIFF(HOUR, c.heure_debut, c.heure_fin)) as heures_programmees,
                SUM(CASE WHEN s.etat_final = 'present' THEN TIMESTAMPDIFF(MINUTE, s.debut_reel, s.fin_reelle)/60 ELSE 0 END) as heures_effectuees,
                SUM(CASE WHEN s.etat_final = 'absent' THEN TIMESTAMPDIFF(HOUR, c.heure_debut, c.heure_fin) ELSE 0 END) as heures_absentes,
                GROUP_CONCAT(DISTINCT f.nom ORDER BY f.nom SEPARATOR ', ') as filieres,
                GROUP_CONCAT(DISTINCT n.nom ORDER BY n.nom SEPARATOR ', ') as niveaux
              FROM users u
              LEFT JOIN seances s ON u.id = s.enseignant_id
              LEFT JOIN cours c ON s.cours_id = c.id
              LEFT JOIN salles sa ON s.salle_id = sa.id
              LEFT JOIN filieres f ON sa.filiere_id = f.id
              LEFT JOIN niveaux n ON f.niveau_id = n.id
              WHERE u.grade = 'Enseignant'";
    
    if (!empty($niveau_id)) {
        $query .= " AND n.id = :niveau_id";
    }
    
    $query .= " GROUP BY u.id";
    
    $stmt = $pdo->prepare($query);
    
    if (!empty($niveau_id)) {
        $stmt->bindParam(':niveau_id', $niveau_id, PDO::PARAM_INT);
    }
    
    $stmt->execute();
    $enseignants = $stmt->fetchAll();

    $pdf = new TCPDF('L', PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor('Établissement Scolaire');
    $pdf->SetTitle('Rapport des heures enseignants');
    $pdf->SetMargins(10, 20, 10);
    $pdf->AddPage();

    $html = '<style>
                h1 { color: #6e48aa; text-align: center; }
                table { width: 100%; border-collapse: collapse; }
                th { background-color: #6e48aa; color: white; padding: 5px; }
                td { padding: 4px; border: 1px solid #ddd; }
            </style>
            <h1>Suivi des Heures des Enseignants</h1>';
    
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
                    <th>Heures programmées</th>
                    <th>Heures effectuées</th>
                    <th>Heures absentes</th>
                    <th>Taux présence</th>
                    <th>Filières</th>
                    <th>Niveaux</th>
                </tr>';

    foreach ($enseignants as $enseignant) {
        $taux_presence = ($enseignant['heures_programmees'] > 0) 
            ? round(($enseignant['heures_effectuees'] / $enseignant['heures_programmees']) * 100, 2) 
            : 0;
        
        $html .= '<tr>
                    <td>'.htmlspecialchars($enseignant['name']).'</td>
                    <td>'.htmlspecialchars($enseignant['phone']).'</td>
                    <td>'.($enseignant['heures_programmees'] ?? 0).'h</td>
                    <td>'.number_format($enseignant['heures_effectuees'] ?? 0, 2, ',', ' ').'h</td>
                    <td>'.($enseignant['heures_absentes'] ?? 0).'h</td>
                    <td>'.$taux_presence.'%</td>
                    <td>'.htmlspecialchars($enseignant['filieres'] ?? 'N/A').'</td>
                    <td>'.htmlspecialchars($enseignant['niveaux'] ?? 'N/A').'</td>
                </tr>';
    }

    $html .= '</table>';

    ob_end_clean();
    $pdf->writeHTML($html, true, false, true, false, '');
    $pdf->Output('rapport_heures_'.date('Y-m-d').'.pdf', 'D');
    exit();
}

include 'includes/admin-header.php';

// Gestion des actions
if (isset($_GET['action'])) {
    $id = $_GET['id'] ?? null;
    
    if ($_GET['action'] == 'reset' && $id) {
        $stmt = $pdo->prepare("UPDATE users SET quota = 0 WHERE id = ?");
        $stmt->execute([$id]);
        $_SESSION['message'] = "Heures réinitialisées avec succès";
        $_SESSION['message_type'] = "success";
    } elseif ($_GET['action'] == 'delete' && $id) {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND grade = 'Enseignant'");
        $stmt->execute([$id]);
        $_SESSION['message'] = "Enseignant supprimé avec succès";
        $_SESSION['message_type'] = "success";
    }
    
    $redirect_url = "suiviHeurProf.php";
    if (isset($_GET['niveau_filter'])) {
        $redirect_url .= "?niveau_filter=".$_GET['niveau_filter'];
    }
    if (isset($_GET['search'])) {
        $redirect_url .= (strpos($redirect_url, '?')) === false ? "?" : "&";
        $redirect_url .= "search=".$_GET['search'];
    }
    
    header("Location: ".$redirect_url);
    exit();
}

// Récupération des données
$search = $_GET['search'] ?? '';
$niveau_id = $_GET['niveau_filter'] ?? null;

$stmt_niveaux = $pdo->query("SELECT * FROM niveaux ORDER BY nom");
$niveaux = $stmt_niveaux->fetchAll();

$query = "SELECT 
            u.id, u.name, u.phone,
            SUM(TIMESTAMPDIFF(HOUR, c.heure_debut, c.heure_fin)) as heures_programmees,
            SUM(CASE WHEN s.etat_final = 'present' THEN TIMESTAMPDIFF(MINUTE, s.debut_reel, s.fin_reelle)/60 ELSE 0 END) as heures_effectuees,
            SUM(CASE WHEN s.etat_final = 'absent' THEN TIMESTAMPDIFF(HOUR, c.heure_debut, c.heure_fin) ELSE 0 END) as heures_absentes,
            GROUP_CONCAT(DISTINCT f.nom ORDER BY f.nom SEPARATOR ', ') as filieres,
            GROUP_CONCAT(DISTINCT n.nom ORDER BY n.nom SEPARATOR ', ') as niveaux
          FROM users u
          LEFT JOIN seances s ON u.id = s.enseignant_id
          LEFT JOIN cours c ON s.cours_id = c.id
          LEFT JOIN salles sa ON s.salle_id = sa.id
          LEFT JOIN filieres f ON sa.filiere_id = f.id
          LEFT JOIN niveaux n ON f.niveau_id = n.id
          WHERE u.grade = 'Enseignant'";

if (!empty($search)) {
    $query .= " AND u.name LIKE :search";
}

if (!empty($niveau_id)) {
    $query .= " AND n.id = :niveau_id";
}

$query .= " GROUP BY u.id ORDER BY u.name";

$stmt = $pdo->prepare($query);

if (!empty($search)) {
    $stmt->bindValue(':search', "%$search%");
}

if (!empty($niveau_id)) {
    $stmt->bindParam(':niveau_id', $niveau_id, PDO::PARAM_INT);
}

$stmt->execute();
$enseignants = $stmt->fetchAll();

// Calcul des totaux
$total_programme = 0;
$total_effectue = 0;
$total_absente = 0;

foreach ($enseignants as $enseignant) {
    $total_programme += $enseignant['heures_programmees'] ?? 0;
    $total_effectue += $enseignant['heures_effectuees'] ?? 0;
    $total_absente += $enseignant['heures_absentes'] ?? 0;
}

$taux_global = ($total_programme > 0) ? round(($total_effectue / $total_programme) * 100, 2) : 0;
?>

<div class="container py-5">
    <h1 class="text-center mb-5" style="color: #6e48aa;">
        <i class="fas fa-chalkboard-teacher me-2"></i> Suivi des Heures des Enseignants
    </h1>

    <!-- Barre de recherche et filtres -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <form method="get" class="row g-3">
                        <div class="col-md-5">
                            <div class="input-group">
                                <input type="text" name="search" class="form-control" placeholder="Rechercher un enseignant..." value="<?= htmlspecialchars($search) ?>">
                                <button class="btn btn-primary" type="submit">
                                    <i class="fas fa-search"></i>
                                </button>
                                <?php if (!empty($search)): ?>
                                <a href="suiviHeurProf.php<?= !empty($niveau_id) ? '?niveau_filter='.$niveau_id : '' ?>" class="btn btn-outline-danger">
                                    <i class="fas fa-times"></i>
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <select name="niveau_filter" class="form-select" onchange="this.form.submit()">
                                <option value="">Tous les niveaux</option>
                                <?php foreach ($niveaux as $niveau): ?>
                                <option value="<?= $niveau['id'] ?>" <?= $niveau_id == $niveau['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($niveau['nom']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-3 text-end">
                            <a href="?export=pdf<?= !empty($niveau_id) ? '&niveau_filter='.$niveau_id : '' ?><?= !empty($search) ? '&search='.urlencode($search) : '' ?>" class="btn btn-danger">
                                <i class="fas fa-file-pdf me-2"></i> Exporter en PDF
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <?php if (isset($_SESSION['message'])): ?>
    <div class="alert alert-<?= $_SESSION['message_type'] ?> alert-dismissible fade show" role="alert">
        <?= $_SESSION['message'] ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php 
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
    endif; ?>

    <?php if (!empty($niveau_id)): 
        $niveau_nom = $niveaux[array_search($niveau_id, array_column($niveaux, 'id'))]['nom'];
    ?>
    <div class="alert alert-info mb-4">
        Filtre appliqué : <strong>Niveau <?= htmlspecialchars($niveau_nom) ?></strong>
        <a href="suiviHeurProf.php<?= !empty($search) ? '?search='.urlencode($search) : '' ?>" class="float-end">
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
                            <th>Programmées</th>
                            <th>Effectuées</th>
                            <th>Absentes</th>
                            <th>Taux</th>
                            <th>Filières</th>
                            <?php if (empty($niveau_id)): ?>
                            <th>Niveaux</th>
                            <?php endif; ?>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($enseignants)): ?>
                        <tr>
                            <td colspan="<?= empty($niveau_id) ? 9 : 8 ?>" class="text-center py-4">
                                Aucun enseignant trouvé avec les critères sélectionnés
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($enseignants as $enseignant): 
                            $taux_presence = ($enseignant['heures_programmees'] > 0) 
                                ? round(($enseignant['heures_effectuees'] / $enseignant['heures_programmees']) * 100, 2) 
                                : 0;
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($enseignant['name']) ?></td>
                            <td><?= htmlspecialchars($enseignant['phone']) ?></td>
                            <td><?= $enseignant['heures_programmees'] ?? 0 ?>h</td>
                            <td><?= number_format($enseignant['heures_effectuees'] ?? 0, 2, ',', ' ') ?>h</td>
                            <td><?= $enseignant['heures_absentes'] ?? 0 ?>h</td>
                            <td>
                                <span class="badge bg-<?= $taux_presence >= 90 ? 'success' : ($taux_presence >= 70 ? 'warning' : 'danger') ?>">
                                    <?= $taux_presence ?>%
                                </span>
                            </td>
                            <td><?= htmlspecialchars($enseignant['filieres'] ?? 'N/A') ?></td>
                            <?php if (empty($niveau_id)): ?>
                            <td><?= htmlspecialchars($enseignant['niveaux'] ?? 'N/A') ?></td>
                            <?php endif; ?>
                            <td>
                                <div class="d-flex gap-2">
                                    <a href="historique_seances.php?enseignant_id=<?= $enseignant['id'] ?>" 
                                       class="btn btn-sm btn-info" 
                                       title="Historique">
                                        <i class="fas fa-history"></i>
                                    </a>
                                    <a href="suiviHeurProf.php?action=delete&id=<?= $enseignant['id'] ?><?= !empty($niveau_id) ? '&niveau_filter='.$niveau_id : '' ?><?= !empty($search) ? '&search='.urlencode($search) : '' ?>" 
                                       class="btn btn-sm btn-danger" 
                                       title="Supprimer"
                                       onclick="return confirm('Confirmer la suppression?')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Statistiques -->
    <div class="row mt-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white shadow-sm">
                <div class="card-body">
                    <h5 class="card-title">Programmées</h5>
                    <p class="card-text display-6"><?= $total_programme ?>h</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white shadow-sm">
                <div class="card-body">
                    <h5 class="card-title">Effectuées</h5>
                    <p class="card-text display-6"><?= number_format($total_effectue, 2, ',', ' ') ?>h</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-danger text-white shadow-sm">
                <div class="card-body">
                    <h5 class="card-title">Absentes</h5>
                    <p class="card-text display-6"><?= $total_absente ?>h</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white shadow-sm">
                <div class="card-body">
                    <h5 class="card-title">Taux présence</h5>
                    <p class="card-text display-6"><?= $taux_global ?>%</p>
                </div>
            </div>
        </div>
        
    </div>
    
</div>
<div class="col-md-3 text-end">
    <a href="?export=pdf<?= !empty($niveau_id) ? '&niveau_filter='.$niveau_id : '' ?><?= !empty($search) ? '&search='.urlencode($search) : '' ?>" class="btn btn-danger me-2">
        <i class="fas fa-file-pdf me-2"></i> Exporter
    </a>
    <a href="gestion_tarifs.php" class="btn btn-success">
        <i class="fas fa-money-bill-wave me-2"></i> Gestion Tarifs
    </a>
</div>
<?php include 'includes/admin-footer.php'; ?>