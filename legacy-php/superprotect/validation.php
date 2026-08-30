<?php
require_once '../includes/config.php';

// Récupérer les délégués en attente de validation
$pendingDelegates = $pdo->query("SELECT * FROM users WHERE grade = 'Delegue' AND validated = 'pending'")->fetchAll(PDO::FETCH_ASSOC);

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_POST['user_id'];
    $action = $_POST['action'];
    
    if ($action === 'approve') {
        $pdo->query("UPDATE users SET validated = 'yes' WHERE id = $user_id");
    } elseif ($action === 'reject') {
        $pdo->query("UPDATE users SET validated = 'none' WHERE id = $user_id");
    }
    
    header("Location: validation.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Validation des Délégués</title>
    <link rel="stylesheet" href="../includes/style.css">
    <style>
        .delegate-list {
            margin-top: 30px;
        }
        
        .delegate-item {
            background-color: rgba(10, 10, 10, 0.7);
            border: 1px solid var(--violet-neon);
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .delegate-info {
            flex: 1;
        }
        
        .delegate-actions button {
            padding: 8px 15px;
            margin-left: 10px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .approve-btn {
            background-color: #4CAF50;
            color: white;
        }
        
        .reject-btn {
            background-color: #F44336;
            color: white;
        }
    </style>
</head>
<body>
    <?php include 'admin-navbar.php'; ?>
    
    <div class="admin-container">
        <h1 style="color: var(--violet-neon); text-align: center;">Validation des Délégués</h1>
        
        <div class="delegate-list">
            <?php if (empty($pendingDelegates)): ?>
                <p style="text-align: center;">Aucune demande de validation en attente</p>
            <?php else: ?>
                <?php foreach ($pendingDelegates as $delegate): ?>
                    <div class="delegate-item">
                        <div class="delegate-info">
                            <h3><?= htmlspecialchars($delegate['name']) ?></h3>
                            <p>Téléphone: <?= htmlspecialchars($delegate['phone']) ?></p>
                            <p>Salle: <?= htmlspecialchars($delegate['classroom']) ?></p>
                        </div>
                        <div class="delegate-actions">
                            <form action="validation.php" method="POST" style="display: inline;">
                                <input type="hidden" name="user_id" value="<?= $delegate['id'] ?>">
                                <input type="hidden" name="action" value="approve">
                                <button type="submit" class="approve-btn">Approuver</button>
                            </form>
                            <form action="validation.php" method="POST" style="display: inline;">
                                <input type="hidden" name="user_id" value="<?= $delegate['id'] ?>">
                                <input type="hidden" name="action" value="reject">
                                <button type="submit" class="reject-btn">Rejeter</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>