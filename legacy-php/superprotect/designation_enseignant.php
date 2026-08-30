<?php include 'includes/db.php'; ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Enseignants</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="admin-body">
    <div class="container py-5">
        <h1 class="text-center mb-5">Validation des enseignants</h1>
        
        <div class="table-responsive">
            <table class="table table-dark table-hover">
                <thead>
                    <tr>
                        <th>Nom</th>
                        <th>Téléphone</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $stmt = $pdo->query("SELECT * FROM users WHERE grade = 'Enseignant'");
                    while ($user = $stmt->fetch()):
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($user['name']) ?></td>
                        <td><?= htmlspecialchars($user['phone']) ?></td>
                        <td><?= htmlspecialchars($user['validated']) ?></td>
                        <td>
                            <?php if ($user['validated'] == 'pending'): ?>
                                <a href="validate_teacher.php?id=<?= $user['id'] ?>&action=yes" class="btn btn-success btn-sm">Valider</a>
                                <a href="validate_teacher.php?id=<?= $user['id'] ?>&action=no" class="btn btn-danger btn-sm">Refuser</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>