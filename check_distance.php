<?php
// check_distance.php - Version améliorée
require_once 'includes/config.php';
require_once 'includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit();
}

if (!isset($_POST['seance_id']) || !isset($_POST['student_lat']) || !isset($_POST['student_lng'])) {
    echo json_encode(['success' => false, 'message' => 'Données manquantes']);
    exit();
}

$seance_id = intval($_POST['seance_id']);
$student_lat = floatval($_POST['student_lat']);
$student_lng = floatval($_POST['student_lng']);

// Fonction améliorée pour calculer la distance avec plus de précision
function calculateDistance($lat1, $lon1, $lat2, $lon2) {
    $earthRadius = 6371000; // Rayon de la Terre en mètres
    
    // Conversion des degrés en radians
    $lat1 = deg2rad($lat1);
    $lon1 = deg2rad($lon1);
    $lat2 = deg2rad($lat2);
    $lon2 = deg2rad($lon2);
    
    // Différence de latitude et longitude
    $dLat = $lat2 - $lat1;
    $dLon = $lon2 - $lon1;
    
    // Formule Haversine améliorée
    $a = sin($dLat/2) * sin($dLat/2) + 
         cos($lat1) * cos($lat2) * 
         sin($dLon/2) * sin($dLon/2);
    
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    
    $distance = $earthRadius * $c;
    
    return round($distance, 2); // Arrondi à 2 décimales
}

// VÉRIFICATION PRINCIPALE : Le délégué a-t-il activé sa position ?
$query = "SELECT latitude, longitude FROM positions_seances WHERE seance_id = ?";
$stmt = $pdo->prepare($query);
$stmt->execute([$seance_id]);
$position_delegue = $stmt->fetch(PDO::FETCH_ASSOC);

// Si le délégué n'a pas activé sa position
if (!$position_delegue) {
    echo json_encode([
        'success' => false, 
        'message' => 'Le délégué n\'a pas encore activé sa position pour cette séance.',
        'error_type' => 'no_delegue_position'
    ]);
    exit();
}

// Si le délégué a activé sa position, on vérifie la distance
$delegue_lat = floatval($position_delegue['latitude']);
$delegue_lng = floatval($position_delegue['longitude']);

// Calculer la distance avec la fonction améliorée
$distance = calculateDistance($student_lat, $student_lng, $delegue_lat, $delegue_lng);
$max_distance=2550; // Augmenté à 650 mètres comme demandé

// Vérification de la validité des coordonnées
$coordinates_valid = true;
if (abs($student_lat) > 90 || abs($student_lng) > 180 || 
    abs($delegue_lat) > 90 || abs($delegue_lng) > 180) {
    $coordinates_valid = false;
}

// Vérification si les coordonnées sont identiques (erreur possible)
if ($student_lat == $delegue_lat && $student_lng == $delegue_lng) {
    $distance = 0;
}

echo json_encode([
    'success' => true,
    'distance' => $distance,
    'within_range' => $distance <= $max_distance,
    'max_distance' => $max_distance,
    'delegue_position_available' => true,
    'coordinates_valid' => $coordinates_valid,
    'student_coords' => ['lat' => $student_lat, 'lng' => $student_lng],
    'delegue_coords' => ['lat' => $delegue_lat, 'lng' => $delegue_lng]
]);
?>