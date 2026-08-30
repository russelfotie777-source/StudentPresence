<?php
include 'includes/db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $filiere_id = $_POST['filiere_id'];
    $nom = $_POST['nom'];
    $formation = $_POST['formation'];
    
    $stmt = $pdo->prepare("INSERT INTO salles (nom, filiere_id, formation) VALUES (?, ?, ?)");
    $stmt->execute([$nom, $filiere_id, $formation]);
    
    header("Location: filiere.php?id=$filiere_id");
    exit;
}

$filiere_id = $_GET['filiere_id'] ?? 0;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Créer une salle</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        .form-control, .form-select {
            background-color: rgba(20, 20, 20, 0.8) !important;
            color: white !important;
            border: 1px solid #6e48aa;
        }
        .form-control:focus, .form-select:focus {
            box-shadow: 0 0 0 0.25rem rgba(110, 72, 170, 0.25);
            border-color: #9b72cf;
        }
    </style>
</head>
<body class="admin-body">
    <div class="container py-5">
        <h1 class="text-center mb-5 neon-violet">Créer une salle</h1>
        
        <div class="row justify-content-center">
            <div class="col-md-6">
                <form method="post">
                    <input type="hidden" name="filiere_id" value="<?= $filiere_id ?>">
                    
                    <div class="mb-3">
                        <label for="nom" class="form-label">Nom de la salle</label>
                        <input type="text" class="form-control" id="nom" name="nom" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="formation" class="form-label">Type de formation</label>
                        <select class="form-select" id="formation" name="formation" required>
                            <option value="FI">Formation Initiale (FI)</option>
                            <option value="FA">Formation Alternance (FA)</option>
                        </select>
                    </div>
                    
                    <div class="text-center mt-4">
                        <button type="submit" class="btn btn-violet">Créer</button>
                        <a href="filiere.php?id=<?= $filiere_id ?>" class="btn btn-dark">Annuler</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>