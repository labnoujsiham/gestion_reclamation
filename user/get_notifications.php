<?php
/**
 * API - Récupérer les notifications d'un utilisateur
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

try {
    // Utiliser la variable globale $pdo
    
    // Récupérer le nombre de notifications non lues
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count_non_lues 
        FROM notifications 
        WHERE user_id = ? AND lu = 0
    ");
    $stmt->execute([$userId]);
    $count = $stmt->fetch()['count_non_lues'];
    
    // Récupérer les notifications récentes (20 dernières)
    // NOTE: La structure de la table notifications de ton ami est différente
    // Il utilise: type, reference_table, reference_id, contenu
    // Au lieu de: reclamation_id, gestionnaire_id, message
    
    $stmt = $pdo->prepare("
        SELECT 
            n.*,
            r.objet AS reclamation_objet,
            u.nom AS gestionnaire_nom
        FROM notifications n
        LEFT JOIN reclamations r ON (n.reference_table = 'reclamations' AND n.reference_id = r.id)
        LEFT JOIN users u ON u.id = (
            SELECT auteur_id FROM commentaires 
            WHERE reclamation_id = n.reference_id 
            ORDER BY date_commentaire DESC 
            LIMIT 1
        )
        WHERE n.user_id = ?
        ORDER BY n.date_creation DESC
        LIMIT 20
    ");
    $stmt->execute([$userId]);
    $notifications = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'count_non_lues' => $count,
        'notifications' => $notifications
    ]);
    
} catch (PDOException $e) {
    error_log("Erreur get_notifications: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erreur serveur'
    ]);
}