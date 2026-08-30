<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

if (isLoggedIn()) {
    if (isStudent()) {
        header('Location: dashEtudiant.php');
    } else {
        header('Location: dashboard.php');
    }
    exit();
}

$error = '';
if (isset($_GET['error'])) {
    $error = $_GET['error'] === 'pending' ? 'Votre compte est en attente de validation' : '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $grade = trim($_POST['grade']);
    $password = trim($_POST['password']);
    
    if (empty($name) || empty($grade) || empty($password)) {
        $error = 'Tous les champs sont obligatoires';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE name = ? AND grade = ?");
        $stmt->execute([$name, $grade]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            
            // VERIFICATION COTE SERVEUR (laissée pour une implémentation serveur réelle)
            if ($grade === 'Delegue' || $grade === 'Etudiant') {
                $storedUser = getStoredUser(); 
                // La logique réelle de blocage est gérée par le JavaScript.
            }
            
            if (!empty($error)) {
                 // Si une erreur liée au verrouillage d'appareil était gérée par le PHP.
            } else {
                
                // Vérifier si l'utilisateur a une promotion temporaire active
                $stmt = $pdo->prepare("SELECT * FROM promotions_temporaires 
                                     WHERE etudiant_id = ? AND date_fin > NOW()");
                $stmt->execute([$user['id']]);
                $promotion_active = $stmt->fetch();
                
                if ($promotion_active && $user['grade'] === 'Etudiant') {
                    // Si promotion active, traiter comme un délégué
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['name'] = $user['name'];
                    $_SESSION['grade'] = 'Delegue'; // On override le grade
                    $_SESSION['validated'] = 'yes'; // On considère comme validé
                    $_SESSION['classroom'] = $user['classroom'];
                    $_SESSION['promotion_temporaire'] = true;
                    
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
                    
                    // Générer un identifiant unique de session pour cet utilisateur
                    $session_identifier = generateSessionIdentifier($user['id'], $user['name'], $user['grade']);
                    $_SESSION['session_identifier'] = $session_identifier;
                    
                    header('Location: dashboard.php');
                    exit();
                } else {
                    // Comportement normal
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['name'] = $user['name'];
                    $_SESSION['grade'] = $user['grade'];
                    $_SESSION['validated'] = $user['validated'];
                    
                    if ($user['grade'] === 'Delegue' || $user['grade'] === 'Etudiant') {
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
                    }
                    
                    // Générer un identifiant unique de session pour cet utilisateur
                    $session_identifier = generateSessionIdentifier($user['id'], $user['name'], $user['grade']);
                    $_SESSION['session_identifier'] = $session_identifier;
                    
                    // Gestion des redirections selon le grade et le statut de validation
                    if ($user['grade'] === 'Delegue') {
                        if ($user['validated'] === 'none') {
                            header('Location: validation.php');
                        } elseif ($user['validated'] === 'pending') {
                            session_destroy();
                            header('Location: index.php?error=pending');
                        } else {
                            header('Location: dashboard.php');
                        }
                    } elseif ($user['grade'] === 'Enseignant') {
                        if ($user['validated'] === 'none') {
                            header('Location: validation_enseignant_user.php');
                        } elseif ($user['validated'] === 'pending') {
                            session_destroy();
                            header('Location: index.php?error=pending');
                        } else {
                            header('Location: dashboard.php');
                        }
                    } elseif ($user['grade'] === 'Etudiant') {
                        header('Location: dashEtudiant.php');
                    }
                    exit();
                }
            }
        } else {
            $error = 'Identifiants incorrects';
        }
    }
}

// Fonction pour récupérer l'utilisateur stocké
function getStoredUser() {
    // Cette fonction sera principalement utilisée côté JavaScript
    return null;
}

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion</title>
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
        
        body {
            background: linear-gradient(135deg, var(--violet-dark), var(--noir));
            color: var(--blanc);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        
        .auth-container {
            background: rgba(10, 10, 10, 0.8);
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.3);
            width: 100%;
            max-width: 400px;
            border: 1px solid var(--violet-medium);
        }
        
        h1 {
            text-align: center;
            margin-bottom: 1.5rem;
            color: var(--violet-neon);
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--violet-neon);
        }
        
        input, select {
            width: 100%;
            padding: 0.75rem;
            border-radius: 4px;
            border: 1px solid var(--violet-medium);
            background: rgba(10, 10, 10, 0.5);
            color: var(--blanc);
        }
        
        input:focus, select:focus {
            outline: none;
            border-color: var(--violet-neon);
        }
        
        button {
            width: 100%;
            padding: 0.75rem;
            background: var(--violet-light);
            color: var(--blanc);
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            transition: all 0.3s ease;
        }
        
        button:hover {
            background: var(--violet-neon);
            transform: translateY(-2px);
        }
        
        .error {
            color: #ff6b6b;
            margin-bottom: 1rem;
            padding: 0.75rem;
            background: rgba(255, 0, 0, 0.1);
            border-radius: 4px;
            border: 1px solid #ff6b6b;
            text-align: center;
        }
        
        .success {
            color: #4CAF50;
            margin-bottom: 1rem;
            padding: 0.75rem;
            background: rgba(76, 175, 80, 0.1);
            border-radius: 4px;
            border: 1px solid #4CAF50;
            text-align: center;
        }
        
        .device-info {
            color: var(--violet-neon);
            margin-top: 1rem;
            padding: 0.75rem;
            background: rgba(179, 136, 255, 0.1);
            border-radius: 4px;
            border: 1px solid var(--violet-neon);
            text-align: center;
            font-size: 0.9rem;
        }
        
        .unlock-button {
            background: transparent !important;
            border: 1px solid var(--violet-neon) !important;
            width: auto !important;
            padding: 0.5rem 1rem !important;
            font-size: 0.8rem !important;
            margin-top: 10px;
        }
        
        .unlock-button:hover {
            background: rgba(179, 136, 255, 0.2) !important;
        }
        
        @media (max-width: 480px) {
            .auth-container {
                margin: 1rem;
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <h1>Connexion</h1>
        <?php if (!empty($error)): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        <?php if (isset($_GET['success']) && $_GET['success'] === 'registered'): ?>
            <div class="success">Inscription réussie! Vous pouvez maintenant vous connecter.</div>
        <?php endif; ?>
        
        <div id="deviceLockInfo" class="device-info" style="display: none;">
            </div>
        
        <form action="index.php" method="POST" id="loginForm">
            <div class="form-group">
                <label for="name">Nom</label>
                <input type="text" id="name" name="name" required>
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
            <div class="form-group">
                <label for="password">Mot de passe</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit">Se connecter</button>
        </form>
        
          <div id="unlockButtonContainer" style="text-align: center; margin-top: 20px; display: none;">
            <button type="button" onclick="resetDeviceLock()" class="unlock-button">
                Déverrouiller cet appareil
            </button>
        </div>
        

  
    </div>

    <script>
        // Gestion du verrouillage d'appareil
        class DeviceLockManager {
            constructor() {
                this.storageKey = 'device_locked_user';
                this.init();
            }

            init() {
                this.displayLockInfo();
                this.setupFormValidation();
                this.setupGradeListener();
                this.checkUnlockButtonVisibility();
            }

            // Afficher les informations de verrouillage
            displayLockInfo() {
                const storedUser = this.getStoredUser();
                const infoElement = document.getElementById('deviceLockInfo');
                
                if (storedUser) {
                    // S'assurer que les infos ne s'affichent que si l'utilisateur stocké est restreint
                    if (this.isStoredUserRestricted()) {
                        infoElement.innerHTML = `
                            <strong>Appareil verrouillé sur :</strong><br>
                            Nom: ${storedUser.name} | Grade: ${storedUser.grade}<br>
                            <small>Cet appareil ne peut se connecter qu'avec ce compte</small>
                        `;
                        infoElement.style.display = 'block';
                        
                        // NOTE: Suppression du pré-remplissage pour éviter les conflits lors de la saisie d'un compte Enseignant
                        // document.getElementById('name').value = storedUser.name;
                        // document.getElementById('grade').value = storedUser.grade;
                    } else {
                         // Si un enseignant est stocké, on masque
                        infoElement.style.display = 'none';
                    }
                } else {
                    infoElement.style.display = 'none';
                }
            }

            // Vérifier si l'appareil est verrouillé
            isDeviceLocked() {
                // L'appareil est considéré verrouillé uniquement si l'utilisateur stocké est Delegue ou Etudiant
                const storedUser = this.getStoredUser();
                return storedUser && this.isStoredUserRestricted();
            }

            // Récupérer l'utilisateur stocké
            getStoredUser() {
                try {
                    const stored = localStorage.getItem(this.storageKey);
                    return stored ? JSON.parse(stored) : null;
                } catch (e) {
                    return null;
                }
            }

            // Stocker l'utilisateur (première connexion) - uniquement pour délégués et étudiants
            storeUser(name, grade, password) {
                // MODIFICATION: Ne stocker que pour les délégués et étudiants
                if (grade === 'Delegue' || grade === 'Etudiant') {
                    const userData = {
                        name: name,
                        grade: grade,
                        password: password, 
                        timestamp: new Date().toISOString(),
                        deviceId: this.generateDeviceId()
                    };
                    
                    localStorage.setItem(this.storageKey, JSON.stringify(userData));
                    this.displayLockInfo();
                    this.checkUnlockButtonVisibility();
                }
                // Si 'Enseignant', ne rien stocker/modifier pour ne pas créer de verrouillage
            }

            // Vérifier les identifiants
            checkCredentials(name, grade, password) {
                const storedUser = this.getStoredUser();
                
                if (!storedUser || !this.isStoredUserRestricted()) {
                    return true; 
                }
                
                if (grade === 'Enseignant') {
                    return true; 
                }
                
                // Uniquement pour délégués et étudiants:
                if (storedUser.name !== name || storedUser.grade !== grade) {
                    return false;
                }
                
                if (storedUser.password !== password) {
                    return false;
                }
                
                return true;
            }

            // Déverrouiller l'appareil
            unlockDevice() {
                localStorage.removeItem(this.storageKey);
                this.displayLockInfo();
                this.checkUnlockButtonVisibility();
                alert('Appareil déverrouillé ! Vous pouvez maintenant vous connecter avec un autre compte.');
                
                // Réinitialiser le formulaire
                document.getElementById('loginForm').reset();
            }

            // Générer un ID d'appareil simple
            generateDeviceId() {
                let deviceId = localStorage.getItem('device_id');
                if (!deviceId) {
                    deviceId = 'device_' + Math.random().toString(36).substr(2, 9);
                    localStorage.setItem('device_id', deviceId);
                }
                return deviceId;
            }

            // Vérifier si le grade stocké est restreint
            isStoredUserRestricted() {
                const storedUser = this.getStoredUser();
                return storedUser && (storedUser.grade === 'Delegue' || storedUser.grade === 'Etudiant');
            }

            // Vérifier et gérer la visibilité du bouton de déverrouillage
            checkUnlockButtonVisibility() {
                const unlockButtonContainer = document.getElementById('unlockButtonContainer');
                
                // Afficher le bouton uniquement si un utilisateur restreint est stocké.
                if (this.isStoredUserRestricted()) {
                    unlockButtonContainer.style.display = 'block';
                } else {
                    unlockButtonContainer.style.display = 'none';
                }
            }

            // Configurer l'écouteur pour les changements de grade
            setupGradeListener() {
                const gradeSelect = document.getElementById('grade');
                gradeSelect.addEventListener('change', () => {
                    this.checkUnlockButtonVisibility();
                });
            }

            // Configurer la validation du formulaire
            setupFormValidation() {
                const form = document.getElementById('loginForm');
                
                form.addEventListener('submit', (e) => {
                    const name = document.getElementById('name').value;
                    const grade = document.getElementById('grade').value;
                    const password = document.getElementById('password').value;
                    
                    // NOUVELLE VÉRIFICATION CLÉ: Si l'utilisateur est Enseignant, on ignore TOUTES les vérifications de verrouillage client-side.
                    if (grade === 'Enseignant') {
                        return true; 
                    }
                    
                    // Logique de verrouillage (Uniquement pour Délégué ou Étudiant)
                    if (this.isDeviceLocked()) {
                        const storedUser = this.getStoredUser();
                        
                        // Si l'utilisateur tente de se connecter avec un autre compte
                        if (storedUser.name !== name || storedUser.grade !== grade) {
                            e.preventDefault();
                            alert(`Cet appareil est verrouillé sur le compte: ${storedUser.name} (${storedUser.grade}). Vous ne pouvez pas vous connecter avec un autre compte. Utilisez le bouton "Déverrouiller cet appareil" pour changer.`);
                            return false;
                        }
                        
                        // Si c'est le même compte mais le mot de passe est incorrect
                        if (!this.checkCredentials(name, grade, password)) {
                            e.preventDefault();
                            alert('Mot de passe incorrect pour le compte verrouillé sur cet appareil.');
                            return false;
                        }
                    }
                    
                    // Si tout est bon, stocker les informations (première connexion ou connexion normale)
                    // Uniquement pour les délégués et étudiants
                    if (!this.isDeviceLocked() && (grade === 'Delegue' || grade === 'Etudiant')) {
                        this.storeUser(name, grade, password);
                    }
                    
                    return true;
                });
            }
        }

        // Initialiser le gestionnaire de verrouillage
        const deviceLock = new DeviceLockManager();

        // Fonction pour déverrouiller l'appareil
        function resetDeviceLock() {
            if (confirm('Êtes-vous sûr de vouloir déverrouiller cet appareil ? Vous pourrez alors vous connecter avec un autre compte.')) {
                deviceLock.unlockDevice();
            }
        }

        // Vérifier au chargement si l'appareil est verrouillé
        document.addEventListener('DOMContentLoaded', function() {
            const storedUser = deviceLock.getStoredUser();
            if (storedUser) {
                console.log('Appareil verrouillé sur:', storedUser.name);
            }
            
            // Vérifier la visibilité du bouton au chargement
            deviceLock.checkUnlockButtonVisibility();
        });

        // Écouter les changements dans le formulaire pour mettre à jour la visibilité du bouton
        document.getElementById('name').addEventListener('input', function() {
            deviceLock.checkUnlockButtonVisibility();
        });
    </script>
</body>
</html>