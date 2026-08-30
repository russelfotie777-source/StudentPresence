<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Vérification de la connexion
if (!isLoggedIn()) {
    header('Location: index.php');
    exit();
}

// Vérification que le compte est validé
redirectIfNotValidated();

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
$current_week = getCurrentWeek($pdo);

if (isDelegate()) {
    if (!isset($_SESSION['classroom'])) {
        $_SESSION['error'] = "Votre salle de classe n'est pas définie dans votre profil.";
    } else {
        // Récupérer l'ID de la filière et du niveau de l'utilisateur depuis la base de données
        $user_filiere_id = null;
        $user_niveau_id = null;
        $filiere_nom = '';

        // Correction: Les IDs sont déjà dans la table users, on les récupère directement
        $query_user_info = "SELECT 
                                filiere_id, 
                                niveau_id
                            FROM users
                            WHERE id = ?";
        
        $stmt_user_info = $pdo->prepare($query_user_info);
        $stmt_user_info->execute([$_SESSION['user_id']]);
        $user_info = $stmt_user_info->fetch(PDO::FETCH_ASSOC);

        if ($user_info) {
            $user_filiere_id = $user_info['filiere_id'];
            $user_niveau_id = $user_info['niveau_id'];
            
            // On peut aussi stocker ces infos dans la session pour ne pas faire la requête à chaque fois
            $_SESSION['filiere_id'] = $user_filiere_id;
            $_SESSION['niveau_id'] = $user_niveau_id;
        }

        if ($user_filiere_id && $user_niveau_id) {
            // CORRECTION: Nouvelle requête SQL pour les délégués
            // Les jointures sont refaites pour être cohérentes avec le schéma de la base de données
            // `seances` -> `salles` -> `filieres` -> `niveaux`
            $query = "SELECT s.*, c.matiere_id, m.nom as matiere_nom, m.code as matiere_code, 
                     u.name as enseignant_nom, u.phone as enseignant_phone,
                     sl.nom as salle_nom, f.nom as filiere_nom,
                     emp.semaine_id, sem.numero as semaine_num,
                     p.etudiants_presents, p.status as push_status
              FROM seances s
              JOIN cours c ON s.cours_id = c.id
              JOIN matieres m ON c.matiere_id = m.id
              JOIN users u ON s.enseignant_id = u.id
              JOIN salles sl ON s.salle_id = sl.id
              JOIN filieres f ON sl.filiere_id = f.id  
              JOIN niveaux n ON f.niveau_id = n.id    
              LEFT JOIN emplois_temps emp ON c.emploi_id = emp.id AND emp.salle_id = sl.id
              LEFT JOIN semaines sem ON emp.semaine_id = sem.id
              LEFT JOIN pushes p ON s.id = p.seance_id
              WHERE sl.nom = ?
              AND s.jour = ?
              AND f.id = ?  
              AND n.id = ?  
              AND (emp.semaine_id = ? OR emp.semaine_id IS NULL)
              ORDER BY s.heure_debut";
            
            $stmt = $pdo->prepare($query);
            $stmt->execute([$_SESSION['classroom'], $today_french, $user_filiere_id, $user_niveau_id, $current_week['id']]);
            $seances = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($seances)) {
                $_SESSION['salle_nom'] = $seances[0]['salle_nom'];
                // Mettre à jour la session avec le nom de la filière si elle est trouvée
                $_SESSION['filiere'] = $seances[0]['filiere_nom'];
            }
        } else {
            $_SESSION['error'] = "Impossible de trouver les informations de filière et de niveau pour votre compte.";
        }
    }
} elseif (isTeacher()) {
    // CORRECTION : Requête avec LEFT JOIN et conditions WHERE corrigées
    $query = "SELECT s.*, 
                 m.nom as matiere_nom, m.code as matiere_code,
                 sl.nom as salle_nom, f.nom as filiere_nom,
                 emp.semaine_id, sem.numero as semaine_num,
                 u.name as enseignant_nom, u.phone as enseignant_phone,
                 p.etudiants_presents, p.status as push_status
          FROM seances s
          JOIN cours c ON s.cours_id = c.id
          JOIN matieres m ON c.matiere_id = m.id
          JOIN users u ON s.enseignant_id = u.id
          JOIN salles sl ON s.salle_id = sl.id
          LEFT JOIN filieres f ON sl.filiere_id = f.id
          LEFT JOIN emplois_temps emp ON c.emploi_id = emp.id
          LEFT JOIN semaines sem ON emp.semaine_id = sem.id
          LEFT JOIN pushes p ON s.id = p.seance_id
          WHERE s.enseignant_id = ?
          AND s.jour = ?
          AND (emp.semaine_id = ? OR emp.semaine_id IS NULL)
          ORDER BY s.heure_debut";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$_SESSION['user_id'], $today_french, $current_week['id']]);
    $seances = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Traitement du formulaire de présence
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['seance_id']) && !isset($_POST['push_seance'])) {
        // Traitement du statut de présence seulement si ce n'est pas un push
        $seance_id = $_POST['seance_id'];
        $status = $_POST['status'];
        $current_time_for_update = date('H:i:s');
        
        $query = "SELECT heure_debut, heure_fin, debut_reel, fin_reelle FROM seances WHERE id = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$seance_id]);
        $seance_to_update = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($seance_to_update) {
            $can_update = false;
            
            if (isDelegate()) {
                $query = "SELECT s.id 
                          FROM seances s
                          JOIN salles sl ON s.salle_id = sl.id
                          WHERE s.id = ? AND sl.nom = ?";
                $stmt = $pdo->prepare($query);
                $stmt->execute([$seance_id, $_SESSION['classroom']]);
                $can_update = $stmt->fetch() !== false;
            } elseif (isTeacher()) {
                $query = "SELECT id FROM seances WHERE id = ? AND enseignant_id = ?";
                $stmt = $pdo->prepare($query);
                $stmt->execute([$seance_id, $_SESSION['user_id']]);
                $can_update = $stmt->fetch() !== false;
            }
            
            if ($can_update) {
                $start = new DateTime($seance_to_update['heure_debut']);
                $end = new DateTime($seance_to_update['heure_fin']);
                $now = new DateTime($current_time_for_update);
                
                $margin = new DateInterval('PT15M');
                $start_with_margin = (clone $start)->sub($margin);
                $end_with_margin = (clone $end)->add($margin);
                
                if ($now >= $start_with_margin && $now <= $end_with_margin) {
                    if (isDelegate()) {
                        // Récupérer les valeurs existantes
                        $debut_reel = $seance_to_update['debut_reel'];
                        $fin_reelle = $seance_to_update['fin_reelle'];
                        
                        // Mettre à jour seulement si le bouton correspondant est cliqué
                        if (isset($_POST['set_debut_reel'])) {
                            $debut_reel = $current_time_for_update;
                        } 
                        if (isset($_POST['set_fin_reelle'])) {
                            $fin_reelle = $current_time_for_update;
                        }
                        
                        $query = "UPDATE seances 
                                  SET etat_delegue = ?, debut_reel = ?, fin_reelle = ?
                                  WHERE id = ?";
                        $stmt = $pdo->prepare($query);
                        $stmt->execute([$status, $debut_reel, $fin_reelle, $seance_id]);
                        
                        // Si les deux heures sont définies et l'état final est présent, calculer le quota
                        if ($debut_reel && $fin_reelle && $status === 'present') {
                            $debut = new DateTime($debut_reel);
                            $fin = new DateTime($fin_reelle);
                            $diff = $debut->diff($fin);
                            
                            // Convertir en heures avec décimales (par exemple 1h30 = 1.5)
                            $hours = $diff->h + ($diff->i / 60);
                            
                            // Ajouter le quota à l'utilisateur (enseignant)
                            $query = "UPDATE users 
                                      SET quota = quota + ?
                                      WHERE id = (SELECT enseignant_id FROM seances WHERE id = ?)";
                            $stmt = $pdo->prepare($query);
                            $stmt->execute([$hours, $seance_id]);
                        }
                    } elseif (isTeacher()) {
                        $query = "UPDATE seances SET etat_prof = ? WHERE id = ?";
                        $stmt = $pdo->prepare($query);
                        $stmt->execute([$status, $seance_id]);
                    }
                    
                    $_SESSION['message'] = "Statut de présence mis à jour avec succès!";
                    header("Location: dashboard.php");
                    exit();
                } else {
                    $_SESSION['error'] = "Impossible de modifier le statut en dehors des heures de cours (marge de 15 minutes).";
                }
            } else {
                $_SESSION['error'] = "Vous n'avez pas les droits pour modifier cette séance.";
            }
        } else {
            $_SESSION['error'] = "Séance introuvable.";
        }
    } elseif (isset($_POST['push_seance'])) {
        // Traitement du push de séance seulement
        $seance_id = $_POST['seance_id'];
        $etudiants_presents = intval($_POST['etudiants_presents']);
        
        // Vérifier que l'enseignant a le droit de pousser cette séance
        $query = "SELECT id FROM seances WHERE id = ? AND enseignant_id = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$seance_id, $_SESSION['user_id']]);
        
        if ($stmt->fetch()) {
            // Vérifier si un push existe déjà pour cette séance
            $query = "SELECT id FROM pushes WHERE seance_id = ?";
            $stmt = $pdo->prepare($query);
            $stmt->execute([$seance_id]);
            
            if ($stmt->fetch()) {
                // Mettre à jour le push existant
                $query = "UPDATE pushes SET etudiants_presents = ?, status = 'pending', created_at = NOW() WHERE seance_id = ?";
                $stmt = $pdo->prepare($query);
                $stmt->execute([$etudiants_presents, $seance_id]);
            } else {
                // Créer un nouveau push
                $query = "INSERT INTO pushes (seance_id, etudiants_presents, status, created_at) VALUES (?, ?, 'pending', NOW())";
                $stmt = $pdo->prepare($query);
                $stmt->execute([$seance_id, $etudiants_presents]);
            }
            
            $_SESSION['message'] = "La demande de report de séance a été enregistrée avec succès!";
            header("Location: dashboard.php");
            exit();
        } else {
            $_SESSION['error'] = "Vous n'avez pas les droits pour reporter cette séance.";
            header("Location: dashboard.php");
            exit();
        }
    }
}

