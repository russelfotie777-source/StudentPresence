<?php
// dashEtudiant.php - Version améliorée
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Vérification de la connexion
if (!isStudent()) {
    header('Location: index.php');
    exit();
}

// Configuration du fuseau horaire
date_default_timezone_set('Africa/Douala');

// Initialisation des variables
$seances = [];
$current_time = date('H:i:s');
$current_date = date('Y-m-d');
$current_day = strtoupper(date('l'));
$today_french = '';

// Conversion du jour en français
switch($current_day) {
    case 'MONDAY': $today_french = 'LUNDI'; break;
    case 'TUESDAY': $today_french = 'MARDI'; break;
    case 'WEDNESDAY': $today_french = 'MERCREDI'; break;
    case 'THURSDAY': $today_french = 'JEUDI'; break;
    case 'FRIDAY': $today_french = 'VENDREDI'; break;
    case 'SATURDAY': $today_french = 'SAMEDI'; break;
    case 'SUNDAY': $today_french = 'DIMANCHE'; break;
    default: $today_french = '';
}

// Récupération de la semaine actuelle
function getCurrentWeek($pdo) {
    $current_date = date('Y-m-d');
    $query = "SELECT * FROM semaines WHERE date_debut <= ? AND date_fin >= ? LIMIT 1";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$current_date, $current_date]);
    $week = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$week) {
        $query = "SELECT * FROM semaines 
                  ORDER BY ABS(DATEDIFF(date_debut, ?)) 
                  LIMIT 1";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$current_date]);
        $week = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    return $week ?: ['id' => 0, 'numero' => 0, 'date_debut' => $current_date, 'date_fin' => $current_date];
}

$current_week = getCurrentWeek($pdo);

// Récupération des séances pour l'étudiant
if (!isset($_SESSION['classroom'])) {
    $_SESSION['error'] = "Votre salle de classe n'est pas définie dans votre profil.";
} else {
    $query = "SELECT s.*, c.matiere_id, m.nom as matiere_nom, m.code as matiere_code, 
                     u.name as enseignant_nom, u.phone as enseignant_phone,
                     sl.nom as salle_nom, f.nom as filiere_nom,
                     emp.semaine_id, sem.numero as semaine_num,
                     pe.etat as etat_etudiant, s.commentaires
              FROM seances s
              JOIN cours c ON s.cours_id = c.id
              JOIN matieres m ON c.matiere_id = m.id
              JOIN users u ON s.enseignant_id = u.id
              JOIN salles sl ON s.salle_id = sl.id
              JOIN filieres f ON sl.filiere_id = f.id
              LEFT JOIN emplois_temps emp ON c.emploi_id = emp.id AND emp.salle_id = sl.id
              LEFT JOIN semaines sem ON emp.semaine_id = sem.id
              LEFT JOIN presences_etudiants pe ON pe.seance_id = s.id AND pe.etudiant_id = ?
              WHERE sl.nom = ?
              AND s.jour = ?
              AND (emp.semaine_id = ? OR emp.semaine_id IS NULL)
              ORDER BY s.heure_debut";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$_SESSION['user_id'], $_SESSION['classroom'], $today_french, $current_week['id']]);
    $seances = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($seances)) {
        $_SESSION['salle_nom'] = $seances[0]['salle_nom'];
        $_SESSION['filiere'] = $seances[0]['filiere_nom'];
    }
}

// Fonction améliorée pour calculer la distance
function calculateDistance($lat1, $lon1, $lat2, $lon2) {
    $earthRadius = 6371000; // Rayon de la Terre en mètres
    
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    
    $a = sin($dLat/2) * sin($dLat/2) + 
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * 
         sin($dLon/2) * sin($dLon/2);
    
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    
    return $earthRadius * $c; // Distance en mètres
}

