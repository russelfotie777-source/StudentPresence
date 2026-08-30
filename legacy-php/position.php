<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Vérification de la connexion et que l'utilisateur est délégué
if (!isLoggedIn() || !isDelegate()) {
    header('Location: index.php');
    exit();
}

// Vérification du paramètre seance_id
if (!isset($_GET['seance_id']) || empty($_GET['seance_id'])) {
    $_SESSION['error'] = "Séance non spécifiée.";
    header('Location: dashboard.php');
    exit();
}

$seance_id = intval($_GET['seance_id']);

// Vérifier que le délégué a le droit d'accéder à cette séance
$query = "SELECT s.*, sl.nom as salle_nom 
          FROM seances s 
          JOIN salles sl ON s.salle_id = sl.id 
          WHERE s.id = ? AND sl.nom = ?";
$stmt = $pdo->prepare($query);
$stmt->execute([$seance_id, $_SESSION['classroom']]);
$seance = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$seance) {
    $_SESSION['error'] = "Séance non trouvée ou vous n'avez pas accès à cette séance.";
    header('Location: dashboard.php');
    exit();
}

// Traitement du formulaire de position
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['latitude']) && isset($_POST['longitude'])) {
        $latitude = floatval($_POST['latitude']);
        $longitude = floatval($_POST['longitude']);
        
        // Vérifier si une position existe déjà pour cette séance
        $query = "SELECT id FROM positions_seances WHERE seance_id = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$seance_id]);
        $existing_position = $stmt->fetch();
        
        if ($existing_position) {
            // Mettre à jour la position existante
            $query = "UPDATE positions_seances 
                      SET latitude = ?, longitude = ?, date_creation = NOW() 
                      WHERE seance_id = ?";
            $stmt = $pdo->prepare($query);
            $stmt->execute([$latitude, $longitude, $seance_id]);
        } else {
            // Insérer une nouvelle position
            $query = "INSERT INTO positions_seances (seance_id, delegue_id, latitude, longitude) 
                      VALUES (?, ?, ?, ?)";
            $stmt = $pdo->prepare($query);
            $stmt->execute([$seance_id, $_SESSION['user_id'], $latitude, $longitude]);
        }
        
        $_SESSION['message'] = "Position enregistrée avec succès pour cette séance!";
        header('Location: dashboard.php');
        exit();
    } else {
        $error = "Impossible de récupérer votre position.";
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enregistrer la Position</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --violet-dark: #2a0a42;
            --violet-medium: #4b2a70;
            --violet-light: #7b4b9e;
            --violet-neon: #b388ff;
            --white: #ffffff;
            --gray-light: #f0f0f0;
            --black: #0a0a0a;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, var(--violet-dark), var(--black));
            color: var(--white);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        
        .container {
            max-width: 600px;
            width: 100%;
            background: rgba(10, 10, 10, 0.8);
            border-radius: 8px;
            padding: 2rem;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.3);
            border: 1px solid var(--violet-medium);
        }
        
        .header {
            text-align: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--violet-medium);
        }
        
        .header h1 {
            color: var(--violet-neon);
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        
        .seance-info {
            background: rgba(74, 20, 140, 0.3);
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 2rem;
            border-left: 3px solid var(--violet-neon);
        }
        
        .position-info {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .coordinates {
            display: flex;
            justify-content: space-around;
            margin: 1rem 0;
        }
        
        .coordinate {
            background: rgba(123, 75, 158, 0.2);
            padding: 1rem;
            border-radius: 4px;
            flex: 1;
            margin: 0 0.5rem;
        }
        
        .coordinate strong {
            color: var(--violet-neon);
            display: block;
            margin-bottom: 0.5rem;
        }
        
        .btn {
            padding: 1rem 2rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
            font-size: 1.1rem;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            width: 100%;
            margin-bottom: 1rem;
        }
        
        .btn-primary {
            background-color: var(--violet-neon);
            color: white;
        }
        
        .btn-primary:hover {
            background-color: var(--violet-light);
            transform: translateY(-2px);
        }
        
        .btn-secondary {
            background-color: #666;
            color: #ccc;
        }
        
        .btn-secondary:hover {
            background-color: #777;
        }
        
        .message {
            padding: 1rem;
            margin: 1rem 0;
            border-radius: 4px;
            text-align: center;
        }
        
        .success {
            background-color: rgba(76, 175, 80, 0.2);
            border: 1px solid #4caf50;
            color: #a5d6a7;
        }
        
        .error {
            background-color: rgba(244, 67, 54, 0.2);
            border: 1px solid #f44336;
            color: #ef9a9a;
        }
        
        .loading {
            display: none;
            text-align: center;
            margin: 1rem 0;
        }
        
        .spinner {
            border: 4px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top: 4px solid var(--violet-neon);
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-map-marker-alt"></i> Enregistrer la Position</h1>
            <p>Pour la séance du <?php echo date('d/m/Y'); ?></p>
        </div>
        
        <?php if (isset($error)): ?>
            <div class="message error">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <div class="seance-info">
            <h3>Informations de la séance</h3>
            <p><strong>Salle:</strong> <?php echo htmlspecialchars($seance['salle_nom']); ?></p>
            <p><strong>Heure:</strong> <?php echo htmlspecialchars($seance['heure_debut'] . ' - ' . $seance['heure_fin']); ?></p>
        </div>
        
        <div class="position-info">
            <p>Cliquez sur le bouton ci-dessous pour enregistrer votre position actuelle.</p>
            <p><small>Cette position sera utilisée pour vérifier la présence des étudiants.</small></p>
            
            <div class="coordinates">
                <div class="coordinate">
                    <strong>Latitude</strong>
                    <span id="latitude">-</span>
                </div>
                <div class="coordinate">
                    <strong>Longitude</strong>
                    <span id="longitude">-</span>
                </div>
            </div>
            
            <div class="loading" id="loading">
                <div class="spinner"></div>
                <p>Récupération de votre position...</p>
            </div>
        </div>
        
        <form method="POST" id="positionForm">
            <input type="hidden" name="latitude" id="inputLatitude">
            <input type="hidden" name="longitude" id="inputLongitude">
            
            <button type="button" class="btn btn-primary" onclick="getLocation()">
                <i class="fas fa-location-arrow"></i> Obtenir ma position
            </button>
            
            <button type="submit" class="btn btn-secondary" id="submitBtn" disabled>
                <i class="fas fa-save"></i> Enregistrer la position
            </button>
        </form>
        
        <a href="dashboard.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Retour au tableau de bord
        </a>
    </div>

    <script>
        function getLocation() {
            const loading = document.getElementById('loading');
            const latitudeSpan = document.getElementById('latitude');
            const longitudeSpan = document.getElementById('longitude');
            const inputLatitude = document.getElementById('inputLatitude');
            const inputLongitude = document.getElementById('inputLongitude');
            const submitBtn = document.getElementById('submitBtn');
            
            loading.style.display = 'block';
            
            if (navigator.geolocation) {
                // Modification des options de géolocalisation pour une meilleure précision et fiabilité
                navigator.geolocation.getCurrentPosition(
                    function(position) {
                        const lat = position.coords.latitude;
                        const lng = position.coords.longitude;
                        
                        latitudeSpan.textContent = lat.toFixed(6);
                        longitudeSpan.textContent = lng.toFixed(6);
                        inputLatitude.value = lat;
                        inputLongitude.value = lng;
                        
                        loading.style.display = 'none';
                        submitBtn.disabled = false;
                        
                        alert('Position récupérée avec succès! Cliquez sur "Enregistrer la position" pour sauvegarder.');
                    },
                    function(error) {
                        loading.style.display = 'none';
                        let errorMessage = "Erreur lors de la récupération de la position: ";
                        
                        switch(error.code) {
                            case error.PERMISSION_DENIED:
                                errorMessage = "L'accès à la position a été refusé. Veuillez l'autoriser dans les paramètres de votre navigateur/système pour ce site.";
                                break;
                            case error.POSITION_UNAVAILABLE:
                                errorMessage += "Position indisponible. Veuillez vous assurer que le GPS est activé et réessayer.";
                                break;
                            case error.TIMEOUT:
                                errorMessage += "Délai de récupération dépassé. Veuillez vérifier votre connexion ou réessayer.";
                                break;
                            default:
                                errorMessage += "Erreur inconnue (" + error.code + ").";
                        }
                        
                        alert(errorMessage);
                        submitBtn.disabled = true; // Désactiver l'enregistrement en cas d'erreur
                    },
                    {
                        enableHighAccuracy: true, // Maintien de la haute précision
                        // Timeout retiré (comme demandé)
                        maximumAge: 0 // Force l'acquisition d'une nouvelle position, pas de cache
                    }
                );
            } else {
                loading.style.display = 'none';
                alert("La géolocalisation n'est pas supportée par votre navigateur.");
                submitBtn.disabled = true;
            }
        }
        
        // Demander automatiquement la position au chargement de la page
        window.onload = function() {
            getLocation();
        };
    </script>
</body>
</html>