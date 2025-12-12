<?php
/**
 * Backend pour ajouter une réclamation
 * ADAPTÉ pour le projet de ton ami
 * AVEC NOTIFICATION GESTIONNAIRES + DEBUG ✅
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
    
    // ✅ NOTIFIER TOUS LES GESTIONNAIRES (VERSION DEBUG)
    error_log("=== DÉBUT NOTIFICATION GESTIONNAIRES ===");
    error_log("Réclamation ID: " . $reclamationId);
    error_log("User ID: " . $userId);
    
    try {
        // Récupérer tous les gestionnaires et administrateurs
        $stmt = $pdo->prepare("
            SELECT id, nom, role FROM users 
            WHERE role IN ('gestionnaire', 'administrateur')
        ");
        $stmt->execute();
        $gestionnaires = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        error_log("Nombre de gestionnaires trouvés: " . count($gestionnaires));
        
        if (count($gestionnaires) === 0) {
            error_log("⚠️ ERREUR: Aucun gestionnaire trouvé dans la base !");
        } else {
            error_log("Liste des gestionnaires:");
            foreach ($gestionnaires as $g) {
                error_log("  - ID: " . $g['id'] . ", Nom: " . $g['nom'] . ", Role: " . $g['role']);
            }
        }
        
        // Récupérer le nom du user pour le message
        $stmt = $pdo->prepare("SELECT nom FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $userInfo = $stmt->fetch(PDO::FETCH_ASSOC);
        $userName = $userInfo['nom'] ?? 'Un utilisateur';
        
        error_log("Nom du user créateur: " . $userName);
        
        // Créer une notification pour chaque gestionnaire
        $notifCount = 0;
        foreach ($gestionnaires as $gest) {
            error_log("Tentative création notification pour gestionnaire ID: " . $gest['id'] . " - " . $gest['nom']);
            
            $stmt = $pdo->prepare("
                INSERT INTO notifications (user_id, type, reference_table, reference_id, contenu, lu, date_creation)
                VALUES (?, 'nouvelle_reclamation', 'reclamations', ?, ?, 0, NOW())
            ");
            
            $result = $stmt->execute([
                $gest['id'],
                $reclamationId,
                'Nouvelle réclamation de ' . $userName
            ]);
            
            if ($result) {
                $notifCount++;
                error_log("✅ Notification créée avec succès pour " . $gest['nom'] . " (ID: " . $gest['id'] . ")");
            } else {
                error_log("❌ ÉCHEC création notification pour " . $gest['nom']);
                $errorInfo = $stmt->errorInfo();
                error_log("Détails erreur SQL: " . json_encode($errorInfo));
            }
        }
        
        error_log("Total notifications créées: " . $notifCount . " / " . count($gestionnaires));
        error_log("=== FIN NOTIFICATION GESTIONNAIRES ===");
        
    } catch (PDOException $e) {
        error_log("❌ ERREUR PDO NOTIFICATION: " . $e->getMessage());
        error_log("Code erreur: " . $e->getCode());
        error_log("Stack trace: " . $e->getTraceAsString());
        // On continue même si la notification échoue
    }
    
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
    error_log("❌ ERREUR GÉNÉRALE add_reclamation: " . $e->getMessage());
    error_log("Code erreur: " . $e->getCode());
    echo json_encode([
        'success' => false,
        'message' => 'Erreur lors de la création de la réclamation'
    ]);
}