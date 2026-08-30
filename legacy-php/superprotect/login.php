<?php
session_start();
require_once __DIR__ . '/includes/admin_credentials.php';

$error_message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $valid_username = ADMIN_USERNAME;
    $valid_password = ADMIN_PASSWORD;

    if ($username === $valid_username && $password === $valid_password) {
        // Authentification réussie, enregistrer l'utilisateur dans la session
        $_SESSION['admin_logged_in'] = true;
        // Rediriger vers la page d'administration
        header("Location: index.php");
        exit();
    } else {
        // Authentification échouée
        $error_message = "Nom d'utilisateur ou mot de passe incorrect.";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion Administration</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(to right, #4a00e0, #8e2de2);
            color: #fff;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            font-family: sans-serif;
        }
        .login-container {
            background: rgba(255, 255, 255, 0.1);
            padding: 2rem;
            border-radius: 15px;
            backdrop-filter: blur(10px);
            box-shadow: 0 4px 30px rgba(0, 0, 0, 0.2);
            width: 100%;
            max-width: 400px;
        }
        .form-control {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: #fff;
        }
        .form-control::placeholder {
            color: #ccc;
        }
        .btn-neon-orange {
            background: linear-gradient(90deg, #ff7e5f 0%, #feb47b 100%);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 30px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: all 0.3s;
            box-shadow: 0 0 15px rgba(255, 126, 95, 0.5);
            width: 100%;
        }
        .btn-neon-orange:hover {
            transform: translateY(-3px);
            box-shadow: 0 0 20px rgba(255, 126, 95, 0.8);
        }
    </style>
</head>
<body>

<div class="login-container">
    <h2 class="text-center mb-4">Connexion Admin</h2>
    <?php if ($error_message): ?>
        <div class="alert alert-danger text-center" role="alert">
            <?= $error_message ?>   </div>
    <?php endif; ?>
    <form action="login.php" method="POST">
  <div class="mb-3">
            <label for="username" class="form-label">Nom d'utilisateur</label>
            <input type="text" class="form-control" id="username" name="username" required>
        </div>
   <div class="mb-3">
            <label for="password" class="form-label">Mot de passe</label>
            <input type="password" class="form-control" id="password" name="password" required>
        </div>    <div class="d-grid mt-4">
            <button type="submit" class="btn btn-neon-orange">Se connecter</button>
</div>
</form>
</div>

</body>
</html>