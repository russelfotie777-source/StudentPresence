<?php 
include 'includes/db.php';
include 'includes/admin-header.php';

// Récupération des paramètres
$enseignant_id = $_GET['enseignant_id'] ?? null;
$semaine_id = $_GET['semaine_id'] ?? null;
$salle_id = $_GET['salle_id'] ?? null;
$matiere_id = $_GET['matiere_id'] ?? null;

// Récupérer les listes pour les filtres
$enseignants = $pdo->query("SELECT id, name FROM users WHERE grade = 'Enseignant' ORDER BY name")->fetchAll();
$semaines = $pdo->query("SELECT * FROM semaines ORDER BY date_debut DESC")->fetchAll();
$salles = $pdo->query("SELECT id, nom FROM salles ORDER BY nom")->fetchAll();
$matieres = $pdo->query("SELECT id, nom FROM matieres ORDER BY nom")->fetchAll();

$query = "SELECT 
            s.id,
            s.date_seance,
            s.heure_debut,
            s.heure_fin,
            s.debut_reel,
            s.fin_reelle,
            s.etat_delegue,
            s.etat_prof,
            s.etat_final,
            s.commentaires,
            m.nom as matiere,
            m.code as code_matiere,
            u.name as enseignant,
            sa.nom as salle,
            f.nom as filiere,
            n.nom as niveau,
            sem.numero as semaine_numero,
            sem.date_debut as semaine_debut,
            sem.date_fin as semaine_fin,
            c.jour as jour_prevue,
            c.heure_debut as heure_debut_prevue,
            c.heure_fin as heure_fin_prevue,
            TIMESTAMPDIFF(MINUTE, s.debut_reel, s.fin_reelle)/60 as duree_reelle,
            TIMESTAMPDIFF(MINUTE, c.heure_debut, c.heure_fin)/60 as duree_prevue
          FROM seances s
          JOIN cours c ON s.cours_id = c.id
          JOIN matieres m ON c.matiere_id = m.id
          JOIN users u ON s.enseignant_id = u.id
          JOIN salles sa ON s.salle_id = sa.id
          JOIN emplois_temps et ON c.emploi_id = et.id
          LEFT JOIN filieres f ON sa.filiere_id = f.id
          LEFT JOIN niveaux n ON f.niveau_id = n.id
          JOIN semaines sem ON s.semaine_id = sem.id
          WHERE 1=1";

$params = [];

if ($enseignant_id) {
    $query .= " AND s.enseignant_id = ?";
    $params[] = $enseignant_id;
}

if ($semaine_id) {
    $query .= " AND s.semaine_id = ?";
    $params[] = $semaine_id;
}

if ($salle_id) {
    $query .= " AND s.salle_id = ?";
    $params[] = $salle_id;
}

if ($matiere_id) {
    $query .= " AND c.matiere_id = ?";
    $params[] = $matiere_id;
}

$query .= " ORDER BY s.date_seance DESC, s.heure_debut";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$seances = $stmt->fetchAll();

// Calcul des statistiques
$stats = [
    'total' => count($seances),
    'present' => count(array_filter($seances, fn($s) => $s['etat_final'] == 'present')),
    'duree_prevue' => array_sum(array_map(fn($s) => $s['duree_prevue'], $seances)),
    'duree_reelle' => array_sum(array_map(fn($s) => $s['etat_final'] == 'present' ? $s['duree_reelle'] : 0, $seances))
];
?>

