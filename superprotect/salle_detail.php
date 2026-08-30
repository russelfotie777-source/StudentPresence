<?php 

include 'includes/db.php';

$salle_id = $_GET['id'] ?? 0;

$stmt = $pdo->prepare("SELECT s.*, f.nom as filiere_nom, n.nom as niveau_nom 
                      FROM salles s 
                      JOIN filieres f ON s.filiere_id = f.id 
                      JOIN niveaux n ON f.niveau_id = n.id 
                      WHERE s.id = ?");
$stmt->execute([$salle_id]);
$salle = $stmt->fetch();

$semaine_id = $_GET['semaine_id'] ?? 0;

// Récupérer toutes les semaines pour le sélecteur
$semaines = $pdo->query("SELECT * FROM semaines ORDER BY numero")->fetchAll();

// Si aucune semaine n'est sélectionnée, prendre la première par défaut
if ($semaine_id == 0 && !empty($semaines)) {
    $semaine_id = $semaines[0]['id'];
}

// Récupérer les séances pour la salle et la semaine sélectionnée
$stmt = $pdo->prepare("SELECT sc.*, m.nom as matiere_nom, u.name as enseignant_nom
                      FROM seances sc
                      JOIN cours c ON sc.cours_id = c.id
                      JOIN matieres m ON c.matiere_id = m.id
                      JOIN users u ON sc.enseignant_id = u.id
                      WHERE sc.salle_id = ? AND sc.semaine_id = ?
                      ORDER BY sc.jour, sc.heure_debut");
$stmt->execute([$salle_id, $semaine_id]);
$seances = $stmt->fetchAll();

// Requête pour récupérer tous les cours disponibles
// Ou utiliser LEFT JOIN pour inclure toutes les matières même sans cours :
$cours = $pdo->query("SELECT id, nom as matiere_nom FROM matieres ORDER BY nom")->fetchAll();

$enseignants = $pdo->query("SELECT id, name FROM users WHERE grade = 'Enseignant'")->fetchAll();
$matieres = $pdo->query("SELECT id, nom FROM matieres ORDER BY nom")->fetchAll();

if (isset($_POST['vider_salle'])) {
    try {
        $pdo->beginTransaction();
        
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
        
        $stmt = $pdo->prepare("DELETE pe FROM presences_etudiants pe 
                              JOIN seances s ON pe.seance_id = s.id 
                              WHERE s.salle_id = ?");
        $stmt->execute([$salle_id]);
        
        $stmt = $pdo->prepare("DELETE p FROM pushes p 
                              JOIN seances s ON p.seance_id = s.id 
                              WHERE s.salle_id = ?");
        $stmt->execute([$salle_id]);
        
        $stmt = $pdo->prepare("DELETE pt FROM promotions_temporaires pt 
                              JOIN seances s ON pt.promoteur_id = s.enseignant_id 
                              WHERE s.salle_id = ?");
        $stmt->execute([$salle_id]);
        
        $stmt = $pdo->prepare("DELETE FROM seances WHERE salle_id = ?");
        $stmt->execute([$salle_id]);
        
        $stmt = $pdo->prepare("DELETE FROM emplois_temps WHERE salle_id = ?");
        $stmt->execute([$salle_id]);
        
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
        
        $pdo->commit();
        
        header("Location: salle_detail.php?id=$salle_id&success=La programmation de la salle a été réinitialisée avec succès");
        exit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
        header("Location: salle_detail.php?id=$salle_id&error=Erreur lors de la réinitialisation: " . $e->getMessage());
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Salle <?= htmlspecialchars($salle['nom']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
body.admin-body {
    background: 
        radial-gradient(circle at 20% 30%, rgba(41, 5, 82, 0.8) 0%, transparent 25%),
        radial-gradient(circle at 80% 70%, rgba(0, 87, 146, 0.6) 0%, transparent 25%),
        linear-gradient(135deg, #0f0c29 0%, #1a1a3a 50%, #24243e 100%);
    background-size: 400% 400%;
    animation: cosmicBackground 20s ease infinite;
    min-height: 100vh;
    color: #e0e0e0;
    font-family: 'Poppins', 'Segoe UI', sans-serif;
    overflow-x: hidden;
}

@keyframes cosmicBackground {
    0% { background-position: 0% 0%; }
    50% { background-position: 100% 100%; }
    100% { background-position: 0% 0%; }
}

body.admin-body::before {
    content: '';
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: 
        radial-gradient(circle at 20% 30%, rgba(255, 255, 255, 0.8) 1px, transparent 1px),
        radial-gradient(circle at 80% 70%, rgba(255, 255, 255, 0.6) 1px, transparent 1px),
        radial-gradient(circle at 40% 80%, rgba(255, 255, 255, 0.7) 1px, transparent 1px);
    background-size: 200px 200px;
    z-index: -1;
    animation: twinkle 10s linear infinite;
}

@keyframes twinkle {
    0%, 100% { opacity: 0.8; }
    50% { opacity: 0.3; }
}

.card {
    background: rgba(255, 255, 255, 0.05);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 16px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
    transition: all 0.4s cubic-bezier(0.25, 0.8, 0.25, 1);
    overflow: hidden;
    position: relative;
    z-index: 1;
}

.card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(135deg, rgba(110, 72, 170, 0.1) 0%, rgba(0, 0, 0, 0) 100%);
    z-index: -1;
    border-radius: inherit;
}

.card:hover {
    transform: translateY(-5px) scale(1.01);
    box-shadow: 0 12px 40px rgba(110, 72, 170, 0.4);
    border-color: rgba(110, 72, 170, 0.3);
}

.seance-card {
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.1) 0%, rgba(255, 255, 255, 0.05) 100%);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 12px;
    margin-bottom: 15px;
    transition: all 0.4s ease;
    position: relative;
    overflow: hidden;
    color: #ffffff;
}

.seance-card::after {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.1), transparent);
    transition: 0.6s;
}

.seance-card:hover::after {
    left: 100%;
}

.seance-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(110, 72, 170, 0.3);
    border-color: rgba(110, 72, 170, 0.4);
}

.seance-card .card-body {
    padding: 1.5rem;
}

.seance-card .card-title {
    color: #ffffff;
    font-weight: 600;
    margin-bottom: 0.5rem;
}

.seance-card .card-text {
    color: rgba(255, 255, 255, 0.8);
    font-size: 0.9rem;
}

.seance-card .text-muted {
    color: rgba(255, 255, 255, 0.6) !important;
}

.neon-violet {
    color: #fff;
    text-shadow: 
        0 0 5px rgba(155, 114, 207, 0.8),
        0 0 10px rgba(110, 72, 170, 0.6),
        0 0 15px rgba(110, 72, 170, 0.4),
        0 0 20px rgba(110, 72, 170, 0.2);
    position: relative;
    display: inline-block;
    font-weight: 700;
    letter-spacing: 1px;
}

.neon-violet::after {
    content: '';
    position: absolute;
    bottom: -8px;
    left: 0;
    width: 100%;
    height: 2px;
    background: linear-gradient(90deg, transparent, #9b72cf, #6e48aa, transparent);
    animation: neonGlow 3s linear infinite;
    background-size: 200% 100%;
}

@keyframes neonGlow {
    0% { background-position: 200% 0; }
    100% { background-position: -200% 0; }
}

.btn-add-seance {
    background: linear-gradient(135deg, rgba(110, 72, 170, 0.8) 0%, rgba(155, 114, 207, 0.8) 100%);
    border: none;
    border-radius: 50px;
    padding: 12px 30px;
    font-weight: 600;
    letter-spacing: 1px;
    text-transform: uppercase;
    box-shadow: 0 4px 15px rgba(110, 72, 170, 0.4);
    transition: all 0.4s ease;
    position: relative;
    overflow: hidden;
    color: white;
}

.btn-add-seance::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
    transition: 0.5s;
}

.btn-add-seance:hover::before {
    left: 100%;
}

.btn-add-seance:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(110, 72, 170, 0.6);
}

.jour-section {
    margin-bottom: 30px;
    padding-bottom: 20px;
    position: relative;
}

.jour-section h4 {
    color: #9b72cf;
    padding-bottom: 10px;
    margin-bottom: 20px;
    position: relative;
    font-weight: 600;
}

.jour-section h4::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    width: 100%;
    height: 1px;
    background: linear-gradient(90deg, transparent, rgba(110, 72, 170, 0.5), transparent);
}

.form-control {
    background: rgba(255, 255, 255, 0.08);
    border: 1px solid rgba(255, 255, 255, 0.15);
    color: #ffffff;
    border-radius: 8px;
    padding: 10px 15px;
    transition: all 0.3s ease;
}

.form-control:focus {
    background: rgba(255, 255, 255, 0.12);
    border-color: #6e48aa;
    box-shadow: 0 0 0 0.25rem rgba(110, 72, 170, 0.25);
    color: #ffffff;
}

.badge-formation {
    background: linear-gradient(135deg, #6e48aa, #9b72cf);
    font-size: 0.75rem;
    padding: 6px 12px;
    border-radius: 50px;
    text-transform: uppercase;
    letter-spacing: 1px;
    font-weight: 600;
    box-shadow: 0 3px 10px rgba(110, 72, 170, 0.3);
    vertical-align: middle;
    margin-left: 10px;
}

.btn-vider {
    background: linear-gradient(135deg, rgba(231, 74, 59, 0.8) 0%, rgba(203, 32, 45, 0.8) 100%);
    border: none;
    border-radius: 50px;
    padding: 10px 20px;
    font-weight: 600;
    letter-spacing: 1px;
    color: white;
    box-shadow: 0 4px 15px rgba(231, 74, 59, 0.4);
    transition: all 0.4s ease;
}

.btn-vider:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(231, 74, 59, 0.6);
    color: white;
}

@keyframes fadeInUp {
    from { opacity: 0; transform: translateY(30px); }
    to { opacity: 1; transform: translateY(0); }
}

.card, .jour-section, .seance-card {
    animation: fadeInUp 0.6s ease forwards;
    opacity: 0;
}

.jour-section:nth-child(1) { animation-delay: 0.1s; }
.jour-section:nth-child(2) { animation-delay: 0.2s; }
.jour-section:nth-child(3) { animation-delay: 0.3s; }
.jour-section:nth-child(4) { animation-delay: 0.4s; }
.jour-section:nth-child(5) { animation-delay: 0.5s; }
.jour-section:nth-child(6) { animation-delay: 0.6s; }
.jour-section:nth-child(7) { animation-delay: 0.7s; }

@keyframes float {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-8px); }
}