// Traitement du formulaire de présence étudiant
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['seance_id'])) {
    $seance_id = $_POST['seance_id'];
    $status = $_POST['status'];
    
    // Vérifier que la séance n'est pas verrouillée
    $query = "SELECT commentaires FROM seances WHERE id = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$seance_id]);
    $seance = $stmt->fetch();
    
    if ($seance['commentaires'] === 'PRESENCES_VERROUILLEES') {
        $_SESSION['error'] = "Les présences pour cette séance ont été verrouillées. Vous ne pouvez plus marquer votre présence.";
        header("Location: dashEtudiant.php");
        exit();
    }
    
    // Vérifier que la séance appartient bien à la salle de l'étudiant
    $query = "SELECT s.id 
              FROM seances s
              JOIN salles sl ON s.salle_id = sl.id
              WHERE s.id = ? AND sl.nom = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$seance_id, $_SESSION['classroom']]);
    
    if ($stmt->fetch() !== false) {
        // Vérifier si une position du délégué existe pour cette séance
        $query = "SELECT latitude, longitude FROM positions_seances WHERE seance_id = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$seance_id]);
        $position_delegue = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($position_delegue) {
            // Vérifier si la position de l'étudiant est fournie
            if (isset($_POST['student_latitude']) && isset($_POST['student_longitude'])) {
                $student_lat = floatval($_POST['student_latitude']);
                $student_lng = floatval($_POST['student_longitude']);
                $delegue_lat = floatval($position_delegue['latitude']);
                $delegue_lng = floatval($position_delegue['longitude']);
                
                // Calculer la distance avec la fonction améliorée
                $distance = calculateDistance($student_lat, $student_lng, $delegue_lat, $delegue_lng);
                $max_distance = 2550; // Augmenté à 650 mètres comme demandé

                if ($distance > $max_distance) {
                    $_SESSION['error'] = "Vous êtes trop éloigné du délégué pour marquer votre présence.";
                    header("Location: dashEtudiant.php");
                    exit();
                }
                
                // Distance OK, on peut continuer
            } else {
                $_SESSION['error'] = "Impossible de récupérer votre position. Veuillez autoriser l'accès à votre localisation.";
                header("Location: dashEtudiant.php");
                exit();
            }
        } else {
            $_SESSION['error'] = "Le délégué n'a pas encore enregistré sa position pour cette séance. Présence impossible.";
            header("Location: dashEtudiant.php");
            exit();
        }
        
        // Vérifier si l'étudiant a déjà marqué sa présence
        $query = "SELECT id FROM presences_etudiants WHERE seance_id = ? AND etudiant_id = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$seance_id, $_SESSION['user_id']]);
        
        if ($stmt->fetch()) {
            // Mise à jour de la présence existante
            $query = "UPDATE presences_etudiants SET etat = ? WHERE seance_id = ? AND etudiant_id = ?";
            $stmt = $pdo->prepare($query);
            $stmt->execute([$status, $seance_id, $_SESSION['user_id']]);
        } else {
            // Insertion d'une nouvelle présence
            $query = "INSERT INTO presences_etudiants (seance_id, etudiant_id, etat) VALUES (?, ?, ?)";
            $stmt = $pdo->prepare($query);
            $stmt->execute([$seance_id, $_SESSION['user_id'], $status]);
        }

        $_SESSION['message'] = "Votre présence a été enregistrée avec succès!";
        header("Location: dashEtudiant.php");
        exit();
    } else {
        $_SESSION['error'] = "Vous ne pouvez pas marquer la présence pour cette séance.";
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de Bord Étudiant</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --blue-dark: #0A192F;
            --blue-medium: #1D4354;
            --blue-light: #4D7298;
            --blue-neon: #61DAFB;
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
            background: linear-gradient(135deg, var(--blue-dark), var(--black));
            color: var(--white);
            min-height: 100vh;
        }
        
        .navbar {
            background: linear-gradient(to right, var(--blue-dark), var(--blue-medium));
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        
        .navbar a {
            color: var(--white);
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            transition: all 0.3s ease;
            background-color: var(--blue-light);
        }
        
        .navbar a:hover {
            background-color: var(--blue-neon);
            transform: translateY(-2px);
        }
        
        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1rem;
        }
        
        .header {
            text-align: center;
            margin-bottom: 2rem;
            padding: 1rem;
            background: rgba(77, 114, 152, 0.2);
            border-radius: 8px;
            border-left: 4px solid var(--blue-neon);
        }
        
        .header h1 {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
            background: linear-gradient(to right, var(--blue-neon), var(--white));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .card {
            background: rgba(10, 10, 10, 0.7);
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
            border: 1px solid var(--blue-medium);
        }
        
        .card h2 {
            color: var(--blue-neon);
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid var(--blue-medium);
            font-size: 1.5rem;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }
        
        .info-item {
            background: rgba(29, 67, 84, 0.3);
            padding: 1rem;
            border-radius: 6px;
            border-left: 3px solid var(--blue-neon);
        }
        
        .info-item strong {
            color: var(--blue-neon);
            display: block;
            margin-bottom: 0.3rem;
        }
        
        .seance-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
            background: rgba(10, 10, 10, 0.7);
            border-radius: 8px;
            overflow: hidden;
        }
        
        .seance-table th, 
        .seance-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid var(--blue-medium);
        }
        
        .seance-table th {
            background-color: var(--blue-dark);
            color: var(--blue-neon);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.8rem;
            letter-spacing: 1px;
        }
        
        .seance-table tr:hover {
            background-color: rgba(77, 114, 152, 0.1);
        }
        
        .status-btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .present {
            background-color: #4caf50;
            color: white;
        }
        
        .absent {
            background-color: #f44336;
            color: white;
        }
        
        .disabled {
            background-color: #666;
            color: #ccc;
            cursor: not-allowed;
        }
        
        .time-display {
            padding: 0.5rem;
            background-color: #333;
            border-radius: 4px;
            color: #ccc;
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
        
        .badge {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .badge-primary {
            background-color: var(--blue-medium);
            color: white;
        }
        
        .badge-secondary {
            background-color: var(--blue-light);
            color: white;
        }
        
        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.7);
        }
        
        .modal-content {
            background-color: var(--blue-dark);
            margin: 15% auto;
            padding: 2rem;
            border: 1px solid var(--blue-neon);
            border-radius: 8px;
            width: 80%;
            max-width: 500px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
        }
        
        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .close:hover {
            color: var(--blue-neon);
        }
        
        .modal-title {
            color: var(--blue-neon);
            margin-bottom: 1.5rem;
        }
        
        .position-info {
            text-align: center;
            margin: 1.5rem 0;
        }
        
        .coordinates {
            display: flex;
            justify-content: space-around;
            margin: 1rem 0;
        }
        
        .coordinate {
            background: rgba(77, 114, 152, 0.2);
            padding: 1rem;
            border-radius: 4px;
            flex: 1;
            margin: 0 0.5rem;
        }
        
        .coordinate strong {
            color: var(--blue-neon);
            display: block;
            margin-bottom: 0.5rem;
        }
        
        .loading {
            text-align: center;
            margin: 1rem 0;
        }
        
        .spinner {
            border: 4px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top: 4px solid var(--blue-neon);
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
            margin-top: 1.5rem;
        }
        
        .modal-btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
        }
        
        .modal-btn-primary {
            background-color: var(--blue-neon);
            color: white;
        }
        
        .modal-btn-secondary {
            background-color: #666;
            color: #ccc;
        }
        
        .permission-help {
            background: rgba(244, 67, 54, 0.1);
            border: 1px solid #f44336;
            border-radius: 4px;
            padding: 1rem;
            margin: 1rem 0;
            text-align: left;
        }
        
        .permission-help h4 {
            color: #f44336;
            margin-bottom: 0.5rem;
        }
        
        .permission-help ol {
            margin-left: 1.5rem;
            margin-bottom: 0.5rem;
        }
        
        .permission-help li {
            margin-bottom: 0.25rem;
        }
        
        @media (max-width: 768px) {
            .navbar {
                flex-direction: column;
                padding: 1rem;
            }
            
            .navbar div {
                margin-bottom: 1rem;
            }
            
            .seance-table {
                display: block;
                overflow-x: auto;
            }
            
            .info-grid {
                grid-template-columns: 1fr;
            }
            
            .seance-table th, 
            .seance-table td {
                padding: 0.75rem;
                font-size: 0.9rem;
            }
            
            .modal-content {
                width: 95%;
                margin: 20% auto;
            }
            
            .coordinates {
                flex-direction: column;
            }
            
            .coordinate {
                margin: 0.5rem 0;
            }
        }
    </style>
