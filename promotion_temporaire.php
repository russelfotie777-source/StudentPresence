<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Vérification de la connexion et du rôle enseignant
if (!isLoggedIn() || !isTeacher()) {
    header('Location: index.php');
    exit();
}

// Traitement du formulaire de promotion
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['promouvoir'])) {
        $etudiant_id = intval($_POST['etudiant_id']);
        $duree_minutes = intval($_POST['duree_minutes']);
        
        // Vérifier que l'étudiant existe et est bien un étudiant
        $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ? AND grade = 'Etudiant'");
        $stmt->execute([$etudiant_id]);
        
        if ($stmt->fetch()) {
            $date_fin = date('Y-m-d H:i:s', strtotime("+$duree_minutes minutes"));
            
            // Enregistrer la promotion temporaire
            $stmt = $pdo->prepare("INSERT INTO promotions_temporaires 
                                  (etudiant_id, promoteur_id, date_fin, duree_minutes) 
                                  VALUES (?, ?, ?, ?)");
            $stmt->execute([$etudiant_id, $_SESSION['user_id'], $date_fin, $duree_minutes]);
            
            $_SESSION['message'] = "Promotion temporaire effectuée avec succès!";
            header("Location: promotion_temporaire.php");
            exit();
        } else {
            $_SESSION['error'] = "Étudiant introuvable ou non éligible";
            header("Location: promotion_temporaire.php");
            exit();
        }
    }
}

// Récupération du terme de recherche
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Construction de la requête de recherche
$query = "SELECT u.id, u.name, u.classroom, f.nom as filiere, n.nom as niveau
          FROM users u
          LEFT JOIN filieres f ON u.filiere_id = f.id
          LEFT JOIN niveaux n ON u.niveau_id = n.id
          WHERE u.grade = 'Etudiant'";

$params = [];

if (!empty($search)) {
    $query .= " AND (u.name LIKE ? OR u.classroom LIKE ? OR f.nom LIKE ? OR n.nom LIKE ?)";
    $search_term = "%$search%";
    $params = array_fill(0, 4, $search_term);
}

$query .= " ORDER BY u.classroom, u.name";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$etudiants = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les promotions actives
$query = "SELECT p.*, u.name as etudiant_nom, u.classroom
          FROM promotions_temporaires p
          JOIN users u ON p.etudiant_id = u.id
          WHERE p.date_fin > NOW()";