.seance-card:hover {
    animation: float 3s ease-in-out infinite;
}

.semaine-selector {
    background: rgba(255, 255, 255, 0.08);
    border: 1px solid rgba(255, 255, 255, 0.15);
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 20px;
}

.semaine-selector label {
    font-weight: 600;
    color: #9b72cf;
    margin-right: 10px;
}

@media (max-width: 768px) {
    .card {
        backdrop-filter: blur(8px);
        -webkit-backdrop-filter: blur(8px);
    }
    
    .seance-card {
        padding: 1rem;
    }
    
    .d-flex.justify-content-between {
        flex-direction: column;
        gap: 15px;
    }
    
    .d-flex.justify-content-between .btn {
        width: 100%;
        margin-left: 0 !important;
    }
}
    </style>
</head>
<body class="admin-body">
    <div class="container py-5">
        <div class="d-flex justify-content-between align-items-center mb-5">
            <h1 class="neon-violet">
                Salle <?= htmlspecialchars($salle['nom']) ?> 
                <span class="badge badge-formation"><?= $salle['formation'] == 'FI' ? 'Formation Initiale' : 'Formation Alternance' ?></span>
            </h1>
            <div>
                <a href="filiere.php?id=<?= $salle['filiere_id'] ?>" class="btn btn-dark">Retour</a>
                <button type="button" class="btn btn-vider ms-2" data-bs-toggle="modal" data-bs-target="#viderModal">
                    Vider la salle
                </button>
            </div>
        </div>
        

        <div class="row">
            <div class="col-md-4 mb-4">
                <div class="card bg-dark text-white">
                    <div class="card-body">
                        <h5 class="card-title">Informations</h5>
                        <p class="card-text">
                            <strong>Filière:</strong> <?= htmlspecialchars($salle['filiere_nom']) ?><br>
                            <strong>Niveau:</strong> <?= htmlspecialchars($salle['niveau_nom']) ?><br>
                            <strong>Type:</strong> <?= $salle['formation'] == 'FI' ? 'Formation Initiale' : 'Formation Alternance' ?><br>
                            <strong>Semaine:</strong> 
                            <?php 
                            $current_semaine = array_filter($semaines, function($s) use ($semaine_id) {
                                return $s['id'] == $semaine_id;
                            });
                            if (!empty($current_semaine)) {
                                $current_semaine = reset($current_semaine);
                                echo "Semaine " . $current_semaine['numero'] . " (" . date('d/m/Y', strtotime($current_semaine['date_debut'])) . " au " . date('d/m/Y', strtotime($current_semaine['date_fin'])) . ")";
                            }
                            ?>
                        </p>
                    </div>
                </div>
                
                <div class="card bg-dark text-white mt-4">
                    <div class="card-body">
                        <h5 class="card-title">Générer le rapport hebdomadaire</h5>
                        <form method="get" action="generer_rapport.php" target="_blank">
                            <input type="hidden" name="salle_id" value="<?= $salle_id ?>">
                            <div class="mb-3">
                                <label for="rapport_semaine_id" class="form-label">Semaine</label>
                                <select class="form-control" id="rapport_semaine_id" name="semaine_id" required style="background-color:#444;">
                                    <?php foreach ($semaines as $s): ?>
                                        <option value="<?= $s['id'] ?>" <?= $semaine_id == $s['id'] ? 'selected' : '' ?>>
                                            Semaine <?= $s['numero'] ?> (<?= date('d/m/Y', strtotime($s['date_debut'])) ?> au <?= date('d/m/Y', strtotime($s['date_fin'])) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Générer le PDF</button>
                        </form>
                    </div>
                </div>

                <div class="card bg-dark text-white mt-4">
                    <div class="card-body">
                        <h5 class="card-title">Ajouter une séance</h5>
                        <form id="addSeanceForm" method="post" action="add_seance.php">
                            <input type="hidden" name="salle_id" value="<?= $salle_id ?>">
                            <input type="hidden" name="semaine_id" value="<?= $semaine_id ?>">
<div class="mb-3">
    <label for="matiere_id" class="form-label">Matière</label>
    <select class="form-control" id="matiere_id" name="matiere_id" required style="background-color:#444;">
        <?php foreach ($matieres as $m): ?>
            <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['nom']) ?></option>
        <?php endforeach; ?>
    </select>
