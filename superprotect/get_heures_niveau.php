<?php
include 'includes/db.php';

if (!isset($_GET['enseignant_id'])) {
    die("Enseignant ID manquant");
}

$enseignant_id = $_GET['enseignant_id'];

// Fonction pour calculer les heures par niveau
function calculerHeuresParNiveau($pdo, $enseignant_id) {
    $sql = "SELECT niveaux.id, niveaux.nom, 
            SEC_TO_TIME(SUM(TIME_TO_SEC(TIMEDIFF(seances.heure_fin, seances.heure_debut)))) as total
            FROM seances 
            JOIN cours ON seances.cours_id = cours.id 
            JOIN emplois_temps ON cours.emploi_id = emplois_temps.id 
            JOIN salles ON emplois_temps.salle_id = salles.id 
            JOIN filieres ON salles.filiere_id = filieres.id 
            JOIN niveaux ON filieres.niveau_id = niveaux.id 
            WHERE seances.enseignant_id = ?
            GROUP BY niveaux.id, niveaux.nom";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$enseignant_id]);
    return $stmt->fetchAll();
}

$heures_par_niveau = calculerHeuresParNiveau($pdo, $enseignant_id);
?>



<div class="table-responsive">
    <table class="table table-hover">
        <thead>
            <tr>
                <th>Niveau</th>
                <th>Heures Cumulées</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($heures_par_niveau)): ?>
                <tr>
                    <td colspan="2" class="text-center">Aucune donnée disponible</td>
                </tr>
            <?php else: ?>
                <?php foreach ($heures_par_niveau as $niveau): ?>
                    <tr>
                        <td><?= htmlspecialchars($niveau['nom']) ?></td>
                        <td>
                            <span class="badge bg-primary"><?= $niveau['total'] ?: '00:00:00' ?></span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>