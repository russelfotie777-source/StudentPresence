<?php
include 'includes/db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nom = $_POST['nom'];
    
    $stmt = $pdo->prepare("INSERT INTO niveaux (nom) VALUES (?)");
    $stmt->execute([$nom]);
    
    header("Location: index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Créer un niveau</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        :root {
            --cosmic-dark: #0f0e1a;
            --cosmic-purple: #6a0dad;
            --cosmic-pink: #ff00ff;
            --cosmic-blue: #00ffff;
            --cosmic-white: #e0e0ff;
        }

        .admin-body {
            background: var(--cosmic-dark);
            background-image: 
                radial-gradient(circle at 20% 30%, rgba(106, 13, 173, 0.15) 0%, transparent 30%),
                radial-gradient(circle at 80% 70%, rgba(0, 255, 255, 0.15) 0%, transparent 30%);
            background-attachment: fixed;
            color: var(--cosmic-white);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            position: relative;
            overflow-x: hidden;
        }

        .neon-violet {
            color: var(--cosmic-white);
            text-shadow: 
                0 0 5px var(--cosmic-white),
                0 0 10px var(--cosmic-purple),
                0 0 20px var(--cosmic-purple),
                0 0 30px var(--cosmic-pink);
            font-weight: 700;
            letter-spacing: 2px;
            position: relative;
            padding-bottom: 10px;
        }

        .neon-violet::after {
            content: "";
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 100px;
            height: 3px;
            background: linear-gradient(90deg, transparent, var(--cosmic-blue), transparent);
            border-radius: 50%;
            filter: blur(1px);
        }

        .form-control {
            background-color: rgba(15, 14, 26, 0.7) !important;
            border: 1px solid rgba(106, 13, 173, 0.3) !important;
            color: var(--cosmic-white) !important;
            backdrop-filter: blur(5px);
        }

        .form-control:focus {
            box-shadow: 0 0 0 0.25rem rgba(106, 13, 173, 0.25);
            border-color: var(--cosmic-pink) !important;
        }

        .btn-violet {
            background: var(--cosmic-purple);
            border: none;
            color: white;
            padding: 8px 20px;
            border-radius: 25px;
            font-weight: 500;
            transition: all 0.3s ease;
            box-shadow: 0 0 10px rgba(106, 13, 173, 0.5);
        }

        .btn-violet:hover {
            background: var(--cosmic-pink);
            transform: translateY(-2px);
            box-shadow: 0 0 20px rgba(255, 0, 255, 0.7);
        }

        .btn-dark {
            background: rgba(15, 14, 26, 0.7);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: var(--cosmic-white);
            transition: all 0.3s ease;
        }

        .btn-dark:hover {
            background: rgba(106, 13, 173, 0.3);
            border-color: var(--cosmic-purple);
            color: var(--cosmic-white);
        }
    </style>
</head>
<body class="admin-body">
    <div class="container py-5">
        <h1 class="text-center mb-5 neon-violet">Créer un nouveau niveau</h1>
        
        <div class="row justify-content-center">
            <div class="col-md-6">
                <form method="post">
                    <div class="mb-4">
                        <label for="nom" class="form-label mb-3">Nom du niveau</label>
                        <input type="text" class="form-control form-control-lg" id="nom" name="nom" required>
                    </div>
                    
                    <div class="text-center mt-4">
                        <button type="submit" class="btn btn-violet px-4 py-2 me-3">
                            <i class="fas fa-save me-2"></i> Créer
                        </button>
                        <a href="index.php" class="btn btn-dark px-4 py-2">
                            <i class="fas fa-arrow-left me-2"></i> Annuler
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
</body>
</html>