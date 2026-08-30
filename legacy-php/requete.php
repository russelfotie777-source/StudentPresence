<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Récupérer les données de la requête GET
$seance_id = isset($_GET['seance_id']) ? intval($_GET['seance_id']) : 0;
$heure = isset($_GET['heure']) ? htmlspecialchars($_GET['heure']) : '';
$matiere = isset($_GET['matiere']) ? htmlspecialchars($_GET['matiere']) : '';
$salle = isset($_GET['salle']) ? htmlspecialchars($_GET['salle']) : '';
$niveau = isset($_GET['niveau']) ? htmlspecialchars($_GET['niveau']) : '';
$penalite = isset($_GET['penalite']) ? floatval($_GET['penalite']) : 0;

// Traitement du formulaire de requête
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    
    // Gestion du fichier uploadé
    $uploadOk = 1;
    $imagePath = '';
    $error_message = '';
    
    // Définir le chemin absolu du dossier d'upload
    $target_dir = __DIR__ . "/uploads/requetes/";
    
    // Vérifier/Créer le dossier avec les bonnes permissions
    if (!file_exists($target_dir)) {
        if (!mkdir($target_dir, 0755, true)) {
            $error_message = "Impossible de créer le dossier de destination. Veuillez contacter l'administrateur.";
            $uploadOk = 0;
        }
    }
    
    // Vérifier que le dossier est accessible en écriture
    if ($uploadOk == 1 && !is_writable($target_dir)) {
        $error_message = "Le dossier de destination n'est pas accessible en écriture. Veuillez contacter l'administrateur.";
        $uploadOk = 0;
    }
    
    // Vérifier si un fichier a été uploadé
    if ($uploadOk == 1 && isset($_FILES['cahier_texte']) && $_FILES['cahier_texte']['error'] === UPLOAD_ERR_OK) {
        // Nettoyer le nom du fichier
        $file_name = preg_replace("/[^a-zA-Z0-9\.\-]/", "_", basename($_FILES["cahier_texte"]["name"]));
        $target_file = $target_dir . uniqid() . '_' . $file_name;
        $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
        
        // Vérifier si le fichier est une image ou PDF
        $check = getimagesize($_FILES["cahier_texte"]["tmp_name"]);
        if ($check === false && $imageFileType != 'pdf') {
            $error_message = "Le fichier n'est pas une image valide ou un PDF.";
            $uploadOk = 0;
        }
        
        // Vérifier la taille du fichier (max 5MB)
        if ($_FILES["cahier_texte"]["size"] > 5000000) {
            $error_message = "Désolé, votre fichier est trop volumineux (max 5MB).";
            $uploadOk = 0;
        }
        
        // Autoriser certains formats de fichier
        $allowed_extensions = ["jpg", "jpeg", "png", "gif", "pdf"];
        if (!in_array($imageFileType, $allowed_extensions)) {
            $error_message = "Désolé, seuls les fichiers JPG, JPEG, PNG, GIF et PDF sont autorisés.";
            $uploadOk = 0;
        }
        
        // Enregistrer le fichier si tout est OK
        if ($uploadOk == 1) {
            if (move_uploaded_file($_FILES["cahier_texte"]["tmp_name"], $target_file)) {
                $imagePath = str_replace(__DIR__ . '/', '', $target_file); // Stocker le chemin relatif
            } else {
                $error_message = "Désolé, une erreur s'est produite lors du téléchargement de votre fichier.";
                $uploadOk = 0;
            }
        }
    } elseif (!isset($_FILES['cahier_texte']) || $_FILES['cahier_texte']['error'] !== UPLOAD_ERR_OK) {
        $error_message = "Veuillez sélectionner un fichier valide.";
        $uploadOk = 0;
    }
    
    if ($uploadOk == 1) {
        // Valider et formater l'heure
        $heure_seance = null;
        if (!empty($heure)) {
            $heure_obj = DateTime::createFromFormat('H:i:s', $heure);
            if ($heure_obj === false) {
                $heure_obj = DateTime::createFromFormat('H:i', $heure);
            }
            if ($heure_obj !== false) {
                $heure_seance = $heure_obj->format('H:i:s');
            }
        }
        
        // Enregistrer la requête dans la base de données
        $query = "INSERT INTO requetes_enseignants 
                  (seance_id, enseignant_id, heure_seance, matiere, salle, niveau, penalite, description, preuve_path, date_creation) 
                  VALUES 
                  (:seance_id, :enseignant_id, :heure_seance, :matiere, :salle, :niveau, :penalite, :description, :preuve_path, NOW())";
        
        try {
            $stmt = $pdo->prepare($query);
            $stmt->bindParam(':seance_id', $seance_id, PDO::PARAM_INT);
            $stmt->bindParam(':enseignant_id', $_SESSION['user_id'], PDO::PARAM_INT);
            $stmt->bindParam(':heure_seance', $heure_seance);
            $stmt->bindParam(':matiere', $matiere);
            $stmt->bindParam(':salle', $salle);
            $stmt->bindParam(':niveau', $niveau);
            $stmt->bindParam(':penalite', $penalite);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':preuve_path', $imagePath);
            
            if ($stmt->execute()) {
                $success_message = "Votre requête a été soumise avec succès. Elle sera examinée par l'administration.";
            } else {
                $error_message = "Une erreur s'est produite lors de l'enregistrement de votre requête.";
            }
        } catch (PDOException $e) {
            $error_message = "Erreur de base de données : " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Requête - Contestation de pénalité</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --cosmic-primary: #6e48aa;
            --cosmic-secondary: #9d50bb;
            --cosmic-dark: #2a0a42;
            --cosmic-light: #b388ff;
            --cosmic-gradient: linear-gradient(135deg, var(--cosmic-primary), var(--cosmic-secondary));
        }
        
        body {
            background: linear-gradient(135deg, #1a1a2e, #16213e);
            color: #fff;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 0 1rem;
        }
        
        .card {
            background: rgba(26, 26, 46, 0.8);
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
            border: 1px solid var(--cosmic-primary);
            backdrop-filter: blur(10px);
        }
        
        h1 {
            color: var(--cosmic-light);
            text-align: center;
            margin-bottom: 1.5rem;
            font-size: 2rem;
            background: linear-gradient(to right, var(--cosmic-light), #fff);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .btn {
            display: inline-block;
            padding: 0.75rem 1.5rem;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }
        
        .btn-primary {
            background: var(--cosmic-gradient);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(110, 72, 170, 0.4);
        }
        
        .btn-back {
            background: linear-gradient(135deg, #6c757d, #495057);
            color: white;
        }
        
        .btn-back:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(108, 117, 125, 0.4);
        }
        
        .info-box {
            background: rgba(110, 72, 170, 0.2);
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1.5rem;
            border-left: 4px solid var(--cosmic-light);
        }
        
        .info-item {
            margin-bottom: 0.5rem;
        }
        
        .info-label {
            font-weight: bold;
            color: var(--cosmic-light);
        }
        
        .form-label {
            font-weight: bold;
            margin-top: 1rem;
            color: var(--cosmic-light);
        }
        
        .form-control, .form-select {
            background-color: rgba(26, 26, 46, 0.6);
            border: 1px solid var(--cosmic-primary);
            color: white;
        }
        
        .form-control:focus, .form-select:focus {
            background-color: rgba(26, 26, 46, 0.8);
            border-color: var(--cosmic-secondary);
            color: white;
            box-shadow: 0 0 0 0.25rem rgba(110, 72, 170, 0.25);
        }
        
        .alert {
            border-radius: 8px;
        }
        
        .penalite {
            color: #ff6b6b;
            font-weight: bold;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 0 0.5rem;
            }
            
            .card {
                padding: 1rem;
            }
            
            h1 {
                font-size: 1.8rem;
            }
        }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="card shadow-lg">
            <div class="card-body">
                <h1 class="text-center mb-4">
                    <i class="fas fa-paper-plane me-2"></i> Requête de contestation
                </h1>
                
                <?php if (isset($success_message)): ?>
                <div class="alert alert-success">
                    <?= htmlspecialchars($success_message) ?>
                </div>
                <?php elseif (isset($error_message)): ?>
                <div class="alert alert-danger">
                    <?= htmlspecialchars($error_message) ?>
                </div>
                <?php endif; ?>
                
                <div class="info-box" style="color:#fff;">
                    <h5 class="mb-3">Informations sur la séance</h5>
                    <div class="info-item">
                        <span class="info-label">Heure:</span> <?= htmlspecialchars($heure) ?>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Matière:</span> <?= htmlspecialchars($matiere) ?>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Salle:</span> <?= htmlspecialchars($salle) ?>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Niveau:</span> <?= htmlspecialchars($niveau) ?>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Pénalité:</span> 
                        <span class="penalite">-<?= number_format($penalite, 0, ',', ' ') ?> FCFA</span>
                    </div>
                </div>
                
                <form method="POST" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="description" class="form-label">Description de la requête *</label>
                        <textarea class="form-control" id="description" name="description" rows="5" required><?= isset($_POST['description']) ? htmlspecialchars($_POST['description']) : '' ?></textarea>
                        <small class="text-muted">Décrivez en détail pourquoi vous contestez cette pénalité.</small>
                    </div>
                    
                    <div class="mb-4">
                        <label for="cahier_texte" class="form-label">Preuve (Cahier de texte) *</label>
                        <input type="file" class="form-control" id="cahier_texte" name="cahier_texte" accept="image/*,.pdf" required>
                        <small class="text-muted">Téléchargez une photo ou scan du cahier de texte comme preuve (JPG, PNG, PDF - max 5MB).</small>
                    </div>
                    
                    <div class="d-flex justify-content-between">
                        <a href="salaireprof.php" class="btn btn-back">
                            <i class="fas fa-arrow-left me-2"></i> Retour
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane me-2"></i> Soumettre la requête
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>