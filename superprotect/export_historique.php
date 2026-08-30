<?php
include 'includes/db.php';

// Récupération des paramètres
$enseignant_id = $_GET['enseignant_id'] ?? null;
$semaine_id = $_GET['semaine_id'] ?? null;

// Requête (identique à historique_seances.php)
$query = "SELECT ..."; // La même requête que dans historique_seances.php
$stmt = $pdo->prepare($query);
$params = [];
if ($enseignant_id) $params[] = $enseignant_id;
if ($semaine_id) $params[] = $semaine_id;
$stmt->execute($params);
$seances = $stmt->fetchAll();

// Entêtes HTTP pour forcer le téléchargement
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="historique_seances_'.date('Y-m-d').'.xls"');

// Génération du fichier Excel (HTML simplifié)
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #000; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .present { background-color: #d4edda; }
        .absent { background-color: #f8d7da; }
    </style>
</head>
<body>
    <h2>Historique des Séances</h2>
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Semaine</th>
                <th>Matière</th>
                <th>Enseignant</th>
                <th>Salle</th>
                <th>Créneau</th>
                <th>Durée</th>
                <th>État</th>
                <th>Commentaires</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($seances as $seance): ?>
            <tr class="<?= $seance['etat_final'] == 'present' ? 'present' : 'absent' ?>">
                <td><?= date('d/m/Y', strtotime($seance['date_seance'])) ?></td>
                <td>Sem. <?= $seance['semaine_numero'] ?></td>
                <td><?= htmlspecialchars($seance['matiere']) ?></td>
                <td><?= htmlspecialchars($seance['enseignant']) ?></td>
                <td><?= htmlspecialchars($seance['salle']) ?></td>
                <td><?= date('H:i', strtotime($seance['heure_debut'])) ?> - <?= date('H:i', strtotime($seance['heure_fin'])) ?></td>
                <td><?= round((strtotime($seance['heure_fin']) - strtotime($seance['heure_debut'])) / 3600, 1) ?>h</td>
                <td><?= $seance['etat_final'] == 'present' ? 'Présent' : 'Absent' ?></td>
                <td><?= htmlspecialchars($seance['commentaires']) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>