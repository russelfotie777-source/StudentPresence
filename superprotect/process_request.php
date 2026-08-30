<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';

session_start();

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$action = isset($_GET['action']) ? $_GET['action'] : '';

if ($id <= 0 || !in_array($action, ['acceptee', 'rejetee'])) {
    $_SESSION['error'] = "Paramètres invalides";
    header('Location: requetes.php');
    exit();
}

// Récupérer la requête avec les informations de la séance
$query = "SELECT r.seance_id, s.heure_debut, s.heure_fin, s.etat_delegue, s.etat_prof
          FROM requetes_enseignants r
          JOIN seances s ON r.seance_id = s.id
          WHERE r.id = ?";
$stmt = $pdo->prepare($query);
$stmt->execute([$id]);
$request = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$request) {
    $_SESSION['error'] = "Requête introuvable";
    header('Location: requetes.php');
    exit();
}

// Traiter la requête
$comment = isset($_POST['comment']) ? trim($_POST['comment']) : '';

try {
    $pdo->beginTransaction();

    // Mettre à jour le statut de la requête
    $updateQuery = "UPDATE requetes_enseignants 
                    SET statut = ?, 
                        date_traitement = NOW(), 
                        commentaire_admin = ?
                    WHERE id = ?";
    $stmt = $pdo->prepare($updateQuery);
    $stmt->execute([$action, $comment, $id]);

    // Si la requête est acceptée, mettre à jour la séance
    if ($action === 'acceptee') {
        // Ne pas inclure etat_final dans la mise à jour car c'est une colonne générée
        $updateSeanceQuery = "UPDATE seances 
                             SET debut_reel = ?, 
                                 fin_reelle = ?, 
                                 etat_prof = 'present'
                             WHERE id = ?";
        $stmt = $pdo->prepare($updateSeanceQuery);
        $stmt->execute([
            $request['heure_debut'],
            $request['heure_fin'],
            $request['seance_id']
        ]);
    }

    $pdo->commit();
    $_SESSION['success'] = "La requête a été traitée avec succès";
} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['error'] = "Une erreur est survenue : " . $e->getMessage();
    error_log("Erreur traitement requête: " . $e->getMessage());
}

header('Location: requetes.php');
exit();
?>