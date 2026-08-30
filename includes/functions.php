<?php
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isTeacher() {
    return isset($_SESSION['grade']) && $_SESSION['grade'] === 'Enseignant';
}

function isDelegate() {
    return isset($_SESSION['grade']) && $_SESSION['grade'] === 'Delegue' 
           && isset($_SESSION['classroom']) && !empty($_SESSION['classroom']);
}

function isStudent() {
    return isset($_SESSION['grade']) && $_SESSION['grade'] === 'Etudiant' 
           && isset($_SESSION['classroom']) && !empty($_SESSION['classroom']);
}

function redirectIfNotValidated() {
    if (isDelegate()) {
        if ($_SESSION['validated'] === 'none') {
            header('Location: validation.php');
            exit();
        } elseif ($_SESSION['validated'] !== 'yes') {
            session_destroy();
            header('Location: index.php?error=pending');
            exit();
        }
    }
}

// Fonction pour vérifier la cohérence de la session
function verifySessionConsistency() {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['session_identifier'])) {
        return false;
    }
    
    // Régénérer l'identifiant de session basé sur les informations actuelles
    $current_identifier = generateSessionIdentifier(
        $_SESSION['user_id'], 
        $_SESSION['name'], 
        $_SESSION['grade']
    );
    
    // Comparer avec l'identifiant stocké en session
    if ($_SESSION['session_identifier'] !== $current_identifier) {
        // Incohérence détectée - déconnexion forcée
        session_destroy();
        header('Location: index.php?error=session_inconsistent');
        exit();
    }
    
    return true;
}

// Fonction pour générer un identifiant unique de session
function generateSessionIdentifier($user_id, $username, $grade) {
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
    $time = time();
    
    // Créer un identifiant unique basé sur les informations de l'utilisateur et de l'appareil
    $identifier_data = $user_id . $username . $grade . $user_agent . $ip_address . $time;
    return hash('sha256', $identifier_data);
}
?>