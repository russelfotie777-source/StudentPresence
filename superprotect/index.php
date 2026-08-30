<?php
session_start();

// Vérifier si l'utilisateur est connecté, sinon le rediriger vers la page de connexion
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit();
}
?>
<style>
    .btn-neon-orange {
        background: linear-gradient(90deg, #ff7e5f 0%, #feb47b 100%);
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 30px;
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: 1px;
        transition: all 0.3s;
        box-shadow: 0 0 15px rgba(255, 126, 95, 0.5);
    }

    .logo-container {
        text-align: center;
        margin-bottom: 2rem;
        height: 80px;
    }

    .logo-container img {
        max-width: 150px;
        filter: drop-shadow(0 0 10px #fff);
        transition: transform 0.5s ease-in-out;
    }
    
    .logo-container img:hover {
        transform: rotate(10deg) scale(1.1);
    }

    .d-flex.justify-content-center.mb-4.gap-3 {
        flex-wrap: wrap;
    }

    @media (max-width: 768px) {
        .d-flex.justify-content-center.mb-4.gap-3 {
            flex-direction: column;
            gap: 15px !important;
        }
        .container {
            padding-top: 3rem;
            padding-bottom: 3rem;
        }
    }

    .btn-neon-orange:hover {
        color: white;
        transform: translateY(-3px);
        box-shadow: 0 0 20px rgba(255, 126, 95, 0.8);
    }

    .btn-neon-purple {
        background: linear-gradient(90deg, #6e48aa 0%, #9d50bb 100%);
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 30px;
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: 1px;
        transition: all 0.3s;
        box-shadow: 0 0 15px rgba(110, 72, 170, 0.5);
    }

    .btn-neon-purple:hover {
        color: white;
        transform: translateY(-3px);
        box-shadow: 0 0 20px rgba(110, 72, 170, 0.8);
    }

    .neon-blue {
        color: #fff;
        text-shadow: 0 0 10px #2575fc, 0 0 20px #2575fc, 0 0 30px #2575fc;
    }

    .niveau-card {
        background: rgba(15, 14, 26, 0.7);
        border: 1px solid rgba(106, 13, 173, 0.3);
        border-radius: 15px;
        backdrop-filter: blur(5px);
        transition: all 0.3s ease;
        box-shadow: 0 5px 15px rgba(106, 13, 173, 0.2);
        height: 100%;
    }

    .niveau-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 25px rgba(106, 13, 173, 0.4);
    }

    .btn-cosmic-green {
        background: linear-gradient(90deg, #00b09b 0%, #96c93d 100%);
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 30px;
        font-weight: bold;
        transition: all 0.3s;
        box-shadow: 0 0 15px rgba(0, 176, 155, 0.5);
    }

    .btn-cosmic-green:hover {
        color: white;
        transform: translateY(-3px);
        box-shadow: 0 0 20px rgba(0, 176, 155, 0.8);
    }
</style>
<?php include 'includes/admin-header.php'; ?>
        <div class="logo-container">
        <img src="iut.png" alt="IUT Logo">
    </div>
<div class="container py-5">
    <h1 class="text-center mb-5 neon-violet">Administration des Niveaux</h1>
    <div class="d-flex justify-content-center mb-4 gap-3">
        <a href="suiviHeurProf.php" class="btn btn-neon-orange me-2">
            <i class="fas fa-chalkboard-teacher me-2"></i> Suivi Heures Profs
        </a>
        <a href="presence.php" class="btn btn-neon-purple me-2">
            <i class="fas fa-clipboard-list me-2"></i> Liste des Présences
        </a>
        <a href="GestionMatiere.php" class="btn btn-neon-purple me-2">
    <i class="fas fa-book me-2"></i> Gérer les Matières
      </a>
        <a href="create_niveau.php" class="btn btn-cosmic-green">
            <i class="fas fa-plus-circle me-2"></i> Créer un Niveau
        </a>:
    </div>
    
    <div class="row">
        <?php
        $stmt = $pdo->query("SELECT * FROM niveaux");
        while ($niveau = $stmt->fetch()):
        ?>
        <div class="col-md-4 mb-4">
            <div class="card niveau-card h-100">
                <div class="card-body text-center d-flex flex-column">
                    <div class="mb-auto">
                        <h3 class="card-title" style="color: #9d50bb;"><?= htmlspecialchars($niveau['nom']) ?></h3>
                    </div>
                    <div class="mt-3">
                        <a href="niveau.php?id=<?= $niveau['id'] ?>" class="btn btn-violet">
                            <i class="fas fa-eye me-1"></i> Voir les filières
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php endwhile; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
</body>
</html>