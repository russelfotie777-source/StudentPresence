<?php 
include 'includes/db.php';
$niveau_id = $_GET['id'] ?? 0;

// Récupérer le niveau
$stmt = $pdo->prepare("SELECT * FROM niveaux WHERE id = ?");
$stmt->execute([$niveau_id]);
$niveau = $stmt->fetch();

// Récupérer les filières
$stmt = $pdo->prepare("SELECT * FROM filieres WHERE niveau_id = ?");
$stmt->execute([$niveau_id]);
$filieres = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - <?= htmlspecialchars($niveau['nom']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        /* Couleurs cosmiques */
:root {
    --cosmic-dark: #0f0e1a;
    --cosmic-purple: #6a0dad;
    --cosmic-pink: #ff00ff;
    --cosmic-blue: #00ffff;
    --cosmic-white: #e0e0ff;
}

/* Fond cosmique animé */
.admin-body {
    background: var(--cosmic-dark);
    background-image: 
        radial-gradient(circle at 20% 30%, rgba(106, 13, 173, 0.15) 0%, transparent 30%),
        radial-gradient(circle at 80% 70%, rgba(0, 255, 255, 0.15) 0%, transparent 30%);
    background-attachment: fixed;
    color: var(--cosmic-white);
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    min-height: 100vh;
    position: relative;
    overflow-x: hidden;
}

/* Effet de particules cosmiques (pseudo-éléments) */
.admin-body::before {
    content: "";
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-image: 
        radial-gradient(circle at 10% 20%, var(--cosmic-pink) 0%, transparent 2%),
        radial-gradient(circle at 90% 40%, var(--cosmic-blue) 0%, transparent 2%),
        radial-gradient(circle at 50% 80%, var(--cosmic-purple) 0%, transparent 2%);
    background-size: 200% 200%;
    animation: cosmicParticles 20s infinite alternate;
    z-index: -1;
    opacity: 0.3;
}

@keyframes cosmicParticles {
    0% { background-position: 0% 0%; }
    100% { background-position: 100% 100%; }
}

/* Titre néon amélioré */
.neon-violet {
    color: var(--cosmic-white);
    text-shadow: 
        0 0 5px var(--cosmic-white),
        0 0 10px var(--cosmic-purple),
        0 0 20px var(--cosmic-purple),
        0 0 30px var(--cosmic-pink);
    font-weight: 700;
    letter-spacing: 2px;
    position: relative;
    padding-bottom: 10px;
}

.neon-violet::after {
    content: "";
    position: absolute;
    bottom: 0;
    left: 50%;
    transform: translateX(-50%);
    width: 100px;
    height: 3px;
    background: linear-gradient(90deg, transparent, var(--cosmic-blue), transparent);
    border-radius: 50%;
    filter: blur(1px);
}

/* Cartes de filières cosmiques */
.filiere-card {
    background: rgba(15, 14, 26, 0.7);
    border: 1px solid rgba(106, 13, 173, 0.3);
    border-radius: 15px;
    backdrop-filter: blur(5px);
    transition: all 0.3s ease;
    overflow: hidden;
    box-shadow: 0 5px 15px rgba(106, 13, 173, 0.2);
    position: relative;
}

.filiere-card::before {
    content: "";
    position: absolute;
    top: -2px;
    left: -2px;
    right: -2px;
    bottom: -2px;
    background: linear-gradient(45deg, var(--cosmic-purple), var(--cosmic-blue), var(--cosmic-pink));
    z-index: -1;
    border-radius: 16px;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.filiere-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(106, 13, 173, 0.4);
}

.filiere-card:hover::before {
    opacity: 0.7;
}

.filiere-card .card-title {
    color: var(--cosmic-white);
    font-weight: 600;
    margin-bottom: 20px;
    position: relative;
}

.filiere-card .card-title::after {
    content: "";
    position: absolute;
    bottom: -10px;
    left: 50%;
    transform: translateX(-50%);
    width: 50px;
    height: 2px;
    background: linear-gradient(90deg, transparent, var(--cosmic-blue), transparent);
}

/* Boutons cosmiques */
.btn-violet {
    background: var(--cosmic-purple);
    border: none;
    color: white;
    padding: 8px 20px;
    border-radius: 25px;
    font-weight: 500;
    transition: all 0.3s ease;
    box-shadow: 0 0 10px rgba(106, 13, 173, 0.5);
    position: relative;
    overflow: hidden;
}

.btn-violet:hover {
    background: var(--cosmic-pink);
    transform: translateY(-2px);
    box-shadow: 0 0 20px rgba(255, 0, 255, 0.7);
}

.btn-violet::before {
    content: "";
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: linear-gradient(
        to bottom right,
        rgba(255, 255, 255, 0) 45%,
        rgba(255, 255, 255, 0.3) 50%,
        rgba(255, 255, 255, 0) 55%
    );
    transform: rotate(30deg);
    transition: all 0.5s ease;
}

.btn-violet:hover::before {
    left: 100%;
}

.btn-neon {
    background: transparent;
    border: 2px solid var(--cosmic-blue);
    color: var(--cosmic-blue);
    padding: 8px 25px;
    border-radius: 25px;
    font-weight: 500;
    transition: all 0.3s ease;
    box-shadow: 0 0 10px rgba(0, 255, 255, 0.3), inset 0 0 10px rgba(0, 255, 255, 0.1);
    position: relative;
    overflow: hidden;
}

.btn-neon:hover {
    background: rgba(0, 255, 255, 0.1);
    color: var(--cosmic-white);
    box-shadow: 0 0 20px rgba(0, 255, 255, 0.5), inset 0 0 15px rgba(0, 255, 255, 0.2);
    transform: translateY(-2px);
}

.btn-dark {
    background: rgba(15, 14, 26, 0.7);
    border: 1px solid rgba(255, 255, 255, 0.1);
    color: var(--cosmic-white);
    transition: all 0.3s ease;
}

.btn-dark:hover {
    background: rgba(106, 13, 173, 0.3);
    border-color: var(--cosmic-purple);
    color: var(--cosmic-white);
}

/* Effet de lumière cosmique sur les cartes */
@keyframes cosmicGlow {
    0% { box-shadow: 0 0 10px rgba(106, 13, 173, 0.5); }
    50% { box-shadow: 0 0 20px rgba(0, 255, 255, 0.7); }
    100% { box-shadow: 0 0 10px rgba(255, 0, 255, 0.5); }
}

.filiere-card {
    animation: cosmicGlow 8s infinite alternate;
}

/* Responsive */
@media (max-width: 768px) {
    .filiere-card {
        margin-bottom: 20px;
    }
}


/* Animation d'étoiles */
.stars, .twinkling {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    width: 100%;
    height: 100%;
    display: block;
}

.stars {
    background: #000 url('https://i.imgur.com/YKY28eT.png') repeat top center;
    z-index: -2;
}

.twinkling {
    background: transparent url('https://i.imgur.com/XYMF4ca.png') repeat top center;
    z-index: -1;
    animation: twinkle 200s linear infinite;
}

@keyframes twinkle {
    from { background-position: 0 0; }
    to { background-position: -10000px 5000px; }
}
    </style>
</head>
<body class="admin-body">
    <div class="container py-5">
        <h1 class="text-center mb-5 neon-violet"><?= htmlspecialchars($niveau['nom']) ?></h1>
        
        <div class="row">
            <?php foreach ($filieres as $filiere): ?>
            <div class="col-md-4 mb-4">
                <div class="card filiere-card">
                    <div class="card-body text-center">
                        <h3 class="card-title"><?= htmlspecialchars($filiere['nom']) ?></h3>
                        <a href="filiere.php?id=<?= $filiere['id'] ?>" class="btn btn-violet">Voir les salles</a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="text-center mt-4">
            <a href="create_filiere.php?niveau_id=<?= $niveau_id ?>" class="btn btn-neon">Créer une filière</a>
            <a href="index.php" class="btn btn-dark">Retour</a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>