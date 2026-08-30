<?php
require_once '../includes/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $classroom_name = trim($_POST['name']);
    $field_id = $_POST['field_id'];
    
    if (!empty($classroom_name)) {
        // Vérifier si la salle existe déjà
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE classroom = ?");
        $stmt->execute([$classroom_name]);
        $exists = $stmt->fetchColumn();
        
        if (!$exists) {
            // Ajouter un utilisateur test (dans une vraie application, vous auriez une table dédiée aux salles)
            $pdo->prepare("INSERT INTO users (name, phone, grade, classroom, password, validated) 
                          VALUES (?, ?, ?, ?, ?, ?)")
                ->execute([
                    'Salle ' . $classroom_name,
                    '000000000',
                    'Enseignant',
                    $classroom_name,
                    password_hash('temp_password', PASSWORD_DEFAULT),
                    'yes'
                ]);
        }
    }
}

header("Location: classrooms.php?field_id=" . $_POST['field_id']);
exit();
?>