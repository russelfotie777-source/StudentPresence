<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Vérifier si l'utilisateur est un enseignant non validé
if (!isLoggedIn() || !isTeacher() || $_SESSION['validated'] !== 'none') {
    header('Location: index.php');
    exit();
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Mettre à jour le statut à 'pending' dans la base de données
    $stmt = $pdo->prepare("UPDATE users SET validated = 'pending' WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    
    // Mettre à jour la session
    $_SESSION['validated'] = 'pending';
    
    // Détruire la session et rediriger vers l'index avec un message
    session_destroy();
    header('Location: index.php?error=pending');
    exit();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Validation Enseignant Requise</title>
    <link rel="stylesheet" href="includes/style.css">
    <style>
        :root {
            --violet-dark: #2a0a42;
            --violet-medium: #4b2a70;
            --violet-light: #7b4b9e;
            --violet-neon: #b388ff;
            --blanc: #ffffff;
            --noir: #0a0a0a;
        }
        
        .auth-container {
            background: rgba(10, 10, 10, 0.8);
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.3);
            width: 100%;
            max-width: 500px;
            margin: 2rem auto;
            border: 1px solid var(--violet-medium);
        }
        
        h1 {
            text-align: center;
            color: var(--violet-neon);
            margin-bottom: 1.5rem;
        }
        
        p.info-text {
            text-align: center;
            margin-bottom: 2rem;
            line-height: 1.6;
        }
        
        button.submit-btn {
            width: 100%;
            padding: 12px;
            background: var(--violet-light);
            color: var(--blanc);
            border: none;
            border-radius: 4px;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        button.submit-btn:hover {
            background: var(--violet-neon);
            transform: translateY(-2px);
        }
        
        .steps {
            margin-top: 2rem;
            padding: 1rem;
            background: rgba(123, 75, 158, 0.1);
            border-radius: 6px;
        }
        
        .steps h3 {
            color: var(--violet-neon);
            margin-bottom: 1rem;
        }
        
        .steps ol {
            padding-left: 1.5rem;
        }
        
        .steps li {
            margin-bottom: 0.5rem;
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <h1>Validation Enseignant Requise</h1>
        
        <p class="info-text">
            En tant qu'enseignant, votre compte doit être validé par l'administrateur
            avant de pouvoir accéder au système. Veuillez soumettre votre demande de validation.
        </p>
        
        <form method="POST" action="validation_enseignant_user.php">
            <button type="submit" class="submit-btn">
                Soumettre ma demande de validation
            </button>
        </form>
        
        <div class="steps">
            <h3>Processus de validation :</h3>
            <ol>
                <li>Cliquez sur le bouton ci-dessus pour soumettre votre demande</li>
                <li>Votre compte sera mis en attente de validation</li>
                <li>L'administrateur traitera votre demande sous 24-48h</li>
                <li>Vous recevrez un email de confirmation une fois validé</li>
                <li>Vous pourrez alors vous connecter normalement</li>
            </ol>
        </div>
    </div>
</body>
</html>