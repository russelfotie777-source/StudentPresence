<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

if (!isLoggedIn() || !isDelegate() || $_SESSION['validated'] !== 'none') {
    header('Location: index.php');
    exit();
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $pdo->prepare("UPDATE users SET validated = 'pending' WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    
    $_SESSION['validated'] = 'pending';
    $message = 'Votre demande de validation a été envoyée. Veuillez attendre l\'approbation.';
    header('Refresh: 0');
    exit();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Validation Requise</title>
    <link rel="stylesheet" href="includes/style.css">
</head>
<body>
    <div class="auth-container">
        <h1>Validation Requise</h1>
        <?php if (!empty($message)): ?>
            <div class="success"><?php echo $message; ?></div>
            <p style="text-align: center; margin-top: 20px;">
                Vous serez redirigé vers la page de connexion lorsque votre compte sera validé.
            </p>
        <?php else: ?>
            <p style="text-align: center; margin-bottom: 20px;">
                En tant que délégué, votre compte doit être validé avant de pouvoir accéder au système.
            </p>
            <form action="validation.php" method="POST">
                <button type="submit">Demander la validation</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>