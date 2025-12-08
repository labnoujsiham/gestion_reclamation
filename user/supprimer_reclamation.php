<?php 
// Démarrer la session et vérifier l'authentification
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: ../connexion/connexion.php');
    exit();
}

// Récupérer l'ID utilisateur depuis la session
$userId = $_SESSION['user_id'];

// Connexion à la base de données
require_once '../connexion/db_config.php';

$reclamationId = $_GET['id'] ?? 0;

// Vérifier que la réclamation appartient bien à l'utilisateur
try {
    $stmt = $pdo->prepare("SELECT id FROM reclamations WHERE id = ? AND user_id = ?");
    $stmt->execute([$reclamationId, $userId]);
    $reclamation = $stmt->fetch();
    
    if (!$reclamation) {
        $_SESSION['error'] = "Réclamation introuvable ou accès refusé";
        header('Location: mes_reclamations.php');
        exit;
    }
    
    // Supprimer la réclamation (les pièces jointes seront supprimées automatiquement grâce à ON DELETE CASCADE)
    $stmt = $pdo->prepare("DELETE FROM reclamations WHERE id = ? AND user_id = ?");
    $stmt->execute([$reclamationId, $userId]);
    
    $_SESSION['success'] = "Réclamation supprimée avec succès";
    header('Location: mes_reclamations.php');
    exit;
    
} catch (PDOException $e) {
    $_SESSION['error'] = "Erreur lors de la suppression de la réclamation";
    header('Location: mes_reclamations.php');
    exit;
}