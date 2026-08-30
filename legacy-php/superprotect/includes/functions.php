<?php
// functions.php - Fonctions spécifiques aux requêtes enseignants

/**
 * Vérifie si un enseignant a déjà soumis une requête pour une séance donnée
 * @param PDO $pdo Instance PDO
 * @param int $seance_id ID de la séance
 * @param int $enseignant_id ID de l'enseignant
 * @return bool True si une requête existe déjà, false sinon
 */
function requete_existe($pdo, $seance_id, $enseignant_id) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM requetes_enseignants 
                          WHERE seance_id = ? AND enseignant_id = ?");
    $stmt->execute([$seance_id, $enseignant_id]);
    return $stmt->fetchColumn() > 0;
}

/**
 * Récupère les informations d'une séance pour affichage dans le formulaire de requête
 * @param PDO $pdo Instance PDO
 * @param int $seance_id ID de la séance
 * @return array|false Tableau des informations ou false si non trouvé
 */
function get_seance_info($pdo, $seance_id) {
    $stmt = $pdo->prepare("SELECT s.*, m.nom as matiere, sa.nom as salle, n.nom as niveau
                          FROM seances s
                          JOIN cours c ON s.cours_id = c.id
                          JOIN matieres m ON c.matiere_id = m.id
                          JOIN salles sa ON s.salle_id = sa.id
                          JOIN filieres f ON sa.filiere_id = f.id
                          JOIN niveaux n ON f.niveau_id = n.id
                          WHERE s.id = ?");
    $stmt->execute([$seance_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Crée une nouvelle requête enseignant
 * @param PDO $pdo Instance PDO
 * @param array $data Données de la requête
 * @return int|false ID de la nouvelle requête ou false en cas d'échec
 */
function creer_requete($pdo, $data) {
    $query = "INSERT INTO requetes_enseignants 
              (seance_id, enseignant_id, heure_seance, matiere, salle, niveau, penalite, description, preuve_path, date_creation)
              VALUES 
              (:seance_id, :enseignant_id, :heure_seance, :matiere, :salle, :niveau, :penalite, :description, :preuve_path, NOW())";
    
    $stmt = $pdo->prepare($query);
    
    $success = $stmt->execute([
        ':seance_id' => $data['seance_id'],
        ':enseignant_id' => $data['enseignant_id'],
        ':heure_seance' => $data['heure_seance'],
        ':matiere' => $data['matiere'],
        ':salle' => $data['salle'],
        ':niveau' => $data['niveau'],
        ':penalite' => $data['penalite'],
        ':description' => $data['description'],
        ':preuve_path' => $data['preuve_path']
    ]);
    
    return $success ? $pdo->lastInsertId() : false;
}

/**
 * Traite une requête (validation ou rejet)
 * @param PDO $pdo Instance PDO
 * @param int $requete_id ID de la requête
 * @param string $action 'acceptee' ou 'rejetee'
 * @param string $commentaire Commentaire de l'admin
 * @return bool True si succès, false sinon
 */
function traiter_requete($pdo, $requete_id, $action, $commentaire = null) {
    // Récupérer la requête
    $requete = $pdo->prepare("SELECT * FROM requetes_enseignants WHERE id = ?");
    $requete->execute([$requete_id]);
    $requete = $requete->fetch(PDO::FETCH_ASSOC);
    
    if (!$requete) {
        return false;
    }
    
    try {
        $pdo->beginTransaction();
        
        // Mettre à jour le statut de la requête
        $updateReq = $pdo->prepare("UPDATE requetes_enseignants 
                                   SET statut = ?, 
                                       date_traitement = NOW(), 
                                       commentaire_admin = ? 
                                   WHERE id = ?");
        $updateReq->execute([$action, $commentaire, $requete_id]);
        
        // Si la requête est acceptée, mettre à jour la séance
        if ($action === 'acceptee') {
            $updateSeance = $pdo->prepare("UPDATE seances 
                                         SET debut_reel = heure_debut, 
                                             fin_reelle = heure_fin, 
                                             etat_prof = 'present', 
                                             etat_final = 'present' 
                                         WHERE id = ?");
            $updateSeance->execute([$requete['seance_id']]);
        }
        
        $pdo->commit();
        return true;
    } catch (Exception $e) {
        $pdo->rollBack();
        return false;
    }
}

/**
 * Récupère les détails complets d'une requête
 * @param PDO $pdo Instance PDO
 * @param int $requete_id ID de la requête
 * @return array|false Tableau des détails ou false si non trouvé
 */
function get_requete_details($pdo, $requete_id) {
    $query = "SELECT r.*, u.name as enseignant_name, s.heure_debut, s.heure_fin 
              FROM requetes_enseignants r 
              JOIN users u ON r.enseignant_id = u.id 
              JOIN seances s ON r.seance_id = s.id 
              WHERE r.id = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$requete_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Récupère toutes les requêtes avec filtres optionnels
 * @param PDO $pdo Instance PDO
 * @param string $statut Filtre par statut (optionnel)
 * @return array Liste des requêtes
 */
function get_all_requetes($pdo, $statut = null) {
    $query = "SELECT r.*, u.name as enseignant_name 
              FROM requetes_enseignants r 
              JOIN users u ON r.enseignant_id = u.id";
    
    $params = [];
    
    if ($statut !== null) {
        $query .= " WHERE r.statut = ?";
        $params[] = $statut;
    }
    
    $query .= " ORDER BY r.date_creation DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}