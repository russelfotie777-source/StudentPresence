<?php
include 'includes/db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $salle_id = $_POST['salle_id'];
    $matiere_id = $_POST['matiere_id'];
    $jour = $_POST['jour'];
    $heure_debut = $_POST['heure_debut'];
    $heure_fin = $_POST['heure_fin'];
    $enseignant_id = $_POST['enseignant_id'];
    $semaine_id = $_POST['semaine_id'];
    $groupe = $_POST['groupe'] ?? 'G1'; // Récupération du groupe
    
    try {
        // Vérifier si la semaine est valide
        $stmt = $pdo->prepare("SELECT id FROM semaines WHERE id = ?");
        $stmt->execute([$semaine_id]);
        if (!$stmt->fetch()) {
            throw new Exception("Semaine invalide");
        }

        // ⭐ CORRECTION CRUCIALE DE LA VÉRIFICATION DE CHEVAUCHEMENT ⭐
        // La contrainte d'unicité horaire est maintenant appliquée UNIQUEMENT au GROUPE spécifié.
        // On utilise la logique de chevauchement standard : (Début1 < Fin2) AND (Fin1 > Début2)
        $stmt_check = $pdo->prepare("SELECT id FROM seances 
                                  WHERE salle_id = ? 
                                  AND semaine_id = ?
                                  AND jour = ?
                                  AND groupe = ? /* FILTRAGE PAR GROUPE AJOUTÉ */
                                  AND (
                                      (heure_debut < ? AND heure_fin > ?)
                                  )");
        $stmt_check->execute([
            $salle_id,
            $semaine_id,
            $jour,
            $groupe, // Passage du paramètre groupe pour le filtre
            $heure_fin,
            $heure_debut
        ]);

        
        if ($stmt_check->fetch()) {
            // Le conflit n'est levé que s'il y a chevauchement pour le même groupe
            throw new Exception("Conflit d'emploi du temps pour le groupe $groupe : une séance existe déjà pour cette plage horaire.");
        }
        

        // Vérifier/Créer le cours correspondant (logique existante)
        $stmt = $pdo->prepare("SELECT id FROM cours WHERE matiere_id = ? AND enseignant_id = ? LIMIT 1");
        $stmt->execute([$matiere_id, $enseignant_id]);
        $cours = $stmt->fetch();

        if ($cours) {
            $cours_id = $cours['id'];
        } else {
            // Créer un nouveau cours
            $stmt = $pdo->prepare("INSERT INTO cours (matiere_id, enseignant_id, emploi_id, jour, heure_debut, heure_fin, planification) 
                                  VALUES (?, ?, 0, 'LUNDI', '08:00:00', '10:00:00', '08:00')");
            $stmt->execute([$matiere_id, $enseignant_id]);
            $cours_id = $pdo->lastInsertId();
        }

        // Ajouter la séance (avec la colonne groupe)
        $stmt = $pdo->prepare("INSERT INTO seances 
                              (cours_id, salle_id, semaine_id, jour, heure_debut, heure_fin, enseignant_id, groupe) 
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $cours_id,
            $salle_id,
            $semaine_id,
            $jour,
            $heure_debut,
            $heure_fin,
            $enseignant_id,
            $groupe // VALEUR DU GROUPE
        ]);
        
        // Redirection avec les paramètres semaine et groupe
        header("Location: salle_detail.php?id=$salle_id&semaine_id=$semaine_id&groupe=$groupe&success=Séance ajoutée avec succès");
        exit;
    } catch (Exception $e) {
        // Redirection avec les paramètres semaine et groupe en cas d'erreur
        $groupe_url = urlencode($groupe);
        $errorMessage = urlencode($e->getMessage());
        header("Location: salle_detail.php?id=$salle_id&semaine_id=$semaine_id&groupe=$groupe_url&error=$errorMessage");
        exit;
    }
} else {
    // Si la requête n'est pas POST, rediriger
    header("Location: index.php");
    exit;
}