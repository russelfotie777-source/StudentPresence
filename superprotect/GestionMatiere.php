<?php
$servername = "sql106.infinityfree.com";
$username = "if0_39769258";
$password = "EyQcRNL5jRGZtor";
$dbname = "if0_39769258_gestion";

// Créer une connexion
$conn = new mysqli($servername, $username, $password, $dbname);

// Vérifier la connexion
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fonction pour ajouter une matière
function addMatiere($conn, $nom, $code) {
    $sql = "INSERT INTO matieres (nom, code) VALUES (?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $nom, $code);
    return $stmt->execute();
}

// Fonction pour mettre à jour une matière
function updateMatiere($conn, $id, $nom, $code) {
    $sql = "UPDATE matieres SET nom = ?, code = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssi", $nom, $code, $id);
    return $stmt->execute();
}

// Fonction pour supprimer une matière
function deleteMatiere($conn, $id) {
    $sql = "DELETE FROM matieres WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    return $stmt->execute();
}

// Gérer les actions du formulaire
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['add'])) {
        addMatiere($conn, $_POST['nom'], $_POST['code']);
    } elseif (isset($_POST['update'])) {
        updateMatiere($conn, $_POST['id'], $_POST['nom'], $_POST['code']);
    } elseif (isset($_POST['delete'])) {
        deleteMatiere($conn, $_POST['id']);
    }
    // Redirection pour éviter la resoumission du formulaire
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Sélectionner toutes les matières
$sql = "SELECT id, nom, code FROM matieres ORDER BY nom";
$result = $conn->query($sql);

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Matières</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #4e73df;
            --secondary-color: #6f42c1;
            --success-color: #1cc88a;
            --danger-color: #e74a3b;
            --warning-color: #f6c23e;
            --light-color: #f8f9fc;
            --dark-color: #5a5c69;
        }
        
        body {
            background-color: #f8f9fc;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .title {
            color: var(--primary-color);
            font-weight: 700;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.1);
            padding-bottom: 10px;
            border-bottom: 2px solid var(--primary-color);
            display: inline-block;
        }
        
        .card-presence {
            border-radius: 10px;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            border: none;
            margin-bottom: 2rem;
        }
        
        .card-header {
            background: linear-gradient(180deg, var(--primary-color), var(--secondary-color));
            color: white;
            border-radius: 10px 10px 0 0 !important;
            padding: 1rem 1.35rem;
            border-bottom: 1px solid #e3e6f0;
        }
        
        .table-container {
            overflow-x: auto;
        }
        
        .table-presence {
            border-collapse: separate;
            border-spacing: 0;
            width: 100%;
        }
        
        .table-presence th {
            background-color: var(--primary-color);
            color: white;
            font-weight: 600;
            padding: 0.75rem;
            vertical-align: middle;
            border: none;
        }
        
        .table-presence td {
            padding: 0.75rem;
            vertical-align: middle;
            border-top: 1px solid #e3e6f0;
        }
        
        .table-presence tbody tr {
            transition: all 0.15s ease;
        }
        
        .table-presence tbody tr:hover {
            background-color: rgba(78, 115, 223, 0.05);
            transform: translateY(-2px);
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }
        
        .btn-edit {
            background-color: var(--warning-color);
            border-color: var(--warning-color);
            color: #000;
        }
        
        .btn-edit:hover {
            background-color: #e0a800;
            border-color: #d39e00;
            color: #000;
        }
        
        .btn-delete {
            background-color: var(--danger-color);
            border-color: var(--danger-color);
            color: white;
        }
        
        .btn-delete:hover {
            background-color: #c53030;
            border-color: #b21f2d;
            color: white;
        }
        
        .btn-add {
            background: linear-gradient(180deg, var(--success-color), #17a673);
            border: none;
            color: white;
            padding: 10px 20px;
            border-radius: 5px;
            font-weight: 600;
            transition: all 0.3s;
            margin-top: 20px;
        }
        
        .btn-add:hover {
            transform: translateY(-2px);
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            background: linear-gradient(180deg, #17a673, var(--success-color));
        }
        
        .btn-group .btn {
            margin-right: 5px;
        }
        
        /* Popup Styles */
        .popup-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }
        
        .popup-content {
            background-color: white;
            padding: 2rem;
            border-radius: 10px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            position: relative;
            animation: popupFadeIn 0.3s;
        }
        
        @keyframes popupFadeIn {
            from {
                opacity: 0;
                transform: translateY(-50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .close-btn {
            position: absolute;
            top: 15px;
            right: 15px;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--dark-color);
            transition: color 0.3s;
        }
        
        .close-btn:hover {
            color: var(--danger-color);
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            font-weight: 600;
            color: var(--dark-color);
            margin-bottom: 0.5rem;
        }
        
        .form-control {
            border: 1px solid #d1d3e2;
            border-radius: 0.35rem;
            padding: 0.75rem;
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(78, 115, 223, 0.25);
        }
        
        .btn-neon {
            background: linear-gradient(180deg, var(--primary-color), var(--secondary-color));
            border: none;
            color: white;
            padding: 12px;
            border-radius: 5px;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-neon:hover {
            transform: translateY(-2px);
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            background: linear-gradient(180deg, var(--secondary-color), var(--primary-color));
            color: white;
        }
        
        .alert-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1050;
            max-width: 350px;
        }
        
        .badge-code {
            background-color: var(--secondary-color);
            color: white;
            padding: 0.3em 0.6em;
            border-radius: 0.25rem;
            font-size: 0.85em;
            font-weight: 600;
        }
        
        @media (max-width: 768px) {
            .btn-group {
                display: flex;
                flex-direction: column;
            }
            
            .btn-group .btn {
                margin-bottom: 5px;
                margin-right: 0;
            }
            
            .popup-content {
                width: 95%;
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>

<?php include 'includes/admin-header.php'; ?>

<div class="container py-5">
    <h1 class="text-center mb-5 title">Gestion des Matières</h1>
    
    <div class="card card-presence">
        <div class="card-header">
            <h5 class="m-0"><i class="fas fa-book me-2"></i>Liste des matières</h5>
        </div>
        <div class="card-body">
            <div class="table-container">
                <table class="table table-presence table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nom de la matière</th>
                            <th>Code</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if ($result->num_rows > 0) {
                            while($row = $result->fetch_assoc()) {
                                echo "<tr data-id='{$row['id']}' data-nom='{$row['nom']}' data-code='{$row['code']}'>";
                                echo "<td>{$row['id']}</td>";
                                echo "<td>{$row['nom']}</td>";
                                echo "<td><span class='badge badge-code'>{$row['code']}</span></td>";
                                echo "<td>
                                        <div class='btn-group'>
                                            <button class='btn btn-sm btn-edit edit-btn' data-bs-toggle='tooltip' title='Modifier'><i class='fas fa-edit me-1'></i> Modifier</button>
                                            <form method='post' onsubmit='return confirm(\"Voulez-vous vraiment supprimer cette matière ?\");'>
                                                <input type='hidden' name='id' value='{$row['id']}'>
                                                <button type='submit' name='delete' class='btn btn-sm btn-delete delete-btn' data-bs-toggle='tooltip' title='Supprimer'><i class='fas fa-trash-alt me-1'></i> Supprimer</button>
                                            </form>
                                        </div>
                                      </td>";
                                echo "</tr>";
                            }
                        } else {
                            echo "<tr><td colspan='4' class='text-center py-4'>Aucune matière trouvée.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>

            <div class="text-center mt-4">
                <button class="btn btn-add add-btn" id="openAddPopup"><i class="fas fa-plus me-2"></i> Ajouter une Matière</button>
            </div>
        </div>
    </div>
    
    <div id="matierePopup" class="popup-overlay">
        <div class="popup-content">
            <span class="close-btn">&times;</span>
            <h3 id="popup-title" class="mb-4"><i class="fas fa-book me-2"></i>Ajouter une Matière</h3>
            <form id="matiereForm" method="post">
                <input type="hidden" name="id" id="matiereId">
                <div class="form-group">
                    <label for="nom">Nom de la matière:</label>
                    <input type="text" name="nom" id="matiereNom" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="code">Code de la matière:</label>
                    <input type="text" name="code" id="matiereCode" class="form-control" required>
                </div>
                <div class="d-flex justify-content-between mt-4">
                    <button type="button" id="cancelBtn" class="btn btn-secondary">Annuler</button>
                    <button type="submit" name="add" id="submitBtn" class="btn btn-neon"><i class="fas fa-save me-2"></i> Enregistrer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Elements du popup
        const popup = document.getElementById('matierePopup');
        const openAddPopupBtn = document.getElementById('openAddPopup');
        const closeBtn = document.querySelector('.close-btn');
        const cancelBtn = document.getElementById('cancelBtn');
        const popupTitle = document.getElementById('popup-title');
        const matiereForm = document.getElementById('matiereForm');
        const matiereId = document.getElementById('matiereId');
        const matiereNom = document.getElementById('matiereNom');
        const matiereCode = document.getElementById('matiereCode');
        const submitBtn = document.getElementById('submitBtn');
        
        // Ouvrir le popup pour ajouter
        openAddPopupBtn.addEventListener('click', function() {
            popupTitle.innerHTML = '<i class="fas fa-plus me-2"></i>Ajouter une Matière';
            matiereForm.reset();
            matiereId.value = '';
            submitBtn.name = 'add';
            submitBtn.innerHTML = '<i class="fas fa-save me-2"></i> Enregistrer';
            popup.style.display = 'flex';
        });
        
        // Ouvrir le popup pour modifier
        const editButtons = document.querySelectorAll('.edit-btn');
        editButtons.forEach(button => {
            button.addEventListener('click', function() {
                const row = this.closest('tr');
                const id = row.getAttribute('data-id');
                const nom = row.getAttribute('data-nom');
                const code = row.getAttribute('data-code');
                
                popupTitle.innerHTML = '<i class="fas fa-edit me-2"></i>Modifier une Matière';
                matiereId.value = id;
                matiereNom.value = nom;
                matiereCode.value = code;
                submitBtn.name = 'update';
                submitBtn.innerHTML = '<i class="fas fa-save me-2"></i> Mettre à jour';
                
                popup.style.display = 'flex';
            });
        });
        
        // Fermer le popup
        function closePopup() {
            popup.style.display = 'none';
        }
        
        closeBtn.addEventListener('click', closePopup);
        cancelBtn.addEventListener('click', closePopup);
        
        // Fermer en cliquant en dehors du contenu
        popup.addEventListener('click', function(e) {
            if (e.target === popup) {
                closePopup();
            }
        });
        
        // Initialiser les tooltips Bootstrap
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    });
</script>
</body>
</html>