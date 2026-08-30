<?php
include 'includes/db.php';

$seance_id = $_GET['id'] ?? 0;
$salle_id = $_GET['salle_id'] ?? 0;

if ($seance_id) {
    try {
        $stmt = $pdo->prepare("DELETE FROM seances WHERE id = ?");
        $stmt->execute([$seance_id]);
        
        header("Location: salle_detail.php?id=$salle_id&success=Séance supprimée avec succès");
        exit;
    } catch (PDOException $e) {
        header("Location: salle_detail.php?id=$salle_id&error=Erreur lors de la suppression");
        exit;
    }
}

header("Location: index.php");
exit;