</div>
                            
                            <div class="mb-3">
                                <label for="jour" class="form-label">Jour</label>
                                <select class="form-control" id="jour" name="jour" required style="background-color:#444;">
                                    <option value="LUNDI">Lundi</option>
                                    <option value="MARDI">Mardi</option>
                                    <option value="MERCREDI">Mercredi</option>
                                    <option value="JEUDI">Jeudi</option>
                                    <option value="VENDREDI">Vendredi</option>
                                    <option value="SAMEDI">Samedi</option>
                                    <option value="DIMANCHE">Dimanche</option>
                                </select>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="heure_debut" class="form-label">Heure début</label>
                                    <input type="time" class="form-control" id="heure_debut" name="heure_debut" required style="background-color:#444;">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="heure_fin" class="form-label">Heure fin</label>
                                    <input type="time" class="form-control" id="heure_fin" name="heure_fin" required style="background-color:#444;">
                                </div>
                            </div>
                            
     <div class="mb-3">
    <label for="enseignant_id" class="form-label">Enseignant</label>
    <select class="form-control" id="enseignant_id" name="enseignant_id" required style="background-color:#444;">
        <?php foreach ($enseignants as $e): ?>
            <option value="<?= $e['id'] ?>"><?= htmlspecialchars($e['name']) ?></option>
        <?php endforeach; ?>
    </select>
