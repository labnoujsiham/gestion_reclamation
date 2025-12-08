<?php
/**
 * API - Marquer une notification comme lue
 * ADAPTÉ pour le projet de ton ami
 */

// Démarrer la session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

// Connexion à la base de données
require_once '../connexion/db_config.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Non authentifié']);
    exit;
}

$userId = $_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true);

try {
    // Utiliser la variable globale $pdo
    
    // Marquer une notification spécifique comme lue
    if (isset($input['notification_id'])) {
        $notifId = $input['notification_id'];
        
        // La table de ton ami a aussi un champ date_lu
        $stmt = $pdo->prepare("
            UPDATE notifications 
            SET lu = 1, date_lu = NOW() 
            WHERE id = ? AND user_id = ?
        ");
        $stmt->execute([$notifId, $userId]);
        
        echo json_encode(['success' => true, 'message' => 'Notification marquée comme lue']);
    }
    // Marquer toutes les notifications comme lues
    elseif (isset($input['mark_all'])) {
        $stmt = $pdo->prepare("
            UPDATE notifications 
            SET lu = 1, date_lu = NOW() 
            WHERE user_id = ? AND lu = 0
        ");
        $stmt->execute([$userId]);
        
        echo json_encode(['success' => true, 'message' => 'Toutes les notifications marquées comme lues']);
    }
    // Marquer les notifications d'une réclamation spécifique comme lues
    elseif (isset($input['reclamation_id'])) {
        $reclamationId = $input['reclamation_id'];
        
        // IMPORTANT: La table de ton ami utilise reference_table et reference_id
        // Au lieu de reclamation_id directement
        $stmt = $pdo->prepare("
            UPDATE notifications 
            SET lu = 1, date_lu = NOW() 
            WHERE user_id = ? 
            AND reference_table = 'reclamations' 
            AND reference_id = ? 
            AND lu = 0
        ");
        $stmt->execute([$userId, $reclamationId]);
        
        echo json_encode(['success' => true, 'message' => 'Notifications de la réclamation marquées comme lues']);
    }
    else {
        echo json_encode(['success' => false, 'message' => 'Paramètres invalides']);
    }
    
} catch (PDOException $e) {
    error_log("Erreur mark_as_read: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erreur serveur']);
}