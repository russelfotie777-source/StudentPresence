<?php
include 'includes/db.php';

$id = $_GET['id'] ?? 0;
$action = $_GET['action'] ?? '';

if ($id && in_array($action, ['yes', 'no'])) {
    $status = $action == 'yes' ? 'yes' : 'none';
    $stmt = $pdo->prepare("UPDATE users SET validated = ? WHERE id = ?");
    $stmt->execute([$status, $id]);
}

header('Location: designation.php');
exit;
?>