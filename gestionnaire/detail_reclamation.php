<?php
/**
 * Détail Réclamation - ReclaNova
 * Detail view for gestionnaire to manage a single reclamation
 */

session_start();
require_once 'db_config.php';

// ============= SESSION AUTHENTICATION =============
// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role'])) {
    header('Location: ../connexion/connexion.php');
    exit;
}

// Check if user is gestionnaire
if ($_SESSION['user_role'] !== 'gestionnaire') {
    header('Location: ../connexion/connexion.php');
    exit;
}

// Get current gestionnaire info from session
$current_gestionnaire_id = $_SESSION['user_id'];
$current_gestionnaire_nom = $_SESSION['user_name'];
// ==================================================

// Set page title
$page_title = "Détail réclamation";

$pdo = getDBConnection();

// Get reclamation ID from URL
$reclamation_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Initialize variables
$reclamation = null;
$commentaires = [];
$statuts = [];
$pieces_jointes = [];
$message = '';
$messageType = '';

if (!$pdo || $reclamation_id <= 0) {
    header('Location: reclamation.php');
    exit;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Update Status
        if (isset($_POST['action']) && $_POST['action'] === 'update_status') {
            $new_statut_id = intval($_POST['statut_id']);
            
            $stmt = $pdo->prepare("
                UPDATE reclamations 
                SET statut_id = :statut_id, 
                    gestionnaire_id = :gestionnaire_id,
                    date_dernier_update = NOW()
                WHERE id = :id
            ");
            $stmt->execute([
                ':statut_id' => $new_statut_id,
                ':gestionnaire_id' => $current_gestionnaire_id,
                ':id' => $reclamation_id
            ]);
            
            // Add activity log comment
            $stmt = $pdo->prepare("
                INSERT INTO commentaires (reclamation_id, auteur_id, message, visible_par_reclamant, date_commentaire)
                VALUES (:reclamation_id, :auteur_id, :message, 0, NOW())
            ");
            $stmt->execute([
                ':reclamation_id' => $reclamation_id,
                ':auteur_id' => $current_gestionnaire_id,
                ':message' => 'Statut changé par ' . $current_gestionnaire_nom
            ]);
            
            $message = "Statut mis à jour avec succès!";
            $messageType = "success";
        }
        
        // Update Priority
        if (isset($_POST['action']) && $_POST['action'] === 'update_priority') {
            $new_priorite = $_POST['priorite'];
            
            // Validate priority value
            if (in_array($new_priorite, ['basse', 'moyenne', 'haute'])) {
                $stmt = $pdo->prepare("
                    UPDATE reclamations 
                    SET priorite = :priorite,
                        gestionnaire_id = :gestionnaire_id,
                        date_dernier_update = NOW()
                    WHERE id = :id
                ");
                $stmt->execute([
                    ':priorite' => $new_priorite,
                    ':gestionnaire_id' => $current_gestionnaire_id,
                    ':id' => $reclamation_id
                ]);
                
                // Add activity log comment
                $stmt = $pdo->prepare("
                    INSERT INTO commentaires (reclamation_id, auteur_id, message, visible_par_reclamant, date_commentaire)
                    VALUES (:reclamation_id, :auteur_id, :message, 0, NOW())
                ");
                $stmt->execute([
                    ':reclamation_id' => $reclamation_id,
                    ':auteur_id' => $current_gestionnaire_id,
                    ':message' => 'Priorité changée à "' . $new_priorite . '" par ' . $current_gestionnaire_nom
                ]);
                
                $message = "Priorité mise à jour avec succès!";
                $messageType = "success";
            }
        }
        
        // Add Comment
        if (isset($_POST['action']) && $_POST['action'] === 'add_comment') {
            $comment_message = trim($_POST['message']);
            
            if (!empty($comment_message)) {
                $stmt = $pdo->prepare("
                    INSERT INTO commentaires (reclamation_id, auteur_id, message, visible_par_reclamant, date_commentaire)
                    VALUES (:reclamation_id, :auteur_id, :message, 1, NOW())
                ");
                $stmt->execute([
                    ':reclamation_id' => $reclamation_id,
                    ':auteur_id' => $current_gestionnaire_id,
                    ':message' => $comment_message
                ]);
                
                // Update reclamation timestamp
                $pdo->prepare("UPDATE reclamations SET date_dernier_update = NOW() WHERE id = ?")->execute([$reclamation_id]);
                
                // ✅ CRÉER UNE NOTIFICATION POUR LE USER (ADAPTÉ pour table ami)
                try {
                    // Récupérer l'user_id de la réclamation
                    $stmt = $pdo->prepare("SELECT user_id FROM reclamations WHERE id = ?");
                    $stmt->execute([$reclamation_id]);
                    $recl = $stmt->fetch();
                    
                    if ($recl) {
                        // Créer la notification (ADAPTÉ: type, reference_table, reference_id, contenu)
                        $stmt = $pdo->prepare("
                            INSERT INTO notifications (user_id, type, reference_table, reference_id, contenu, lu, date_creation) 
                            VALUES (?, 'nouveau_commentaire', 'reclamations', ?, ?, 0, NOW())
                        ");
                        $stmt->execute([
                            $recl['user_id'],
                            $reclamation_id,
                            'Le gestionnaire a répondu à votre réclamation'
                        ]);
                    }
                } catch (PDOException $e) {
                    error_log("Erreur création notification: " . $e->getMessage());
                }
                
                $message = "Commentaire ajouté avec succès!";
                $messageType = "success";
            }
        }
        
        // Request More Info
        if (isset($_POST['action']) && $_POST['action'] === 'request_info') {
            // Get the status ID for 'attente_info_reclamant'
            $stmt = $pdo->prepare("SELECT id FROM statuts WHERE cle = 'attente_info_reclamant'");
            $stmt->execute();
            $info_statut = $stmt->fetch();
            
            if ($info_statut) {
                // Update status and enable commenting for reclamant
                $stmt = $pdo->prepare("
                    UPDATE reclamations 
                    SET statut_id = :statut_id,
                        peut_commenter = 1,
                        gestionnaire_id = :gestionnaire_id,
                        date_dernier_update = NOW()
                    WHERE id = :id
                ");
                $stmt->execute([
                    ':statut_id' => $info_statut['id'],
                    ':gestionnaire_id' => $current_gestionnaire_id,
                    ':id' => $reclamation_id
                ]);
                
                // Add comment about info request
                $stmt = $pdo->prepare("
                    INSERT INTO commentaires (reclamation_id, auteur_id, message, visible_par_reclamant, date_commentaire)
                    VALUES (:reclamation_id, :auteur_id, :message, 1, NOW())
                ");
                $stmt->execute([
                    ':reclamation_id' => $reclamation_id,
                    ':auteur_id' => $current_gestionnaire_id,
                    ':message' => 'Demande d\'informations supplémentaires envoyée au réclamant.'
                ]);
                
                // Create notification for reclamant (ADAPTÉ pour table ami)
                $stmt = $pdo->prepare("SELECT user_id FROM reclamations WHERE id = ?");
                $stmt->execute([$reclamation_id]);
                $recl = $stmt->fetch();
                
                if ($recl) {
                    // ✅ Créer la notification (ADAPTÉ: type, reference_table, reference_id, contenu)
                    try {
                        $stmt = $pdo->prepare("
                            INSERT INTO notifications (user_id, type, reference_table, reference_id, contenu, lu, date_creation)
                            VALUES (?, 'demande_info', 'reclamations', ?, ?, 0, NOW())
                        ");
                        $stmt->execute([
                            $recl['user_id'],
                            $reclamation_id,
                            'Le gestionnaire demande plus d\'informations sur votre réclamation'
                        ]);
                    } catch (PDOException $e) {
                        error_log("Erreur création notification demande_info: " . $e->getMessage());
                    }
                }
                
                $message = "Demande d'informations envoyée!";
                $messageType = "success";
            }
        }
        
    } catch (PDOException $e) {
        $message = "Erreur: " . $e->getMessage();
        $messageType = "error";
        error_log("Detail reclamation error: " . $e->getMessage());
    }
}

// Fetch reclamation details
try {
    $stmt = $pdo->prepare("
        SELECT 
            r.*,
            c.nom AS categorie_nom,
            s.libelle AS statut_libelle,
            s.cle AS statut_cle,
            u.nom AS reclamant_nom,
            u.email AS reclamant_email,
            g.nom AS gestionnaire_nom
        FROM reclamations r
        LEFT JOIN categories c ON r.categorie_id = c.id
        LEFT JOIN statuts s ON r.statut_id = s.id
        LEFT JOIN users u ON r.user_id = u.id
        LEFT JOIN users g ON r.gestionnaire_id = g.id
        WHERE r.id = :id
    ");
    $stmt->execute([':id' => $reclamation_id]);
    $reclamation = $stmt->fetch();
    
    if (!$reclamation) {
        header('Location: reclamation.php');
        exit;
    }
    
    // Get all statuts
    $stmt = $pdo->query("SELECT id, cle, libelle FROM statuts ORDER BY id");
    $statuts = $stmt->fetchAll();
    
    // Get comments
    $stmt = $pdo->prepare("
        SELECT 
            cm.*,
            u.nom AS auteur_nom,
            u.role AS auteur_role
        FROM commentaires cm
        LEFT JOIN users u ON cm.auteur_id = u.id
        WHERE cm.reclamation_id = :id
        ORDER BY cm.date_commentaire ASC
    ");
    $stmt->execute([':id' => $reclamation_id]);
    $commentaires = $stmt->fetchAll();
    
    // Get attachments
    $stmt = $pdo->prepare("
        SELECT * FROM pieces_jointes 
        WHERE reclamation_id = :id 
        ORDER BY date_ajout ASC
    ");
    $stmt->execute([':id' => $reclamation_id]);
    $pieces_jointes = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("Fetch reclamation error: " . $e->getMessage());
    header('Location: reclamation.php');
    exit;
}

// Helper functions
function getStatusClass($statutCle) {
    $classes = [
        'en_cours'              => 'status-processing',
        'en_attente'            => 'status-pending',
        'acceptee'              => 'status-accepted',
        'rejetee'               => 'status-rejected',
        'fermee'                => 'status-closed',
        'attente_info_reclamant'=> 'status-info',
    ];
    return $classes[$statutCle] ?? 'status-default';
}

function formatDateTime($date) {
    return date('d/m/Y H:i:s', strtotime($date));
}

function formatDateShort($date) {
    return date('d/m/Y H:i', strtotime($date));
}

// Check if "demander plus d'infos" button should be shown
$canRequestInfo = in_array($reclamation['statut_cle'], ['en_attente', 'en_cours']);
?>
<?php include 'sidebar2.php'; ?>
<?php include 'topbar2.php'; ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Détail Réclamation - ReclaNova</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link href="https://fonts.googleapis.com/css2?family=Afacad:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="sidebar2.css">
    <link rel="stylesheet" href="topbar2.css">
    <link rel="stylesheet" href="detail_reclamation.css">
</head>
<body>

    <div class="main-container">
        
        <!-- Back Button -->
        <a href="reclamation.php" class="back-link">
            <i class='bx bx-arrow-back'></i> Retour à la liste
        </a>

        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <div class="detail-container">
            
            <!-- Left Column -->
            <div class="detail-left">
                
                <!-- Header with title and status -->
                <div class="reclamation-header">
                    <div class="header-top">
                        <h1 class="reclamation-title"><?php echo htmlspecialchars($reclamation['objet']); ?></h1>
                        <?php if ($canRequestInfo): ?>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="request_info">
                                <button type="submit" class="btn-request-info">
                                    demander plus d'informations
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                    <span class="status-badge <?php echo getStatusClass($reclamation['statut_cle']); ?>">
                        <?php echo htmlspecialchars($reclamation['statut_libelle']); ?>
                    </span>
                </div>

                <!-- Description -->
                <div class="section">
                    <h3 class="section-title-detail">description</h3>
                    <p class="description-text"><?php echo nl2br(htmlspecialchars($reclamation['description'])); ?></p>
                </div>

                <!-- User Info & History -->
                <div class="info-grid">
                    <div class="info-block">
                        <h3 class="section-title-detail">informations sur le réclamant</h3>
                        <div class="info-item">
                            <i class='bx bx-user'></i>
                            <span><?php echo htmlspecialchars($reclamation['reclamant_nom']); ?></span>
                        </div>
                        <div class="info-item">
                            <i class='bx bx-envelope'></i>
                            <span><?php echo htmlspecialchars($reclamation['reclamant_email']); ?></span>
                        </div>
                    </div>
                    
                    <div class="info-block">
                        <h3 class="section-title-detail">historique</h3>
                        <div class="history-item">
                            <span class="history-label">soumis le :</span>
                            <span class="history-value"><?php echo formatDateShort($reclamation['date_soumission']); ?></span>
                        </div>
                        <div class="history-item">
                            <span class="history-label">Dernière mise à jour :</span>
                            <span class="history-value"><?php echo formatDateShort($reclamation['date_dernier_update']); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Assigned To -->
                <div class="section">
                    <h3 class="section-title-detail">affecté à</h3>
                    <p class="assigned-name">
                        <?php 
                        if ($reclamation['gestionnaire_nom']) {
                            echo htmlspecialchars($reclamation['gestionnaire_nom']);
                        } elseif ($reclamation['statut_cle'] === 'en_attente') {
                            echo "En attente";
                        } else {
                            echo "Non assigné";
                        }
                        ?>
                    </p>
                </div>

                <!-- Attachments -->
                <?php if (!empty($pieces_jointes)): ?>
                <div class="section">
                    <h3 class="section-title-detail">pièces jointes</h3>
                    <div class="attachments-list">
                        <?php foreach ($pieces_jointes as $pj): ?>
                            <div class="attachment-item">
                                <?php 
                                $isImage = in_array($pj['mime'], ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);
                                if ($isImage): 
                                ?>
                                    <div class="attachment-preview">
                                        <img src="<?php echo htmlspecialchars($pj['chemin_fichier']); ?>" 
                                             alt="<?php echo htmlspecialchars($pj['nom_original']); ?>"
                                             onclick="openImageModal(this.src)">
                                    </div>
                                <?php else: ?>
                                    <div class="attachment-file">
                                        <i class='bx bx-file'></i>
                                        <a href="<?php echo htmlspecialchars($pj['chemin_fichier']); ?>" target="_blank">
                                            <?php echo htmlspecialchars($pj['nom_original']); ?>
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Update Status -->
                <div class="action-section">
                    <h3 class="action-title">mettre à jour le status</h3>
                    <p class="action-subtitle">changer le status de la réclamation</p>
                    <form method="POST" class="action-form">
                        <input type="hidden" name="action" value="update_status">
                        <select name="statut_id" class="action-select">
                            <?php foreach ($statuts as $statut): ?>
                                <option 
                                    value="<?php echo $statut['id']; ?>"
                                    <?php echo ($reclamation['statut_cle'] === $statut['cle']) ? 'selected' : ''; ?>
                                >
                                    <?php echo htmlspecialchars($statut['libelle']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn-action">changer statue</button>
                    </form>
                </div>

                <!-- Update Priority -->
                <div class="action-section">
                    <h3 class="action-title">mettre à jour la priorité</h3>
                    <p class="action-subtitle">changer la priorité de la réclamation</p>
                    <form method="POST" class="action-form">
                        <input type="hidden" name="action" value="update_priority">
                        <select name="priorite" class="action-select">
                            <option value="basse" <?php echo ($reclamation['priorite'] === 'basse') ? 'selected' : ''; ?>>Basse</option>
                            <option value="moyenne" <?php echo ($reclamation['priorite'] === 'moyenne') ? 'selected' : ''; ?>>Moyenne</option>
                            <option value="haute" <?php echo ($reclamation['priorite'] === 'haute') ? 'selected' : ''; ?>>Haute</option>
                        </select>
                        <button type="submit" class="btn-action">changer priorité</button>
                    </form>
                </div>

            </div>

            <!-- Right Column - Comments -->
            <div class="detail-right">
                <div class="comments-section">
                    <div class="comments-header">
                        <i class='bx bx-message-square-detail'></i>
                        <h3>Commentaire et mis à jour</h3>
                    </div>

                    <div class="comments-list">
                        <?php if (empty($commentaires)): ?>
                            <p class="no-comments">pas encore de commentaires</p>
                        <?php else: ?>
                            <?php foreach ($commentaires as $comment): ?>
                                <div class="comment-item <?php echo ($comment['auteur_role'] === 'gestionnaire') ? 'comment-gestionnaire' : 'comment-reclamant'; ?>">
                                    <div class="comment-header">
                                        <span class="comment-author">
                                            <?php echo htmlspecialchars($comment['auteur_nom'] ?? 'Utilisateur'); ?>
                                            <span class="comment-role">(<?php echo htmlspecialchars($comment['auteur_role'] ?? 'inconnu'); ?>)</span>
                                        </span>
                                        <span class="comment-date"><?php echo formatDateShort($comment['date_commentaire']); ?></span>
                                    </div>
                                    <p class="comment-message"><?php echo nl2br(htmlspecialchars($comment['message'])); ?></p>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <!-- Add Comment Form -->
                    <div class="add-comment">
                        <h4>Ajouter un commentaire</h4>
                        <form method="POST">
                            <input type="hidden" name="action" value="add_comment">
                            <textarea 
                                name="message" 
                                class="comment-textarea" 
                                placeholder="entrer votre commentaire..."
                                required
                            ></textarea>
                            <button type="submit" class="btn-add-comment">
                                Ajouter un commentaire
                            </button>
                        </form>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- Image Modal -->
    <div id="imageModal" class="image-modal" onclick="closeImageModal()">
        <span class="close-modal">&times;</span>
        <img id="modalImage" class="modal-content">
    </div>

    <script>
        function openImageModal(src) {
            document.getElementById('imageModal').style.display = 'flex';
            document.getElementById('modalImage').src = src;
        }
        
        function closeImageModal() {
            document.getElementById('imageModal').style.display = 'none';
        }
        
        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeImageModal();
            }
        });
    </script>

</body>
</html>