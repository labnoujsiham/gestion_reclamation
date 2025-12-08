<?php
/**
 * Backend pour ajouter une réclamation
 * ADAPTÉ pour le projet de ton ami
 */

// Démarrer la session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Vérifier que l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Non authentifié']);
    exit;
}

// Connexion à la base de données
require_once '../connexion/db_config.php';

header('Content-Type: application/json');

// Vérifier la méthode POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

// Récupérer l'ID utilisateur depuis la session
$userId = $_SESSION['user_id'];

// Récupérer les données du formulaire
$objet = trim($_POST['objet'] ?? '');
$categorie = trim($_POST['categorie'] ?? '');
$description = trim($_POST['description'] ?? '');
$priorite = trim($_POST['priorite'] ?? 'moyenne'); // Par défaut moyenne
$urgent = isset($_POST['urgent']) ? 1 : 0;

// Validation des champs
if (empty($objet) || empty($categorie) || empty($description)) {
    echo json_encode([
        'success' => false,
        'message' => 'Tous les champs obligatoires doivent être remplis'
    ]);
    exit;
}

if (strlen($objet) < 5 || strlen($objet) > 255) {
    echo json_encode([
        'success' => false,
        'message' => 'L\'objet doit contenir entre 5 et 255 caractères'
    ]);
    exit;
}

if (strlen($description) < 10) {
    echo json_encode([
        'success' => false,
        'message' => 'La description doit contenir au moins 10 caractères'
    ]);
    exit;
}

try {
    // Utiliser la variable globale $pdo
    
    // Récupérer l'ID de la catégorie par son nom
    $stmt = $pdo->prepare("SELECT id FROM categories WHERE nom = ?");
    $stmt->execute([$categorie]);
    $categorieData = $stmt->fetch();
    
    if (!$categorieData) {
        echo json_encode([
            'success' => false,
            'message' => 'Catégorie invalide'
        ]);
        exit;
    }
    
    $categorieId = $categorieData['id'];
    
    // Récupérer le statut "En attente"
    $stmt = $pdo->prepare("SELECT id FROM statuts WHERE cle = 'en_attente'");
    $stmt->execute();
    $statutData = $stmt->fetch();
    
    if (!$statutData) {
        echo json_encode([
            'success' => false,
            'message' => 'Erreur de configuration des statuts'
        ]);
        exit;
    }
    
    $statutId = $statutData['id'];
    
    // Insérer la réclamation
    $stmt = $pdo->prepare("
        INSERT INTO reclamations 
        (user_id, categorie_id, objet, description, priorite, statut_id, date_soumission, urgent) 
        VALUES (?, ?, ?, ?, ?, ?, NOW(), ?)
    ");
    
    $stmt->execute([
        $userId,
        $categorieId,
        $objet,
        $description,
        $priorite,
        $statutId,
        $urgent
    ]);
    
    $reclamationId = $pdo->lastInsertId();
    
    // Gérer les pièces jointes si présentes
    $fichiers = [];
    if (isset($_FILES['pieces_jointes']) && !empty($_FILES['pieces_jointes']['name'][0])) {
        $uploadDir = __DIR__ . '/uploads/';
        
        // Créer le dossier uploads s'il n'existe pas
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $filesCount = count($_FILES['pieces_jointes']['name']);
        
        for ($i = 0; $i < $filesCount; $i++) {
            if ($_FILES['pieces_jointes']['error'][$i] === UPLOAD_ERR_OK) {
                $fileName = $_FILES['pieces_jointes']['name'][$i];
                $fileTmpName = $_FILES['pieces_jointes']['tmp_name'][$i];
                $fileSize = $_FILES['pieces_jointes']['size'][$i];
                $fileMime = $_FILES['pieces_jointes']['type'][$i];
                
                // Validation du fichier
                $allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];
                $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                
                if (!in_array($fileExt, $allowedExtensions)) {
                    continue; // Ignorer les fichiers non autorisés
                }
                
                if ($fileSize > 5 * 1024 * 1024) { // Max 5MB
                    continue; // Ignorer les fichiers trop gros
                }
                
                // Générer un nom unique
                $uniqueName = uniqid() . '_' . time() . '.' . $fileExt;
                $destination = $uploadDir . $uniqueName;
                
                if (move_uploaded_file($fileTmpName, $destination)) {
                    // Enregistrer dans la base de données
                    $stmt = $pdo->prepare("
                        INSERT INTO pieces_jointes 
                        (reclamation_id, chemin_fichier, nom_original, mime, taille, date_ajout) 
                        VALUES (?, ?, ?, ?, ?, NOW())
                    ");
                    
                    $stmt->execute([
                        $reclamationId,
                        'uploads/' . $uniqueName,
                        $fileName,
                        $fileMime,
                        $fileSize
                    ]);
                    
                    $fichiers[] = $fileName;
                }
            }
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Réclamation créée avec succès',
        'reclamation_id' => $reclamationId,
        'fichiers_uploades' => count($fichiers)
    ]);
    
} catch (PDOException $e) {
    error_log("Erreur add_reclamation: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erreur lors de la création de la réclamation'
    ]);
}