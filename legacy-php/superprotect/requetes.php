<?php
include 'includes/db.php';
require_once 'includes/functions.php';

session_start();

$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($action === 'view' && $id > 0) {
    // Requête optimisée avec jointure LEFT JOIN au cas où la séance serait manquante
    $query = "SELECT r.*, u.name as enseignant_name, 
                     s.id as seance_id,
                     TIME_FORMAT(s.heure_debut, '%H:%i') as seance_heure_debut,
                     TIME_FORMAT(s.heure_fin, '%H:%i') as seance_heure_fin,
                     s.debut_reel, s.fin_reelle
              FROM requetes_enseignants r 
              JOIN users u ON r.enseignant_id = u.id 
              LEFT JOIN seances s ON r.seance_id = s.id 
              WHERE r.id = ?";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$id]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$request) {
        $_SESSION['error'] = "Requête introuvable";
        header('Location: requetes.php');
        exit();
    }

    // Debug: Vérifier les données récupérées
    error_log("Données de la requête: " . print_r($request, true));

    include 'includes/admin-header.php';  
?>
    <div class="card">
        <div class="card-header bg-primary text-white">
            <h2 class="mb-0">Détails de la requête</h2>
        </div>
        <div class="card-body">
            <div class="row mb-4">
                <div class="col-md-6">
                    <h4>Informations de base</h4>
                    <p><strong>Enseignant:</strong> <?= htmlspecialchars($request['enseignant_name'] ?? 'N/A') ?></p>
                    <p><strong>Matière:</strong> <?= htmlspecialchars($request['matiere'] ?? 'N/A') ?></p>
                    <p><strong>Salle:</strong> <?= htmlspecialchars($request['salle'] ?? 'N/A') ?></p>
                    <p><strong>Niveau:</strong> <?= htmlspecialchars($request['niveau'] ?? 'N/A') ?></p>
                </div>
                <div class="col-md-6">
                    <h4>Détails de la séance</h4>
                    <p><strong>ID Séance:</strong> <?= htmlspecialchars($request['seance_id'] ?? 'N/A') ?></p>
                    <p><strong>Heure prévue:</strong> 
                        <?= !empty($request['seance_heure_debut']) ? htmlspecialchars($request['seance_heure_debut']) : 'N/A' ?> - 
                        <?= !empty($request['seance_heure_fin']) ? htmlspecialchars($request['seance_heure_fin']) : 'N/A' ?>
                    </p>
                    <p><strong>Heure marquée:</strong> <?= htmlspecialchars($request['heure_seance'] ?? 'N/A') ?></p>
                    <p><strong>Début réel:</strong> <?= !empty($request['debut_reel']) ? htmlspecialchars($request['debut_reel']) : 'N/A' ?></p>
                    <p><strong>Fin réelle:</strong> <?= !empty($request['fin_reelle']) ? htmlspecialchars($request['fin_reelle']) : 'N/A' ?></p>
                    <p><strong>Pénalité:</strong> <?= isset($request['penalite']) ? number_format($request['penalite'], 0, ',', ' ') : '0' ?> FCFA</p>
                    <p><strong>Statut:</strong> 
                        <span class="badge <?= 
                            ($request['statut'] ?? '') === 'en_attente' ? 'bg-warning' : 
                            (($request['statut'] ?? '') === 'acceptee' ? 'bg-success' : 'bg-danger') 
                        ?>">
                            <?= isset($request['statut']) ? ucfirst(str_replace('_', ' ', $request['statut'])) : 'N/A' ?>
                        </span>
                    </p>
                </div>
            </div>

            <div class="mb-4">
                <h4>Description</h4>
                <div class="card card-body bg-light">
                    <?= isset($request['description']) ? nl2br(htmlspecialchars($request['description'])) : 'Aucune description' ?>
                </div>
            </div>

            <?php if (!empty($request['preuve_path'])): ?>
            <div class="mb-4">
                <h4>Preuve jointe</h4>
                <?php if (pathinfo($request['preuve_path'], PATHINFO_EXTENSION) === 'pdf'): ?>
                    <embed src="../<?= htmlspecialchars($request['preuve_path']) ?>" type="application/pdf" width="100%" height="500px">
                <?php else: ?>
                    <img src="../<?= htmlspecialchars($request['preuve_path']) ?>" alt="Preuve jointe" class="img-fluid">
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if (!empty($request['commentaire_admin'])): ?>
            <div class="mb-4">
                <h4>Commentaire de l'administration</h4>
                <div class="card card-body bg-light">
                    <?= nl2br(htmlspecialchars($request['commentaire_admin'])) ?>
                </div>
            </div>
            <?php endif; ?>

            <div class="d-flex justify-content-between">
                <a href="requetes.php" class="btn btn-secondary">Retour à la liste</a>
                <?php if (($request['statut'] ?? '') === 'en_attente'): ?>
                    <div>
                        <a href="process_request.php?id=<?= $id ?>&action=acceptee" 
                           class="btn btn-success" 
                           onclick="return confirm('Êtes-vous sûr de vouloir valider cette requête?')">
                            Valider la requête
                        </a>
                        <a href="process_request.php?id=<?= $id ?>&action=rejetee" 
                           class="btn btn-danger"
                           onclick="return confirm('Êtes-vous sûr de vouloir rejeter cette requête?')">
                            Refuser la requête
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
} else {
    // Lister toutes les requêtes
    $status = isset($_GET['status']) ? $_GET['status'] : 'all';
    
    $query = "SELECT r.*, u.name as enseignant_name,
                     (SELECT COUNT(*) FROM presences_etudiants pe WHERE pe.seance_id = r.seance_id) as nb_presences
              FROM requetes_enseignants r 
              JOIN users u ON r.enseignant_id = u.id";
    
    $params = [];
    
    if ($status !== 'all') {
        $query .= " WHERE r.statut = ?";
        $params[] = $status;
    }
    
    $query .= " ORDER BY r.date_creation DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

    include 'includes/admin-header.php';     
?>
    <div class="card">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <h2 class="mb-0">Gestion des requêtes enseignants</h2>
            <div class="btn-group">
                <a href="requetes.php?status=all" class="btn btn-sm btn-outline-light <?= $status === 'all' ? 'active' : '' ?>">Toutes</a>
                <a href="requetes.php?status=en_attente" class="btn btn-sm btn-outline-light <?= $status === 'en_attente' ? 'active' : '' ?>">En attente</a>
                <a href="requetes.php?status=acceptee" class="btn btn-sm btn-outline-light <?= $status === 'acceptee' ? 'active' : '' ?>">Acceptées</a>
                <a href="requetes.php?status=rejetee" class="btn btn-sm btn-outline-light <?= $status === 'rejetee' ? 'active' : '' ?>">Refusées</a>
            </div>
        </div>
        <div class="card-body">
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <?= $_SESSION['success'] ?>
                    <?php unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger">
                    <?= $_SESSION['error'] ?>
                    <?php unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>
            
            <?php if (empty($requests)): ?>
                <div class="alert alert-info">
                    Aucune requête trouvée.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Enseignant</th>
                                <th>Matière</th>
                                <th>Salle</th>
                                <th>Présences</th>
                                <th>Pénalité</th>
                                <th>Statut</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($requests as $request): ?>
                            <tr>
                                <td><?= date('d/m/Y H:i', strtotime($request['date_creation'])) ?></td>
                                <td><?= htmlspecialchars($request['enseignant_name']) ?></td>
                                <td><?= htmlspecialchars($request['matiere']) ?></td>
                                <td><?= htmlspecialchars($request['salle']) ?></td>
                                <td><?= $request['nb_presences'] ?></td>
                                <td><?= number_format($request['penalite'], 0, ',', ' ') ?> FCFA</td>
                                <td>
                                    <span class="badge <?= 
                                        $request['statut'] === 'en_attente' ? 'bg-warning' : 
                                        ($request['statut'] === 'acceptee' ? 'bg-success' : 'bg-danger') 
                                    ?>">
                                        <?= ucfirst(str_replace('_', ' ', $request['statut'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="requetes.php?action=view&id=<?= $request['id'] ?>" class="btn btn-sm btn-primary">
                                        <i class="fas fa-eye"></i> Voir
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

include 'includes/admin-footer.php';
?>