$promotions_actives = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Promotions Temporaires</title>
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
        
        .search-container {
            margin-bottom: 1.5rem;
            position: relative;
        }
        
        .search-input {
            width: 100%;
            padding: 0.75rem 1rem 0.75rem 3rem;
            border-radius: 30px;
            border: 1px solid var(--violet-medium);
            background: rgba(10, 10, 10, 0.7);
            color: var(--white);
            font-size: 1rem;
            transition: all 0.3s ease;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%23b388ff' viewBox='0 0 16 16'%3E%3Cpath d='M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: 1rem center;
            background-size: 1.2rem;
        }
        
        .search-input:focus {
            outline: none;
            border-color: var(--violet-neon);
            box-shadow: 0 0 0 2px rgba(179, 136, 255, 0.3);
        }
        
        .etudiant-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
            background: rgba(10, 10, 10, 0.7);
            border-radius: 8px;
            overflow: hidden;
        }
        
        .etudiant-table th, 
        .etudiant-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid var(--violet-medium);
        }
        
        .etudiant-table th {
            background-color: var(--violet-dark);
            color: var(--violet-neon);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.8rem;
            letter-spacing: 1px;
        }
        
        .etudiant-table tr:hover {
            background-color: rgba(123, 75, 158, 0.1);
        }
        
        .promo-btn {
            padding: 0.5rem 1rem;
            background-color: #9c27b0;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .promo-btn:hover {
            background-color: #7b1fa2;
            transform: translateY(-2px);
        }
        
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
        
        .badge {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .badge-success {
            background-color: #4caf50;
            color: white;
        }
        
        .badge-warning {
            background-color: #ff9800;
            color: white;
        }
        
        .badge-info {
            background-color: #2196F3;
            color: white;
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
        
        @media (max-width: 768px) {
            .etudiant-table {
                display: block;
                overflow-x: auto;
            }
            
            .etudiant-table th, 
            .etudiant-table td {
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
            <a href="dashboard.php"><i class="fas fa-arrow-left"></i> Retour</a>
        </div>
    </div>
    
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-user-shield"></i> Promotions Temporaires</h1>
            <p>Promouvoir des étudiants au rôle de délégué pour une durée limitée</p>
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
            <h2><i class="fas fa-search"></i> Rechercher un étudiant</h2>
            <form method="GET" action="promotion_temporaire.php">
                <div class="search-container">
                    <input type="text" 
                           class="search-input" 
                           name="search" 
                           placeholder="Rechercher par nom, salle, filière ou niveau..." 
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
            </form>
        </div>
        
        <div class="card">
            <h2><i class="fas fa-users"></i> Liste des Étudiants</h2>
            <?php if (empty($etudiants)): ?>
                <p>Aucun étudiant trouvé</p>
            <?php else: ?>
                <table class="etudiant-table">
                    <thead>
                        <tr>
                            <th>Nom</th>
                            <th>Salle</th>
                            <th>Filière</th>
                            <th>Niveau</th>
                            <th>Statut</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($etudiants as $etudiant): 
                            $promotion_active = null;
                            foreach ($promotions_actives as $promo) {
                                if ($promo['etudiant_id'] == $etudiant['id']) {
                                    $promotion_active = $promo;
                                    break;
                                }
                            }
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($etudiant['name']); ?></td>
                            <td><?php echo htmlspecialchars($etudiant['classroom']); ?></td>
                            <td><?php echo htmlspecialchars($etudiant['filiere'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($etudiant['niveau'] ?? '-'); ?></td>
                            <td>
                                <?php if ($promotion_active): ?>
                                    <span class="badge badge-success">
                                        Délégué temporaire (expire: <?php echo date('H:i', strtotime($promotion_active['date_fin'])); ?>)
                                    </span>
                                <?php else: ?>
                                    <span class="badge">Étudiant</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!$promotion_active): ?>
                                    <button onclick="openPromoModal(<?php echo $etudiant['id']; ?>)" class="promo-btn">
                                        <i class="fas fa-user-shield"></i> Promouvoir
                                    </button>
                                <?php else: ?>
                                    <span class="badge badge-info">Déjà promu</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        
        <div class="card">
            <h2><i class="fas fa-history"></i> Promotions Actives</h2>
            <?php if (empty($promotions_actives)): ?>
                <p>Aucune promotion active pour le moment.</p>
            <?php else: ?>
                <table class="etudiant-table">
                    <thead>
                        <tr>
                            <th>Étudiant</th>
                            <th>Salle</th>
                            <th>Durée</th>
                            <th>Expire à</th>
                            <th>Temps restant</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($promotions_actives as $promo): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($promo['etudiant_nom']); ?></td>
                            <td><?php echo htmlspecialchars($promo['classroom'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($promo['duree_minutes']); ?> minutes</td>
                            <td><?php echo date('d/m/Y H:i', strtotime($promo['date_fin'])); ?></td>
                            <td>
                                <?php 
                                    $now = new DateTime();
                                    $fin = new DateTime($promo['date_fin']);
                                    $interval = $now->diff($fin);
                                    echo '<span class="badge badge-warning">' . $interval->format('%h h %i min') . '</span>';
                                ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Modal de promotion -->
    <div id="promoModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closePromoModal()">&times;</span>
            <h3 class="modal-title"><i class="fas fa-user-shield"></i> Promouvoir un Étudiant</h3>
            <form id="promoForm" method="POST">
                <input type="hidden" name="etudiant_id" id="modalEtudiantId">
                <input type="hidden" name="promouvoir" value="1">
                <div class="form-group">
                    <label for="duree_minutes">Durée de la promotion (minutes):</label>
                    <input type="number" id="duree_minutes" name="duree_minutes" min="1" max="1440" required>
                </div>
                <div class="modal-footer">
                    <button type="button" class="modal-btn modal-btn-secondary" onclick="closePromoModal()">Annuler</button>
                    <button type="submit" class="modal-btn modal-btn-primary">Confirmer</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Fonctions pour gérer la modal de promotion
        function openPromoModal(etudiantId) {
            document.getElementById('modalEtudiantId').value = etudiantId;
            document.getElementById('promoModal').style.display = 'block';
        }
        
        function closePromoModal() {
            document.getElementById('promoModal').style.display = 'none';
        }
        
        // Fermer la modal si on clique en dehors
        window.onclick = function(event) {
            const modal = document.getElementById('promoModal');
            if (event.target === modal) {
                closePromoModal();
            }
        }
        
        // Recherche en temps réel (optionnel)
        document.querySelector('.search-input').addEventListener('input', function() {
            if (this.value.length > 2 || this.value.length === 0) {
                this.form.submit();
            }
        });
    </script>
</body>
</html>