function getCurrentWeek($pdo){
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
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de Bord</title>
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
        }
        
        .navbar {
            background: linear-gradient(to right, var(--violet-dark), var(--violet-medium));
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
            background-color: var(--violet-light);
        }
        
        .navbar a:hover {
            background-color: var(--violet-neon);
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
            background: rgba(123, 75, 158, 0.2);
            border-radius: 8px;
            border-left: 4px solid var(--violet-neon);
        }
        
        .header h1 {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
            background: linear-gradient(to right, var(--violet-neon), var(--white));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .card {
            background: rgba(10, 10, 10, 0.7);
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
            border: 1px solid var(--violet-medium);
        }
        
        .card h2 {
            color: var(--violet-neon);
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid var(--violet-medium);
            font-size: 1.5rem;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }
        
        .info-item {
            background: rgba(74, 20, 140, 0.3);
            padding: 1rem;
            border-radius: 6px;
            border-left: 3px solid var(--violet-neon);
        }
        
        .info-item strong {
            color: var(--violet-neon);
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
            border-bottom: 1px solid var(--violet-medium);
        }
        
        .seance-table th {
            background-color: var(--violet-dark);
            color: var(--violet-neon);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.8rem;
            letter-spacing: 1px;
        }
        
        .seance-table tr:hover {
            background-color: rgba(123, 75, 158, 0.1);
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
        
        .time-btn {
            background-color: var(--violet-light);
            color: white;
        }
        
        .push-btn {
            background-color: #ff9800;
            color: white;
        }
        
        .push-badge {
            background-color: #ff9800;
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.8rem;
            margin-left: 0.5rem;
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
            background-color: var(--violet-medium);
            color: white;
        }
        
        .badge-secondary {
            background-color: var(--violet-light);
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
            background-color: var(--violet-dark);
            margin: 15% auto;
            padding: 2rem;
            border: 1px solid var(--violet-neon);
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
            color: var(--violet-neon);
        }
        
        .modal-title {
            color: var(--violet-neon);
            margin-bottom: 1.5rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--violet-neon);
        }
        
        .form-group input {
            width: 100%;
            padding: 0.75rem;
            border-radius: 4px;
            border: 1px solid var(--violet-medium);
            background-color: rgba(10, 10, 10, 0.7);
            color: white;
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
            background-color: var(--violet-neon);
            color: white;
        }
        
        .modal-btn-secondary {
            background-color: #666;
            color: #ccc;
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
        }
    </style>
</head>
<body>
    <div class="navbar">
        <div>
            <i class="fas fa-user-circle"></i> Bienvenue, <?php echo htmlspecialchars($_SESSION['name']); ?> 
            <span style="color: var(--violet-neon);">(<?php echo htmlspecialchars($_SESSION['grade']); ?>)</span>
        </div>
        <div>
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Déconnexion</a>
        </div>
    </div>
    
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-tachometer-alt"></i> Tableau de Bord</h1>
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
                <?php if (isDelegate()): ?>
                <div class="info-item">
                    <strong>Salle/Classe</strong>
                    <span class="badge badge-primary"><?php echo htmlspecialchars($_SESSION['classroom'] ?? 'Non spécifié'); ?></span>
                </div>
                <div class="info-item">
                    <strong>Filière</strong>
                    <span class="badge badge-secondary"><?php echo htmlspecialchars($_SESSION['filiere'] ?? 'Non spécifié'); ?></span>
                </div>
                <div class="info-item">
                    <strong>Statut de validation</strong>
                    <?php 
                        $status = htmlspecialchars($_SESSION['validated']);
                        $status_color = $status === 'yes' ? '#4caf50' : ($status === 'pending' ? '#ff9800' : '#f44336');
                        echo "<span style='color: $status_color'>" . ucfirst($status) . "</span>";
                    ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php if (isTeacher()): ?>
<div class="card">
    <h2><i class="fas fa-money-bill-wave"></i> Gestion des Heures</h2>
    <a href="salaireprof.php" class="status-btn" style="background-color: #4CAF50;">
        <i class="fas fa-chart-line"></i> Suivi de mes Heures
    </a>
</div>
<?php endif; ?>

        <?php if (isTeacher()): ?>
<div class="card">
    <h2><i class="fas fa-user-graduate"></i> Gestion des Étudiants</h2>
    <a href="promotion_temporaire.php" class="status-btn" style="background-color: #9c27b0;">
        <i class="fas fa-user-shield"></i> Promotions Temporaires
    </a>
</div>
<?php endif; ?>
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
                        <?php if (isDelegate()): ?>
                            <th>État (Délégué)</th>
                            <th>Position</th> <!-- NOUVELLE COLONNE -->
                            <th>Push</th>
                        <?php elseif (isTeacher()): ?>
                            <th>État Délégué</th>
                            <th>État (Professeur)</th>
                        <?php endif; ?>
                        <th>État final</th>
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
                        
                        // Calcul de l'état final basé sur les valeurs en base
                        $final_status = 'Absent';
                        $final_class = 'absent';
                        
                        if ($seance['etat_delegue'] === 'present' && $seance['etat_prof'] === 'present') {
                            $final_status = 'Présent';
                            $final_class = 'present';
                        } elseif ($is_past && ($seance['etat_delegue'] === NULL || $seance['etat_prof'] === NULL)) {
                            $final_status = 'Non marqué (temps écoulé)';
                            $final_class = 'disabled';
                        }
                        
                        // Gestion du push
                        $has_push = !empty($seance['etudiants_presents']);
                        $push_status = $seance['push_status'] ?? null;
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
                        <td>
                            <?php if (isDelegate() && $is_active): ?>
                                <?php if (isset($seance['debut_reel'])): ?>
                                    <span class="time-display"><?php echo substr($seance['debut_reel'], 0, 5); ?></span>
                                <?php else: ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="seance_id" value="<?php echo $seance['id']; ?>">
                                        <input type="hidden" name="status" value="<?php echo htmlspecialchars($seance['etat_delegue'] ?? 'present'); ?>">
                                        <input type="hidden" name="set_debut_reel" value="1">
                                        <button type="submit" class="status-btn time-btn">
                                            <i class="fas fa-clock"></i> Début réel
                                        </button>
                                    </form>
                                <?php endif; ?>
                            <?php else: ?>
                                <?php echo isset($seance['debut_reel']) ? substr($seance['debut_reel'], 0, 5) : '-'; ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (isDelegate() && $is_active): ?>
                                <?php if (isset($seance['fin_reelle'])): ?>
                                    <span class="time-display"><?php echo substr($seance['fin_reelle'], 0, 5); ?></span>
                                <?php else: ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="seance_id" value="<?php echo $seance['id']; ?>">
                                        <input type="hidden" name="status" value="<?php echo htmlspecialchars($seance['etat_delegue'] ?? 'present'); ?>">
                                        <input type="hidden" name="set_fin_reelle" value="1">
                                        <button type="submit" class="status-btn time-btn" <?php echo !isset($seance['debut_reel']) ? 'disabled' : ''; ?>>
                                            <i class="fas fa-clock"></i> Fin réelle
                                        </button>
                                    </form>
                                <?php endif; ?>
                            <?php else: ?>
                                <?php echo isset($seance['fin_reelle']) ? substr($seance['fin_reelle'], 0, 5) : '-'; ?>
                            <?php endif; ?>
                        </td>
                        
                        <?php if (isDelegate()): ?>
                            <td>
                                <?php if ($is_active): ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="seance_id" value="<?php echo $seance['id']; ?>">
                                        <input type="hidden" name="status" value="present">
                                        <button type="submit" class="status-btn present" <?php echo ($seance['etat_delegue'] === 'present') ? 'disabled' : ''; ?>>
                                            <i class="fas fa-check"></i> Présent
                                        </button>
                                    </form>
                                    <form method="POST" style="display: inline; margin-left: 5px;">
                                        <input type="hidden" name="seance_id" value="<?php echo $seance['id']; ?>">
                                        <input type="hidden" name="status" value="absent">
                                        <button type="submit" class="status-btn absent" <?php echo ($seance['etat_delegue'] === 'absent') ? 'disabled' : ''; ?>>
                                            <i class="fas fa-times"></i> Absent
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <?php 
                                        $status = isset($seance['etat_delegue']) ? 
                                                  ($seance['etat_delegue'] === 'present' ? 'Présent' : 'Absent') : 
                                                  ($is_past ? 'Non marqué' : '-');
                                        $status_class = isset($seance['etat_delegue']) ? 
                                                       ($seance['etat_delegue'] === 'present' ? 'present' : 'absent') : 
                                                       'disabled';
                                    ?>
                                    <span class="status-btn <?php echo $status_class; ?>">
                                        <?php echo $status; ?>
                                    </span>
                                <?php endif; ?>
                            </td>

   <!-- NOUVELLE COLONNE POSITION -->
    <td>
        <?php if ($is_active): ?>
            <a href="position.php?seance_id=<?php echo $seance['id']; ?>" class="status-btn" style="background-color: #2196F3;">
                <i class="fas fa-map-marker-alt"></i> Send Position
            </a>
        <?php else: ?>
            <span class="status-btn disabled">
                <i class="fas fa-map-marker-alt"></i> Position
            </span>
        <?php endif; ?>
    </td>

                            <td>
                                <?php if ($has_push && $push_status === 'pending'): ?>
                                    <a href="liste.php?seance_id=<?php echo $seance['id']; ?>" class="status-btn push-btn">
                                        <i class="fas fa-users"></i> Push (<?php echo $seance['etudiants_presents']; ?> présents)
                                    </a>
                                <?php elseif ($has_push): ?>
                                    <span class="push-badge">Reporté (<?php echo $seance['etudiants_presents']; ?> présents)</span>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                        <?php elseif (isTeacher()): ?>
                            <td>
                                <?php 
                                    $delegue_status = isset($seance['etat_delegue']) ? 
                                                     ($seance['etat_delegue'] === 'present' ? 'Présent' : 'Absent') : 
                                                     ($is_past ? 'Non marqué' : '-');
                                    $delegue_class = isset($seance['etat_delegue']) ? 
                                                    ($seance['etat_delegue'] === 'present' ? 'present' : 'absent') : 
                                                    'disabled';
                                ?>
                                <span class="status-btn <?php echo $delegue_class; ?>">
                                    <?php echo $delegue_status; ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($is_active): ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="seance_id" value="<?php echo $seance['id']; ?>">
                                        <input type="hidden" name="status" value="present">
                                        <button type="submit" class="status-btn present" <?php echo ($seance['etat_prof'] === 'present') ? 'disabled' : ''; ?>>
                                            <i class="fas fa-check"></i> Présent
                                        </button>
                                    </form>
                                    <form method="POST" style="display: inline; margin-left: 5px;">
                                        <input type="hidden" name="seance_id" value="<?php echo $seance['id']; ?>">
                                        <input type="hidden" name="status" value="absent">
                                        <button type="submit" class="status-btn absent" <?php echo ($seance['etat_prof'] === 'absent') ? 'disabled' : ''; ?>>
                                            <i class="fas fa-times"></i> Absent
                                        </button>
                                    </form>
                                    <button onclick="openPushModal(<?php echo $seance['id']; ?>)" class="status-btn push-btn" style="margin-left: 5px;">
                                        <i class="fas fa-clock"></i> Push
                                    </button>
                                <?php else: ?>
                                    <?php 
                                        $status = isset($seance['etat_prof']) ? 
                                                  ($seance['etat_prof'] === 'present' ? 'Présent' : 'Absent') : 
                                                  ($is_past ? 'Non marqué' : '-');
                                        $status_class = isset($seance['etat_prof']) ? 
                                                       ($seance['etat_prof'] === 'present' ? 'present' : 'absent') : 
                                                       'disabled';
                                    ?>
                                    <span class="status-btn <?php echo $status_class; ?>">
                                        <?php echo $status; ?>
                                    </span>
                                    <?php if ($has_push): ?>
                                        <span class="push-badge">Reporté (+<?php echo $seance['etudiants_presents']; ?> min)</span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                        <?php endif; ?>
                        
                        <td>
                            <span class="status-btn <?php echo $final_class; ?>">
                                <?php echo $final_status; ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="card">
            <h2><i class="fas fa-calendar-alt"></i> Séances du jour</h2>
            <?php if (isTeacher()): ?>
                <p>Aucune séance programmée pour aujourd'hui (<?= $today_french ?>)</p>
                <p>Enseignant ID: <?= $_SESSION['user_id'] ?></p>
                <p>Semaine ID: <?= $current_week['id'] ?></p>
                <p>Jour recherché: <?= $today_french ?></p>
            <?php elseif (isDelegate()): ?>
                <p>Aucune séance programmée pour votre salle (<?= $_SESSION['classroom'] ?? 'Non définie' ?>) aujourd'hui (<?= $today_french ?>)</p>
                <p>Semaine ID: <?= $current_week['id'] ?></p>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    

<div id="pushModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closePushModal()">&times;</span>
        <h3 class="modal-title"><i class="fas fa-clock"></i> Reporter la séance</h3>
        <form id="pushForm" method="POST">
            <input type="hidden" name="seance_id" id="modalSeanceId">
            <input type="hidden" name="push_seance" value="1">
            <div class="form-group">
                <label for="etudiants_presents">Nombre d'étudiants présents :</label>
                <input type="number" id="etudiants_presents" name="etudiants_presents" min="1" required>
            </div>
            <div class="modal-footer">
                <button type="button" class="modal-btn modal-btn-secondary" onclick="closePushModal()">Annuler</button>
                <button type="submit" class="modal-btn modal-btn-primary">Valider</button>
            </div>
        </form>
    </div>
</div>
    
    <script>
        // Fonctions pour gérer la modal de push
        function openPushModal(seanceId) {
            document.getElementById('modalSeanceId').value = seanceId;
            document.getElementById('pushModal').style.display = 'block';
        }
        
        function closePushModal() {
            document.getElementById('pushModal').style.display = 'none';
        }
        
        // Fermer la modal si on clique en dehors
        window.onclick = function(event) {
            const modal = document.getElementById('pushModal');
            if (event.target === modal) {
                closePushModal();
            }
        }
    </script>
</body>
</html>