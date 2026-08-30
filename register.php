<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit();
}

// Récupérer les données depuis la base de données
try {
    // Utilisation de DISTINCT pour éviter les doublons de nom de salle
    $salles = $pdo->query("SELECT DISTINCT nom FROM salles ORDER BY nom")->fetchAll(PDO::FETCH_ASSOC);
    $niveaux = $pdo->query("SELECT id, nom FROM niveaux ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    $filieres = $pdo->query("SELECT id, nom FROM filieres ORDER BY nom")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erreur de connexion à la base de données: " . $e->getMessage());
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $phone = trim($_POST['phone']);
    $grade = trim($_POST['grade']);
    $classroom = ($grade === 'Delegue' || $grade === 'Etudiant') ? trim($_POST['classroom']) : null;
    $formation = ($grade === 'Delegue' || $grade === 'Etudiant') ? trim($_POST['formation']) : null;
    $niveau_id = ($grade === 'Delegue' || $grade === 'Etudiant') ? (int)$_POST['niveau_id'] : null;
    $filiere_id = ($grade === 'Delegue' || $grade === 'Etudiant') ? (int)$_POST['filiere_id'] : null;
    $password = trim($_POST['password']);
    
    // Validation des données
    if (empty($name) || empty($phone) || empty($grade) || empty($password)) {
        $error = 'Tous les champs obligatoires doivent être remplis';
    } elseif (($grade === 'Delegue' || $grade === 'Etudiant') && (empty($classroom) || empty($formation) || empty($niveau_id) || empty($filiere_id))) {
        $error = 'Pour les délégués et étudiants, la salle de classe, la formation, le niveau et la filière sont obligatoires';
    } else {
        // Vérification si le nom existe déjà
        $stmt = $pdo->prepare("SELECT id FROM users WHERE name = ?");
        $stmt->execute([$name]);
        
        if ($stmt->rowCount() > 0) {
            $error = 'Ce nom est déjà utilisé';
        } else {
            // Vérification du nombre d'étudiants 'FM' par salle
            if ($grade === 'Etudiant' && $formation === 'FM') {
                $stmt = $pdo->prepare("SELECT COUNT(*) AS fm_count FROM users WHERE classroom = ? AND formation = 'FM'");
                $stmt->execute([$classroom]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($result['fm_count'] >= 50) {
                    $error = "La salle de classe est pleine pour les étudiants 'FM'. Veuillez en choisir une autre.";
                }
            }

            // Si aucune erreur n'a été trouvée (y compris la vérification FM)
            if (empty($error)) {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $validated = 'none';
                
                try {
                    if ($grade === 'Delegue' || $grade === 'Etudiant') {
                        $stmt = $pdo->prepare("INSERT INTO users (name, phone, grade, classroom, formation, niveau_id, filiere_id, password, validated) 
                                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$name, $phone, $grade, $classroom, $formation, $niveau_id, $filiere_id, $hashedPassword, $validated]);
                    } else {
                        // Pour les enseignants
                        $stmt = $pdo->prepare("INSERT INTO users (name, phone, grade, password, validated) 
                                              VALUES (?, ?, ?, ?, ?)");
                        $stmt->execute([$name, $phone, $grade, $hashedPassword, $validated]);
                    }
                    
                    if ($stmt->rowCount() > 0) {
                        // Si c'est un étudiant, connectez-le directement
                        if ($grade === 'Etudiant') {
                            $stmt = $pdo->prepare("SELECT * FROM users WHERE name = ? AND grade = ?");
                            $stmt->execute([$name, $grade]);
                            $user = $stmt->fetch();
                            
                            if ($user) {
                                $_SESSION['user_id'] = $user['id'];
                                $_SESSION['name'] = $user['name'];
                                $_SESSION['grade'] = $user['grade'];
                                $_SESSION['validated'] = $user['validated'];
                                $_SESSION['classroom'] = $user['classroom'];
                                
                                // Récupération des informations supplémentaires sur la salle
                                $stmt = $pdo->prepare("SELECT f.nom as filiere_nom 
                                                      FROM salles s 
                                                      JOIN filieres f ON s.filiere_id = f.id 
                                                      WHERE s.nom = ?");
                                $stmt->execute([$user['classroom']]);
                                $salle_info = $stmt->fetch();
                                
                                if ($salle_info) {
                                    $_SESSION['filiere'] = $salle_info['filiere_nom'];
                                }
                                
                                header('Location: dashEtudiant.php');
                                exit();
                            }
                        }
                        $success = 'Inscription réussie! Vous pouvez maintenant vous connecter.';
                    } else {
                        $error = 'Une erreur est survenue lors de l\'inscription';
                    }
                } catch (PDOException $e) {
                    $error = 'Erreur lors de l\'inscription: ' . $e->getMessage();
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription</title>
    <link rel="stylesheet" href="includes/style.css">
    <style>
        .delegate-fields {
            background-color: rgba(10, 10, 10, 0.3);
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 15px;
            border: 1px solid var(--violet-neon);
        }
    </style>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const gradeSelect = document.getElementById('grade');
        const delegateFields = document.getElementById('delegate-fields');
        const delegateRequiredFields = delegateFields.querySelectorAll('[required]');
        
        gradeSelect.addEventListener('change', function() {
            if (this.value === 'Delegue' || this.value === 'Etudiant') {
                delegateFields.style.display = 'block';
                delegateRequiredFields.forEach(field => {
                    field.required = true;
                });
            } else {
                delegateFields.style.display = 'none';
                delegateRequiredFields.forEach(field => {
                    field.required = false;
                });
            }
        });
        
        // Initialiser l'affichage
        if (gradeSelect.value === 'Delegue' || gradeSelect.value === 'Etudiant') {
            delegateFields.style.display = 'block';
            delegateRequiredFields.forEach(field => {
                field.required = true;
            });
        } else {
            delegateFields.style.display = 'none';
            delegateRequiredFields.forEach(field => {
                field.required = false;
            });
        }
    });
</script>
</head>
<body>
    <div class="auth-container">
        <h1>Inscription</h1>
        <?php if (!empty($error)): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        <?php if (!empty($success)): ?>
            <div class="success"><?php echo $success; ?></div>
        <?php endif; ?>
        <form action="register.php" method="POST">
            <div class="form-group">
                <label for="name">Nom complet</label>
                <input type="text" id="name" name="name" required>
            </div>
            <div class="form-group">
                <label for="phone">Numéro de téléphone</label>
                <input type="tel" id="phone" name="phone" required>
            </div>
            <div class="form-group">
                <label for="grade">Grade</label>
                <select id="grade" name="grade" required>
                    <option value="">Sélectionnez un grade</option>
                    <option value="Delegue">Délégué</option>
                    <option value="Enseignant">Enseignant</option>
                    <option value="Etudiant">Étudiant</option>
                </select>
            </div>
            
            <div id="delegate-fields" class="delegate-fields">
                <h3 style="color: var(--violet-neon); margin-top: 0; margin-bottom: 15px;">Informations de la classe</h3>
                
                <div class="form-group">
                    <label for="formation">Formation</label>
                    <select id="formation" name="formation" required>
                        <option value="">Sélectionnez une formation</option>
                        <option value="FA">FA (Formation Alternance)</option>
                        <option value="FI">FI (Formation Initiale)</option>
                        <option value="FM">FM (Etudiant Micrateur )</option>

                    </select>
                </div>
                
                <div class="form-group">
                    <label for="niveau_id">Niveau</label>
                    <select id="niveau_id" name="niveau_id" required>
                        <option value="">Sélectionnez un niveau</option>
                        <?php foreach ($niveaux as $niveau): ?>
                            <option value="<?php echo htmlspecialchars($niveau['id']); ?>">
                                <?php echo htmlspecialchars($niveau['nom']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="filiere_id">Filière</label>
                    <select id="filiere_id" name="filiere_id" required>
                        <option value="">Sélectionnez une filière</option>
                        <?php foreach ($filieres as $filiere): ?>
                            <option value="<?php echo htmlspecialchars($filiere['id']); ?>">
                                <?php echo htmlspecialchars($filiere['nom']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="classroom">Salle de classe</label>
                    <select id="classroom" name="classroom" required>
                        <option value="">Sélectionnez une salle</option>
                        <?php foreach ($salles as $salle): ?>
                            <option value="<?php echo htmlspecialchars($salle['nom']); ?>">
                                <?php echo htmlspecialchars($salle['nom']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <label for="password">Mot de passe</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <button type="submit" class="btn-submit">S'inscrire</button>
        </form>
        
        <p style="text-align: center; margin-top: 20px; color: var(--violet-neon);">
            Déjà inscrit? <a href="index.php" style="color: var(--blanc);">Se connecter</a>
        </p>
    </div>
</body>
</html>