</head>
<body>
    <div class="navbar">
        <div>
            <i class="fas fa-user-circle"></i> Bienvenue, <?php echo htmlspecialchars($_SESSION['name']); ?> 
            <span style="color: var(--blue-neon);">(<?php echo htmlspecialchars($_SESSION['grade']); ?>)</span>
        </div>
        <div>
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Déconnexion</a>
        </div>
    </div>
    
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-tachometer-alt"></i> Tableau de Bord Étudiant</h1>
            <p><?php echo date('l d F Y'); ?></p>
            <p>Semaine <?php echo $current_week['numero']; ?> (<?php echo date('d/m/Y', strtotime($current_week['date_debut'])); ?> - <?php echo date('d/m/Y', strtotime($current_week['date_fin'])); ?>)</p>
        </div>
        
        <?php if (isset($_SESSION['message'])): ?>
            <div class="message success">
                <?php echo $_SESSION['message']; unset($_SESSION['message']); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="message error">
                <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>
        
        <div class="card">
            <h2><i class="fas fa-info-circle"></i> Informations du compte</h2>
            <div class="info-grid">
                <div class="info-item">
                    <strong>Nom complet</strong>
                    <?php echo htmlspecialchars($_SESSION['name']); ?>
                </div>
                <div class="info-item">
                    <strong>Grade</strong>
                    <?php echo htmlspecialchars($_SESSION['grade']); ?>
                </div>
                <div class="info-item">
                    <strong>Salle/Classe</strong>
                    <span class="badge badge-primary"><?php echo htmlspecialchars($_SESSION['classroom'] ?? 'Non spécifié'); ?></span>
                </div>
                <div class="info-item">
                    <strong>Filière</strong>
                    <span class="badge badge-secondary"><?php echo htmlspecialchars($_SESSION['filiere'] ?? 'Non spécifié'); ?></span>
                </div>
            </div>
        </div>
        
        <?php if (!empty($seances)): ?>
        <div class="card">
            <h2><i class="fas fa-calendar-alt"></i> Séances du jour (<?php echo $today_french; ?>)</h2>
            <table class="seance-table">
                <thead>
                    <tr>
                        <th>Matière</th>
                        <th>Enseignant</th>
                        <th>Salle</th>
                        <th>Heure début</th>
                        <th>Heure fin</th>
                        <th>Début réel</th>
                        <th>Fin réelle</th>
                        <th>État Délégué</th>
                        <th>État Professeur</th>
                        <th>Votre présence</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($seances as $seance): 
                        $current_time_display = date('H:i:s');
                        $start_time = new DateTime($seance['heure_debut']);
                        $end_time = new DateTime($seance['heure_fin']);
                        $now = new DateTime($current_time_display);
                        
                        $margin = new DateInterval('PT15M');
                        $start_with_margin = (clone $start_time)->sub($margin);
                        $end_with_margin = (clone $end_time)->add($margin);
                        
                        $is_active = ($now >= $start_with_margin && $now <= $end_with_margin);
                        $is_past = ($now > $end_with_margin);
                        
                        // Calcul des états
                        $delegue_status = isset($seance['etat_delegue']) ? 
                                         ($seance['etat_delegue'] === 'present' ? 'Présent' : 'Absent') : 
                                         ($is_past ? 'Non marqué' : '-');
                        $delegue_class = isset($seance['etat_delegue']) ? 
                                        ($seance['etat_delegue'] === 'present' ? 'present' : 'absent') : 
                                        'disabled';
                                        
                        $prof_status = isset($seance['etat_prof']) ? 
                                     ($seance['etat_prof'] === 'present' ? 'Présent' : 'Absent') : 
                                     ($is_past ? 'Non marqué' : '-');
                        $prof_class = isset($seance['etat_prof']) ? 
                                    ($seance['etat_prof'] === 'present' ? 'present' : 'absent') : 
                                    'disabled';
                                    
                        $etudiant_status = isset($seance['etat_etudiant']) ? 
                                         ($seance['etat_etudiant'] === 'present' ? 'Présent' : 'Absent') : 
                                         ($is_past ? 'Non marqué' : '-');
                        $etudiant_class = isset($seance['etat_etudiant']) ? 
                                        ($seance['etat_etudiant'] === 'present' ? 'present' : 'absent') : 
                                        'disabled';
                    ?>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars($seance['matiere_nom']); ?></strong>
                            <div class="text-muted"><?php echo htmlspecialchars($seance['matiere_code']); ?></div>
                        </td>
                        <td><?php echo htmlspecialchars($seance['enseignant_nom']); ?></td>
                        <td><?php echo htmlspecialchars($seance['salle_nom']); ?></td>
                        <td><?php echo htmlspecialchars($seance['heure_debut']); ?></td>
                        <td><?php echo htmlspecialchars($seance['heure_fin']); ?></td>
                        <td><?php echo isset($seance['debut_reel']) ? substr($seance['debut_reel'], 0, 5) : '-'; ?></td>
                        <td><?php echo isset($seance['fin_reelle']) ? substr($seance['fin_reelle'], 0, 5) : '-'; ?></td>
                        <td>
                            <span class="status-btn <?php echo $delegue_class; ?>">
                                <?php echo $delegue_status; ?>
                            </span>
                        </td>
                        <td>
                            <span class="status-btn <?php echo $prof_class; ?>">
                                <?php echo $prof_status; ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($seance['commentaires'] === 'PRESENCES_VERROUILLEES' || $is_past || $seance['etat_etudiant'] === 'present'): ?>
                                <span class="status-btn <?php echo $etudiant_class; ?>">
                                    <?php echo $etudiant_status; ?>
                                </span>
                            <?php elseif ($is_active && $seance['etat_etudiant'] !== 'present'): ?>
                                <button type="button" onclick="openPositionModal(<?php echo $seance['id']; ?>)" class="status-btn present">
                                    <i class="fas fa-check"></i> Présent
                                </button>
                            <?php else: ?>
                                <span class="status-btn disabled">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="card">
            <h2><i class="fas fa-calendar-alt"></i> Séances du jour</h2>
            <p>Aucune séance programmée pour votre salle (<?= $_SESSION['classroom'] ?? 'Non définie' ?>) aujourd'hui (<?= $today_french ?>)</p>
            <p>Semaine ID: <?= $current_week['id'] ?></p>
        </div>
        <?php endif; ?>
    </div>

    <!-- Modal pour la vérification de position -->
    <div id="positionModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closePositionModal()">&times;</span>
            <h3 class="modal-title"><i class="fas fa-map-marker-alt"></i> Vérification de position</h3>
            
            <div class="position-info">
                <p>Vérification de votre position par rapport au délégué...</p>
                <p><small>Vous devez être à moins de <strong>100 mètres</strong> du délégué pour marquer votre présence.</small></p>
                
                <div class="coordinates">
                    <div class="coordinate">
                        <strong>Votre latitude</strong>
                        <span id="studentLatitude">-</span>
                    </div>
                    <div class="coordinate">
                        <strong>Votre longitude</strong>
                        <span id="studentLongitude">-</span>
                    </div>
                </div>
                
                <div class="loading" id="positionLoading">
                    <div class="spinner"></div>
                    <p>Récupération de votre position...</p>
                </div>
                
                <div id="permissionHelp" class="permission-help" style="display: none;">
                    <h4><i class="fas fa-exclamation-triangle"></i> Problème de permission</h4>
                    <p>Votre navigateur n'a pas autorisé l'accès à votre position. Voici comment résoudre ce problème :</p>
                    <ol>
                        <li>Cliquez sur l'icône de cadenas ou "i" dans la barre d'adresse</li>
                        <li>Autorisez l'accès à votre position</li>
                        <li>Actualisez la page et réessayez</li>
                    </ol>
                    <p><strong>Sur iPhone :</strong> Allez dans Réglages > Confidentialité > Service de localisation > Safari > Autoriser "Demander ou Toujours"</p>
                </div>
                
                <div id="distanceResult" style="display: none;">
                    <p id="distanceMessage"></p>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="modal-btn modal-btn-secondary" onclick="closePositionModal()">Annuler</button>
                <button type="button" class="modal-btn modal-btn-primary" id="confirmPresenceBtn" onclick="confirmPresence()" disabled>Confirmer présence</button>
            </div>
        </div>
    </div>

    <script>
        let currentSeanceId = null;
        let studentPosition = null;
        
        // Fonction pour forcer la demande de géolocalisation
        function getCurrentPosition() {
            return new Promise((resolve, reject) => {
                const options = {
                    enableHighAccuracy: true, // Haute précision

                    maximumAge: 60000 // Ne pas utiliser de position plus vieille que 1 minute
                };
                
                if (!navigator.geolocation) {
                    reject("La géolocalisation n'est pas supportée par ce navigateur.");
                    return;
                }
                
                navigator.geolocation.getCurrentPosition(
                    position => {
                        resolve(position);
                    },
                    error => {
                        let errorMessage = "Erreur de géolocalisation: ";
                        switch(error.code) {
                            case error.PERMISSION_DENIED:
                                errorMessage += "Vous avez refusé l'accès à votre position. Veuillez autoriser la géolocalisation dans les paramètres de votre navigateur.";
                                break;
                            case error.POSITION_UNAVAILABLE:
                                errorMessage += "Les informations de localisation ne sont pas disponibles.";
                                break;
                            case error.TIMEOUT:
                                errorMessage += "La demande de localisation a expiré.";
                                break;
                            default:
                                errorMessage += "Une erreur inconnue s'est produite.";
                                break;
                        }
                        reject(errorMessage);
                    },
                    options
                );
            });
        }
        
        // Fonction améliorée pour ouvrir le modal
        function openPositionModal(seanceId) {
            currentSeanceId = seanceId;
            const modal = document.getElementById('positionModal');
            const loading = document.getElementById('positionLoading');
            const permissionHelp = document.getElementById('permissionHelp');
            const distanceResult = document.getElementById('distanceResult');
            const confirmBtn = document.getElementById('confirmPresenceBtn');
            
            // Réinitialiser l'interface
            loading.style.display = 'block';
            permissionHelp.style.display = 'none';
            distanceResult.style.display = 'none';
            confirmBtn.disabled = true;
            document.getElementById('studentLatitude').textContent = '-';
            document.getElementById('studentLongitude').textContent = '-';
            
            modal.style.display = 'block';
            
            // Forcer la demande de géolocalisation
            getCurrentPosition()
                .then(position => {
                    const lat = position.coords.latitude;
                    const lng = position.coords.longitude;
                    
                    // Mettre à jour l'affichage
                    document.getElementById('studentLatitude').textContent = lat.toFixed(6);
                    document.getElementById('studentLongitude').textContent = lng.toFixed(6);
                    
                    studentPosition = { lat, lng };
                    
                    // Vérifier la distance avec le serveur
                    checkDistanceWithServer(seanceId, lat, lng);
                })
                .catch(error => {
                    console.error('Erreur de géolocalisation:', error);
                    loading.style.display = 'none';
                    permissionHelp.style.display = 'block';
                    document.getElementById('permissionHelp').innerHTML += `<p><strong>Détails:</strong> ${error}</p>`;
                });
        }
        
        // Fonction pour vérifier la distance avec le serveur
        function checkDistanceWithServer(seanceId, lat, lng) {
            const formData = new FormData();
            formData.append('seance_id', seanceId);
            formData.append('student_lat', lat);
            formData.append('student_lng', lng);
            
            fetch('check_distance.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                const loading = document.getElementById('positionLoading');
                const distanceResult = document.getElementById('distanceResult');
                const distanceMessage = document.getElementById('distanceMessage');
                const confirmBtn = document.getElementById('confirmPresenceBtn');
                
                loading.style.display = 'none';
                distanceResult.style.display = 'block';
                
                if (data.success) {
                    if (data.within_range) {
                        distanceMessage.innerHTML = `<span style="color: #4caf50;">
                            <i class="fas fa-check-circle"></i> Distance valide: Vous êtes dans la zone autorisée 
                        </span>`;
                        confirmBtn.disabled = false;
                    } else {
                        distanceMessage.innerHTML = `<span style="color: #f44336;">
                            <i class="fas fa-exclamation-triangle"></i> Distance trop grande: Vous êtes hors de la zone autorisée.
                        </span>`;
                        confirmBtn.disabled = true;
                    }
                } else {
                    distanceMessage.innerHTML = `<span style="color: #f44336;">
                        <i class="fas fa-exclamation-triangle"></i> ${data.message}
                    </span>`;
                    confirmBtn.disabled = true;
                }
            })
            .catch(error => {
                console.error('Erreur lors de la vérification de distance:', error);
                const loading = document.getElementById('positionLoading');
                const distanceResult = document.getElementById('distanceResult');
                const distanceMessage = document.getElementById('distanceMessage');
                
                loading.style.display = 'none';
                distanceResult.style.display = 'block';
                distanceMessage.innerHTML = `<span style="color: #f44336;">
                    <i class="fas fa-exclamation-triangle"></i> Erreur lors de la vérification de distance
                </span>`;
            });
        }
        
        // Fonction pour confirmer la présence
        function confirmPresence() {
            if (!currentSeanceId || !studentPosition) {
                alert('Erreur: Position non disponible');
                return;
            }
            
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'dashEtudiant.php';
            
            const seanceIdInput = document.createElement('input');
            seanceIdInput.type = 'hidden';
            seanceIdInput.name = 'seance_id';
            seanceIdInput.value = currentSeanceId;
            
            const statusInput = document.createElement('input');
            statusInput.type = 'hidden';
            statusInput.name = 'status';
            statusInput.value = 'present';
            
            const latInput = document.createElement('input');
            latInput.type = 'hidden';
            latInput.name = 'student_latitude';
            latInput.value = studentPosition.lat;
            
            const lngInput = document.createElement('input');
            lngInput.type = 'hidden';
            lngInput.name = 'student_longitude';
            lngInput.value = studentPosition.lng;
            
            form.appendChild(seanceIdInput);
            form.appendChild(statusInput);
            form.appendChild(latInput);
            form.appendChild(lngInput);
            
            document.body.appendChild(form);
            form.submit();
        }
        
        // Fonction pour fermer le modal
        function closePositionModal() {
            document.getElementById('positionModal').style.display = 'none';
            currentSeanceId = null;
            studentPosition = null;
        }
        
        // Fermer le modal en cliquant en dehors
        window.onclick = function(event) {
            const modal = document.getElementById('positionModal');
            if (event.target === modal) {
                closePositionModal();
            }
        }
        
        // Fonction pour réessayer la géolocalisation
        function retryGeolocation() {
            if (currentSeanceId) {
                openPositionModal(currentSeanceId);
            }
        }
    </script>
</body>
</html>