<div class="container py-5">
    <h1 class="text-center mb-5" style="color: #6e48aa;">
        <i class="fas fa-history me-2"></i> Historique des Séances
    </h1>

    <!-- Filtres améliorés -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="get" class="row g-3">
                <div class="col-md-3">
                    <label for="enseignant_id" class="form-label">Enseignant</label>
                    <select name="enseignant_id" id="enseignant_id" class="form-select">
                        <option value="">Tous les enseignants</option>
                        <?php foreach ($enseignants as $enseignant): ?>
                        <option value="<?= $enseignant['id'] ?>" <?= $enseignant_id == $enseignant['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($enseignant['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="semaine_id" class="form-label">Semaine</label>
                    <select name="semaine_id" id="semaine_id" class="form-select">
                        <option value="">Toutes les semaines</option>
                        <?php foreach ($semaines as $semaine): ?>
                        <option value="<?= $semaine['id'] ?>" <?= $semaine_id == $semaine['id'] ? 'selected' : '' ?>>
                            Semaine <?= $semaine['numero'] ?> (<?= date('d/m/Y', strtotime($semaine['date_debut'])) ?> - <?= date('d/m/Y', strtotime($semaine['date_fin'])) ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="salle_id" class="form-label">Salle</label>
                    <select name="salle_id" id="salle_id" class="form-select">
                        <option value="">Toutes les salles</option>
                        <?php foreach ($salles as $salle): ?>
                        <option value="<?= $salle['id'] ?>" <?= $salle_id == $salle['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($salle['nom']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="matiere_id" class="form-label">Matière</label>
                    <select name="matiere_id" id="matiere_id" class="form-select">
                        <option value="">Toutes les matières</option>
                        <?php foreach ($matieres as $matiere): ?>
                        <option value="<?= $matiere['id'] ?>" <?= $matiere_id == $matiere['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($matiere['nom']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="fas fa-filter me-2"></i>Filtrer
                    </button>
                    <a href="historique_seances.php" class="btn btn-outline-secondary">
                        <i class="fas fa-times me-2"></i>Réinitialiser
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Statistiques améliorées -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white shadow-sm">
                <div class="card-body">
                    <h5 class="card-title">Séances programmées</h5>
                    <p class="card-text display-6"><?= $stats['total'] ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white shadow-sm">
                <div class="card-body">
                    <h5 class="card-title">Séances effectuées</h5>
                    <p class="card-text display-6"><?= $stats['present'] ?></p>
                    <small><?= $stats['total'] > 0 ? round($stats['present']/$stats['total']*100, 1) : 0 ?>%</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white shadow-sm">
                <div class="card-body">
                    <h5 class="card-title">Heures prévues</h5>
                    <p class="card-text display-6"><?= round($stats['duree_prevue'], 1) ?>h</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white shadow-sm">
                <div class="card-body">
                    <h5 class="card-title">Heures effectuées</h5>
                    <p class="card-text display-6"><?= round($stats['duree_reelle'], 1) ?>h</p>
                    <small><?= $stats['duree_prevue'] > 0 ? round($stats['duree_reelle']/$stats['duree_prevue']*100, 1) : 0 ?>%</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Tableau amélioré -->
    <div class="card shadow-sm">
        <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Détail des séances</h5>
            <div>
                <span class="badge bg-success me-2">Présent</span>
                <span class="badge bg-danger">Absent</span>
                <span class="badge bg-warning ms-2">Retard</span>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Date</th>
                            <th>Semaine</th>
                            <th>Matière</th>
                            <th>Enseignant</th>
                            <th>Salle</th>
                            <th>Filière/Niveau</th>
                            <th>Créneau prévu</th>
                            <th>Créneau réel</th>
                            <th>Durée</th>
                            <th>État</th>
                            <th>Commentaires</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($seances as $seance): 
                            $retard = $seance['etat_final'] == 'present' && 
                                     strtotime($seance['debut_reel']) > strtotime($seance['heure_debut']);
                        ?>
                        <tr class="<?= $seance['etat_final'] == 'present' ? ($retard ? 'table-warning' : 'table-success') : 'table-danger' ?>">
                            <td><?= date('d/m/Y', strtotime($seance['date_seance'])) ?></td>
                            <td>Sem. <?= $seance['semaine_numero'] ?></td>
                            <td>
                                <small class="text-muted"><?= $seance['code_matiere'] ?></small><br>
                                <?= htmlspecialchars($seance['matiere']) ?>
                            </td>
                            <td><?= htmlspecialchars($seance['enseignant']) ?></td>
                            <td><?= htmlspecialchars($seance['salle']) ?></td>
                            <td>
                                <?= $seance['filiere'] ? htmlspecialchars($seance['filiere']) : 'N/A' ?><br>
                                <small><?= $seance['niveau'] ?? '' ?></small>
                            </td>
                            <td>
                                <?= $seance['jour_prevue'] ?><br>
                                <?= date('H:i', strtotime($seance['heure_debut_prevue'])) ?>-<?= date('H:i', strtotime($seance['heure_fin_prevue'])) ?>
                            </td>
                            <td>
                                <?= $seance['debut_reel'] ? date('H:i', strtotime($seance['debut_reel'])) : '--:--' ?>-<?= $seance['fin_reelle'] ? date('H:i', strtotime($seance['fin_reelle'])) : '--:--' ?>
                                <?php if ($retard): ?>
                                <br><small class="text-danger">Retard: <?= round((strtotime($seance['debut_reel']) - strtotime($seance['heure_debut']))/60) ?> min</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= round($seance['duree_reelle'], 1) ?>h / <?= round($seance['duree_prevue'], 1) ?>h
                            </td>
                            <td>
                                <?php if ($seance['etat_final'] == 'present'): ?>
                                    <span class="badge bg-<?= $retard ? 'warning' : 'success' ?>">
                                        <?= $retard ? 'Retard' : 'Présent' ?>
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-danger">
                                        Absent
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($seance['commentaires']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($seances)): ?>
                        <tr>
                            <td colspan="11" class="text-center text-muted py-4">Aucune séance trouvée</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Export -->
    <?php if (!empty($seances)): ?>
    <div class="mt-3 text-end">
        <a href="export_historique.php?<?= http_build_query($_GET) ?>" class="btn btn-outline-primary">
            <i class="fas fa-file-excel me-2"></i>Exporter en Excel
        </a>
    </div>
    <?php endif; ?>
</div>

<?php include 'includes/admin-footer.php'; ?>