</div>
                            
                            <button type="submit" class="btn btn-add-seance w-100">Ajouter la séance</button>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-md-8">
 
                
                <?php 
                $jours = ['LUNDI', 'MARDI', 'MERCREDI', 'JEUDI', 'VENDREDI', 'SAMEDI', 'DIMANCHE'];
                foreach ($jours as $jour): 
                    $seances_jour = array_filter($seances, function($s) use ($jour) {
                        return $s['jour'] == $jour;
                    });
                ?>
                    <div class="jour-section">
                        <h4><?= ucfirst(strtolower($jour)) ?></h4>
                        
                        <?php if (empty($seances_jour)): ?>
                            <div class="alert alert-dark">Aucune séance programmée</div>
                        <?php else: ?>
                            <?php foreach ($seances_jour as $seance): ?>
                                <div class="card seance-card">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h5 class="card-title"><?= htmlspecialchars($seance['matiere_nom']) ?></h5>
                                                <p class="card-text mb-1">
                                                    <small class="text-muted"><?= substr($seance['heure_debut'], 0, 5) ?> - <?= substr($seance['heure_fin'], 0, 5) ?></small>
                                                </p>
                                                <p class="card-text">
                                                    <small>Enseignant: <?= htmlspecialchars($seance['enseignant_nom']) ?></small>
                                                </p>
                                            </div>
                                            <div>
                                                <a href="delete_seance.php?id=<?= $seance['id'] ?>&salle_id=<?= $salle_id ?>&semaine_id=<?= $semaine_id ?>" class="btn btn-sm btn-danger" onclick="return confirm('Supprimer cette séance?')">
                                                    <i class="fas fa-trash"></i> Supprimer
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="modal fade" id="viderModal" tabindex="-1" aria-labelledby="viderModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content bg-dark text-white">
                <div class="modal-header">
                    <h5 class="modal-title" id="viderModalLabel">Confirmer la réinitialisation</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger">
                        <strong>Attention !</strong> Cette action est irréversible et supprimera définitivement :
                        <ul>
                            <li>Toutes les séances programmées dans cette salle</li>
                            <li>Tous les enregistrements de présence associés</li>
                            <li>Tous les pushes de validation associés</li>
                            <li>Tous les emplois du temps associés</li>
                        </ul>
                    </div>
                    <p>Êtes-vous absolument sûr de vouloir continuer ?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <form method="post" action="">
                        <button type="submit" name="vider_salle" class="btn btn-danger">Confirmer la suppression</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        <?php if (isset($_GET['success'])): ?>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: '<?= htmlspecialchars($_GET['success']) ?>',
                timer: 2000,
                showConfirmButton: false
            });
        <?php elseif (isset($_GET['error'])): ?>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: '<?= htmlspecialchars($_GET['error']) ?>'
            });
        <?php endif; ?>

        // Mettre à jour automatiquement le champ semaine_id caché quand on change le selecteur
        document.getElementById('semaine_id').addEventListener('change', function() {
            document.querySelector('input[name="semaine_id"]').value = this.value;
            document.getElementById('rapport_semaine_id').value = this.value;
        });
    </script>
</body>
</html>