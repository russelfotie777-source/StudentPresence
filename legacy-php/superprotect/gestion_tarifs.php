<?php
session_start();
require 'includes/db.php';

// Gestion des actions AVANT tout affichage HTML
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_tarif'])) {
    $niveau_id = $_POST['niveau_id'];
    $tarif_heure = $_POST['tarif_heure'];
    
    // Validation des données
    if (!is_numeric($tarif_heure) || $tarif_heure < 0) {
        $_SESSION['message'] = "Le tarif doit être un nombre positif";
        $_SESSION['message_type'] = "danger";
    } else {
        try {
            // Vérifier d'abord si le tarif existe déjà
            $check = $pdo->prepare("SELECT COUNT(*) FROM tarifs_heures WHERE niveau_id = ?");
            $check->execute([$niveau_id]);
            $exists = $check->fetchColumn();
            
            if ($exists) {
                // Mise à jour si le tarif existe déjà
                $stmt = $pdo->prepare("UPDATE tarifs_heures SET tarif_heure = ? WHERE niveau_id = ?");
                $stmt->execute([$tarif_heure, $niveau_id]);
            } else {
                // Insertion si le tarif n'existe pas encore
                $stmt = $pdo->prepare("INSERT INTO tarifs_heures (niveau_id, tarif_heure) VALUES (?, ?)");
                $stmt->execute([$niveau_id, $tarif_heure]);
            }
            
            $_SESSION['message'] = "Tarif mis à jour avec succès";
            $_SESSION['message_type'] = "success";
        } catch (PDOException $e) {
            $_SESSION['message'] = "Erreur lors de la mise à jour: " . $e->getMessage();
            $_SESSION['message_type'] = "danger";
        }
    }
    header("Location: gestion_tarifs.php");
    exit();
}

// Inclure le header après le traitement des actions
include 'includes/admin-header.php';

// Récupérer les niveaux et leurs tarifs
$tarifs = $pdo->query("SELECT n.id, n.nom, t.tarif_heure 
                       FROM niveaux n 
                       LEFT JOIN tarifs_heures t ON n.id = t.niveau_id 
                       ORDER BY n.id")->fetchAll();
?>

<div class="container py-5">
    <h1 class="text-center mb-5" style="color: #6e48aa;">
        <i class="fas fa-money-bill-wave me-2"></i> Gestion des Tarifs par Niveau
    </h1>

    <?php if (isset($_SESSION['message'])): ?>
    <div class="alert alert-<?= $_SESSION['message_type'] ?> alert-dismissible fade show" role="alert">
        <?= $_SESSION['message'] ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php 
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
    endif; ?>

    <div class="card shadow-sm mb-5">
        <div class="card-body">
            <table class="table table-bordered">
                <thead class="table-dark">
                    <tr>
                        <th>Niveau</th>
                        <th>Tarif par heure (FCFA)</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tarifs as $tarif): ?>
                    <tr>
                        <td><?= htmlspecialchars($tarif['nom']) ?></td>
                        <td>
                            <form method="post" class="d-flex">
                                <input type="number" name="tarif_heure" class="form-control me-2" 
                                       value="<?= $tarif['tarif_heure'] ?? 0 ?>" step="0.01" min="0" required>
                                <input type="hidden" name="niveau_id" value="<?= $tarif['id'] ?>">
                                <button type="submit" name="update_tarif" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Enregistrer
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="text-center">
        <a href="suivi_salaires.php" class="btn btn-success">
            <i class="fas fa-file-invoice-dollar me-2"></i> Voir le suivi des salaires
        </a>
    </div>
</div>

<?php include 'includes/admin-footer.php'; ?>