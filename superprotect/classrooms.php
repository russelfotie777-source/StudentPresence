<?php
require_once '../includes/config.php';

$field_id = $_GET['field_id'] ?? 0;
$field = $pdo->query("SELECT fields.*, levels.name as level_name FROM fields 
                      JOIN levels ON fields.level_id = levels.id 
                      WHERE fields.id = $field_id")->fetch(PDO::FETCH_ASSOC);

// Récupérer les salles existantes depuis la table users
$classrooms = $pdo->query("SELECT DISTINCT classroom FROM users WHERE classroom IS NOT NULL AND classroom != ''")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Salles - <?= $field['name'] ?? '' ?></title>
    <link rel="stylesheet" href="../includes/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <?php include 'admin-navbar.php'; ?>
    
    <div class="admin-container">
        <h1>
            <i class="fas fa-door-open"></i> Salles de <?= htmlspecialchars($field['name'] ?? 'Filière inconnue') ?>
            <small style="display: block; font-size: 1rem; color: var(--gris); margin-top: 0.5rem;">
                Niveau: <?= htmlspecialchars($field['level_name'] ?? '') ?>
            </small>
        </h1>
        
        <?php if (!empty($classrooms)): ?>
            <div class="cards-container">
                <?php foreach ($classrooms as $room): ?>
                    <div class="card">
                        <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem;">
                            <div style="width: 50px; height: 50px; background-color: rgba(138, 43, 226, 0.1); border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-door-open" style="font-size: 1.5rem; color: var(--violet-neon);"></i>
                            </div>
                            <h3 style="margin: 0;"><?= htmlspecialchars($room['classroom']) ?></h3>
                        </div>
                        <p style="display: flex; align-items: center; gap: 0.5rem;">
                            <i class="fas fa-users" style="color: var(--gris);"></i>
                            <span>
                                <?php 
                                    $count = $pdo->query("SELECT COUNT(*) FROM users WHERE classroom = '".$room['classroom']."'")->fetchColumn();
                                    echo $count . ' ' . ($count > 1 ? 'membres' : 'membre');
                                ?>
                            </span>
                        </p>
                        <div style="margin-top: 1rem; display: flex; gap: 0.5rem;">
                            <a href="#" class="btn" style="background-color: rgba(138, 43, 226, 0.1); color: var(--violet-neon); flex: 1; text-align: center;">
                                <i class="fas fa-eye"></i> Voir
                            </a>
                            <a href="#" class="btn" style="background-color: rgba(138, 43, 226, 0.1); color: var(--violet-neon); flex: 1; text-align: center;">
                                <i class="fas fa-edit"></i> Modifier
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div style="text-align: center; padding: 2rem; background-color: var(--noir-light); border-radius: 8px; border: 1px dashed rgba(138, 43, 226, 0.5);">
                <i class="fas fa-door-closed" style="font-size: 3rem; color: var(--gris); margin-bottom: 1rem;"></i>
                <h3 style="color: var(--gris);">Aucune salle disponible</h3>
                <p>Commencez par ajouter une salle de classe</p>
            </div>
        <?php endif; ?>
        
        <div class="add-form">
            <h3><i class="fas fa-plus-circle"></i> Ajouter une nouvelle salle</h3>
            <form action="add-classroom.php" method="POST">
                <input type="hidden" name="field_id" value="<?= $field_id ?>">
                <input type="text" name="name" placeholder="Nom de la salle (ex: E302, A101)" required>
                <button type="submit"><i class="fas fa-plus"></i> Ajouter</button>
            </form>
        </div>
    </div>
</body>
</html>