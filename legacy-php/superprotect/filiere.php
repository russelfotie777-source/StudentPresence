<?php 
include 'includes/db.php';
$filiere_id = $_GET['id'] ?? 0;

// Récupérer la filière
$stmt = $pdo->prepare("SELECT f.*, n.nom as niveau_nom FROM filieres f JOIN niveaux n ON f.niveau_id = n.id WHERE f.id = ?");
$stmt->execute([$filiere_id]);
$filiere = $stmt->fetch();

// Récupérer les salles
$stmt = $pdo->prepare("SELECT * FROM salles WHERE filiere_id = ? ORDER BY formation, nom");
$stmt->execute([$filiere_id]);
$salles = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - <?= htmlspecialchars($filiere['nom']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <style>
        :root {
            --primary-color: #6c5ce7;
            --primary-dark: #5649c0;
            --secondary-color: #00cec9;
            --dark-color: #2d3436;
            --light-color: #f5f6fa;
            --gradient-violet: linear-gradient(135deg, #6c5ce7 0%, #a29bfe 100%);
        }
        
        body.admin-body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-image: radial-gradient(circle at 10% 20%, rgba(108, 92, 231, 0.05) 0%, rgba(108, 92, 231, 0.05) 90%);
        }
        
        .container {
            max-width: 1200px;
        }
        
        .page-header {
            position: relative;
            padding: 2rem 0;
            margin-bottom: 3rem;
            background: var(--gradient-violet);
            color: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(108, 92, 231, 0.3);
            overflow: hidden;
        }
        
        .page-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIxMDAlIiBoZWlnaHQ9IjEwMCUiPjxkZWZzPjxwYXR0ZXJuIGlkPSJwYXR0ZXJuIiB3aWR0aD0iNDAiIGhlaWdodD0iNDAiIHBhdHRlcm5Vbml0cz0idXNlclNwYWNlT25Vc2UiIHBhdHRlcm5UcmFuc2Zvcm09InJvdGF0ZSg0NSkiPjxyZWN0IHdpZHRoPSIyMCIgaGVpZ2h0PSIyMCIgZmlsbD0icmdiYSgyNTUsMjU1LDI1NSwwLjA1KSIvPjwvcGF0dGVybj48L2RlZnM+PHJlY3Qgd2lkdGg9IjEwMCUiIGhlaWdodD0iMTAwJSIgZmlsbD0idXJsKCNwYXR0ZXJuKSIvPjwvc3ZnPg==');
        }
        
        .neon-violet {
            color: white;
            text-shadow: 0 0 10px rgba(255, 255, 255, 0.8), 0 0 20px rgba(167, 139, 250, 0.8);
            font-weight: 700;
            position: relative;
            z-index: 1;
        }
        
        .btn-emploi {
            background: var(--gradient-violet);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 50px;
            font-weight: 600;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(108, 92, 231, 0.4);
            position: relative;
            overflow: hidden;
            z-index: 1;
        }
        
        .btn-emploi::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, #5649c0 0%, #6c5ce7 100%);
            z-index: -1;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .btn-emploi:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(108, 92, 231, 0.6);
        }
        
        .btn-emploi:hover::before {
            opacity: 1;
        }
        
        .btn-emploi i {
            margin-right: 8px;
        }
        
        .salle-card {
            border: none;
            border-radius: 15px;
            overflow: hidden;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            background: white;
            height: 100%;
        }
        
        .salle-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 30px rgba(108, 92, 231, 0.2);
        }
        
        .card-body {
            padding: 2rem;
        }
        
        .card-title {
            color: var(--primary-color);
            font-weight: 700;
            margin-bottom: 1rem;
            font-size: 1.5rem;
        }
        
        .badge {
            font-size: 0.9rem;
            padding: 8px 12px;
            border-radius: 50px;
            font-weight: 600;
        }
        
        .btn-violet {
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 50px;
            font-weight: 500;
            transition: all 0.3s;
            margin-top: 1rem;
        }
        
        .btn-violet:hover {
            background-color: var(--primary-dark);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(108, 92, 231, 0.4);
        }
        
        .btn-neon {
            background-color: var(--secondary-color);
            color: var(--dark-color);
            border: none;
            padding: 10px 25px;
            border-radius: 50px;
            font-weight: 600;
            transition: all 0.3s;
            box-shadow: 0 4px 15px rgba(0, 206, 201, 0.3);
        }
        
        .btn-neon:hover {
            background-color: #00b5b2;
            color: white;
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0, 206, 201, 0.4);
        }
        
        .btn-dark {
            background-color: var(--dark-color);
            color: white;
            border: none;
            padding: 10px 25px;
            border-radius: 50px;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-dark:hover {
            background-color: #1e272e;
            color: white;
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .action-buttons {
            margin-top: 3rem;
            padding-top: 2rem;
            border-top: 1px solid rgba(0, 0, 0, 0.1);
        }
        
        @media (max-width: 768px) {
            .page-header {
                padding: 1.5rem 0;
            }
            
            .card-body {
                padding: 1.5rem;
            }
        }
        
        /* Animation */
        .animate-float {
            animation: float 6s ease-in-out infinite;
        }
        
        @keyframes float {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
            100% { transform: translateY(0px); }
        }
    </style>
</head>
<body class="admin-body">
    <div class="container py-5 animate__animated animate__fadeIn">
        <div class="page-header text-center animate__animated animate__fadeInDown">
            <h1 class="neon-violet mb-3"><?= htmlspecialchars($filiere['niveau_nom']) ?> - <?= htmlspecialchars($filiere['nom']) ?></h1>
            <p class="lead text-white opacity-75">Gestion des salles de formation</p>
        </div>
        
        <div class="text-end mb-5">
            <a href="generer_emplois.php?filiere_id=<?= $filiere_id ?>" class="btn btn-emploi animate__animated animate__pulse animate__infinite animate__slower">
                <i class="bi bi-file-earmark-pdf-fill"></i> Générer Emplois du Temps
            </a>
        </div>
        
        <div class="row g-4">
            <?php foreach ($salles as $salle): ?>
            <div class="col-md-4 animate__animated animate__fadeInUp">
                <div class="card salle-card h-100">
                    <div class="card-body text-center d-flex flex-column">
                        <div class="mb-auto">
                            <h3 class="card-title">Salle <?= htmlspecialchars($salle['nom']) ?></h3>
                            <p class="card-text">
                                <span class="badge bg-<?= $salle['formation'] == 'FI' ? 'primary' : 'warning' ?> text-uppercase">
                                    <?= $salle['formation'] == 'FI' ? 'Formation Initiale' : 'Formation Alternance' ?>
                                </span>
                            </p>
                        </div>
                        <div class="mt-3">
                            <a href="salle_detail.php?id=<?= $salle['id'] ?>" class="btn btn-violet">
                                <i class="bi bi-eye-fill"></i> Voir détails
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="action-buttons text-center">
            <a href="create_salle.php?filiere_id=<?= $filiere_id ?>" class="btn btn-neon me-3">
                <i class="bi bi-plus-circle-fill"></i> Créer une salle
            </a>
            <a href="niveau.php?id=<?= $filiere['niveau_id'] ?>" class="btn btn-dark">
                <i class="bi bi-arrow-left"></i> Retour
            </a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
</body>
</html>