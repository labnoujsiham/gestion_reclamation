<?php
/**
 * API - Marquer les notifications comme lues (GESTIONNAIRE)
 * ADAPTÉ pour le projet de ton ami
 */

// Démarrer la session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

// Connexion à la base de données
require_once '../connexion/db_config.php';

$pdo = getDBConnection();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Non authentifié']);
    exit;
}

$userId = $_SESSION['user_id'];

// Récupérer les données JSON
$input = json_decode(file_get_contents('php://input'), true);

try {
    // Marquer toutes les notifications comme lues
    if (isset($input['mark_all']) && $input['mark_all'] === true) {
        $stmt = $pdo->prepare("
            UPDATE notifications 
            SET lu = 1 
            WHERE user_id = ? AND lu = 0
        ");
        $stmt->execute([$userId]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Toutes les notifications marquées comme lues'
        ]);
        exit;
    }
    
    // Marquer une notification spécifique comme lue
    if (isset($input['notification_id'])) {
        $notificationId = intval($input['notification_id']);
        
        $stmt = $pdo->prepare("
            UPDATE notifications 
            SET lu = 1 
            WHERE id = ? AND user_id = ?
        ");
        $stmt->execute([$notificationId, $userId]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Notification marquée comme lue'
        ]);
        exit;
    }
    
    echo json_encode([
        'success' => false,
        'message' => 'Paramètres manquants'
    ]);
    
} catch (PDOException $e) {
    error_log("Erreur mark_as_read gestionnaire: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erreur serveur'
    ]);
}