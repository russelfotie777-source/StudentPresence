<?php
include 'includes/db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $niveau_id = $_POST['niveau_id'];
    $nom = $_POST['nom'];
    
    $stmt = $pdo->prepare("INSERT INTO filieres (nom, niveau_id) VALUES (?, ?)");
    $stmt->execute([$nom, $niveau_id]);
    
    header("Location: niveau.php?id=$niveau_id");
    exit;
}

$niveau_id = $_GET['niveau_id'] ?? 0;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Créer une filière</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body class="admin-body">
    <div class="container py-5">
        <h1 class="text-center mb-5 neon-violet">Créer une filière</h1>
        
        <div class="row justify-content-center">
            <div class="col-md-6">
                <form method="post">
                    <input type="hidden" name="niveau_id" value="<?= $niveau_id ?>">
                    
                    <div class="mb-3">
                        <label for="nom" class="form-label">Nom de la filière</label>
                        <input type="text" class="form-control bg-dark text-white" id="nom" name="nom" required>
                    </div>
                    
                    <div class="text-center">
                        <button type="submit" class="btn btn-violet">Créer</button>
                        <a href="niveau.php?id=<?= $niveau_id ?>" class="btn btn-dark">Annuler</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>