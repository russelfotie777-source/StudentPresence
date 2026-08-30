<?php 
include 'includes/db.php';

$id = $_GET['id'] ?? null;

if (!$id) {
    header("Location: suiviHeurProf.php");
    exit();
}

// Récupérer les informations du professeur
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND grade = 'Enseignant'");
$stmt->execute([$id]);
$prof = $stmt->fetch();

if (!$prof) {
    header("Location: suiviHeurProf.php");
    exit();
}

// Récupérer les heures par niveau
$query = "SELECT 
            n.id, 
            n.nom, 
            SUM(TIMESTAMPDIFF(HOUR, s.heure_debut, s.heure_fin)) as heures_effectuees,
            SUM(CASE WHEN s.etat_final = 'present' THEN TIMESTAMPDIFF(HOUR, s.heure_debut, s.heure_fin) ELSE 0 END) as heures_present,
            SUM(CASE WHEN s.etat_final = 'absent' THEN TIMESTAMPDIFF(HOUR, s.heure_debut, s.heure_fin) ELSE 0 END) as heures_absent
          FROM seances s
          JOIN cours c ON s.cours_id = c.id
          JOIN emplois_temps e ON c.emploi_id = e.id
          JOIN salles sa ON e.salle_id = sa.id
          JOIN filieres f ON sa.filiere_id = f.id
          JOIN niveaux n ON f.niveau_id = n.id
          WHERE s.enseignant_id = ?
          GROUP BY n.id";

$stmt = $pdo->prepare($query);
$stmt->execute([$id]);
$heures_par_niveau = $stmt->fetchAll();

// Calcul du total des heures
$total_heures = array_sum(array_column($heures_par_niveau, 'heures_effectuees'));

// Export Excel - DOIT être placé avant tout affichage HTML
if (isset($_GET['export']) && $_GET['export'] == 'excel') {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="details_prof_'.$prof['id'].'_'.date('Y-m-d').'.xls"');
    
    echo "<table border='1'>";
    echo "<tr><th colspan='4'>Détails des heures pour ".htmlspecialchars($prof['name'])."</th></tr>";
    echo "<tr><th>Niveau</th><th>Heures effectuées</th><th>Heures présentes</th><th>Heures absentes</th></tr>";
    
    foreach ($heures_par_niveau as $niveau) {
        echo "<tr>";
        echo "<td>".htmlspecialchars($niveau['nom'])."</td>";
        echo "<td>".$niveau['heures_effectuees']."h</td>";
        echo "<td>".$niveau['heures_present']."h</td>";
        echo "<td>".$niveau['heures_absent']."h</td>";
        echo "</tr>";
    }
    
    echo "<tr><td colspan='4'><strong>Total heures: ".$total_heures."h</strong></td></tr>";
    echo "</table>";
    exit(); // Important: arrêter l'exécution du script après l'export
}

// Si on arrive ici, c'est qu'on veut afficher la page normalement
include 'includes/admin-header.php';
?>

<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 style="color: #6e48aa;">
            <i class="fas fa-chalkboard-teacher me-2"></i> Détails du Professeur
        </h1>
        <div>
            <a href="suiviHeurProf.php" class="btn btn-primary me-2">
                <i class="fas fa-arrow-left me-2"></i> Retour
            </a>
            <a href="?id=<?= $id ?>&export=excel" class="btn btn-success">
                <i class="fas fa-file-excel me-2"></i> Exporter en Excel
            </a>
        </div>
    </div>

    <!-- Le reste du code HTML reste inchangé -->
    <div class="row">
        <div class="col-md-4">
            <div class="card shadow-sm">
                <div class="card-body text-center">
                    <div class="mb-3">
                        <i class="fas fa-user-tie fa-5x" style="color: #6e48aa;"></i>
                    </div>
                    <h3><?= htmlspecialchars($prof['name']) ?></h3>
                    <p class="text-muted"><?= htmlspecialchars($prof['phone']) ?></p>
                    <div class="mb-3">
                        <span class="badge bg-<?= $prof['quota'] > 40 ? 'danger' : 'success' ?> rounded-pill fs-5">
                            <?= $prof['quota'] ?> heures (quota)
                        </span>
                    </div>
                    <div class="mb-3">
                        <span class="badge bg-info rounded-pill fs-5">
                            <?= $total_heures ?> heures (total effectuées)
                        </span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0">Répartition des heures par niveau</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($heures_par_niveau)): ?>
                        <p class="text-muted">Aucune donnée disponible</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Niveau</th>
                                        <th>Heures effectuées</th>
                                        <th>Heures présentes</th>
                                        <th>Heures absentes</th>
                                        <th>Pourcentage</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($heures_par_niveau as $niveau): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($niveau['nom']) ?></td>
                                        <td><?= $niveau['heures_effectuees'] ?>h</td>
                                        <td><?= $niveau['heures_present'] ?>h</td>
                                        <td><?= $niveau['heures_absent'] ?>h</td>
                                        <td>
                                            <div class="progress">
                                                <div class="progress-bar" 
                                                     role="progressbar" 
                                                     style="width: <?= $total_heures > 0 ? ($niveau['heures_effectuees'] / $total_heures) * 100 : 0 ?>%" 
                                                     aria-valuenow="<?= $niveau['heures_effectuees'] ?>" 
                                                     aria-valuemin="0" 
                                                     aria-valuemax="<?= $total_heures ?>">
                                                    <?= $total_heures > 0 ? round(($niveau['heures_effectuees'] / $total_heures) * 100, 1) : 0 ?>%
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/admin-footer.php'; ?>