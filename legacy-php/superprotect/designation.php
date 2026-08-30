<?php include 'includes/db.php'; ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Désignation</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body class="admin-body">
    <div class="container py-5">
        <h1 class="text-center mb-5 neon-violet">Validation des délégués</h1>
        
        <div class="table-responsive">
            <table class="table table-dark table-hover">
                <thead>
                    <tr>
                        <th>Nom</th>
                        <th>Téléphone</th>
                        <th>Salle</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $stmt = $pdo->query("SELECT * FROM users WHERE grade = 'Delegue'");
                    while ($user = $stmt->fetch()):
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($user['name']) ?></td>
                        <td><?= htmlspecialchars($user['phone']) ?></td>
                        <td><?= htmlspecialchars($user['classroom'] ?? 'N/A') ?></td>
                        <td><?= htmlspecialchars($user['validated']) ?></td>
                        <td>
                            <?php if ($user['validated'] == 'pending'): ?>
                                <a href="validate.php?id=<?= $user['id'] ?>&action=yes" class="btn btn-success btn-sm">Valider</a>
                                <a href="validate.php?id=<?= $user['id'] ?>&action=no" class="btn btn-danger btn-sm">Refuser</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>