<?php
/**
 * Fichier de déconnexion
 * Détruit la session et redirige vers la page de connexion
 * ADAPTÉ pour le projet de ton ami
 */

// Démarrer la session si pas déjà démarrée
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Détruire toutes les variables de session
$_SESSION = array();

// Si vous voulez détruire complètement la session, effacez également le cookie de session
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Détruire la session
session_destroy();

// Rediriger vers la page de connexion (chemin corrigé)
header('Location: ../connexion/connexion.php');
exit;