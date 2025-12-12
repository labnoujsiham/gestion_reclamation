<?php
/**
 * Détail Réclamation GESTIONNAIRE - SYSTÈME COMPLET
 * Avec popup auto demande d'infos
 */

session_start();
require_once 'db_config.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role'])) {
    header('Location: ../connexion/connexion.php');
    exit;
}

$current_gestionnaire_id = $_SESSION['user_id'];
$current_gestionnaire_nom = $_SESSION['user_name'];

$page_title = "Détail réclamation";
$pdo = getDBConnection();
$reclamation_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

$reclamation = null;
$commentaires = [];
$statuts = [];
$pieces_jointes = [];
$message = '';
$messageType = '';
$show_info_modal = false; // Pour afficher popup demande infos

if (!$pdo || $reclamation_id <= 0) {
    header('Location: reclamation.php');
    exit;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        
        // ===== UPDATE STATUS =====
        if (isset($_POST['action']) && $_POST['action'] === 'update_status') {
            $new_statut_id = intval($_POST['statut_id']);
            
            // Récupérer le statut cle
            $stmt = $pdo->prepare("SELECT cle FROM statuts WHERE id = ?");
            $stmt->execute([$new_statut_id]);
            $new_statut = $stmt->fetch();
            
            // Mettre à jour le statut
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
            
            // Si changement vers "attente_info_reclamant" → Afficher popup
            if ($new_statut && $new_statut['cle'] === 'attente_info_reclamant') {
                $show_info_modal = true;
            } else {
                // ✅ NOTIFICATION UNIQUEMENT SI STATUT = FERMÉE
                if ($new_statut && $new_statut['cle'] === 'fermee') {
                    try {
                        $stmt = $pdo->prepare("SELECT user_id FROM reclamations WHERE id = ?");
                        $stmt->execute([$reclamation_id]);
                        $recl = $stmt->fetch();
                        
                        if ($recl) {
                            $stmt = $pdo->prepare("
                                INSERT INTO notifications (user_id, type, reference_table, reference_id, contenu, lu, date_creation) 
                                VALUES (?, 'statut_ferme', 'reclamations', ?, 'Votre réclamation a été clôturée', 0, NOW())
                            ");
                            $stmt->execute([$recl['user_id'], $reclamation_id]);
                        }
                    } catch (PDOException $e) {
                        error_log("Erreur notification fermée: " . $e->getMessage());
                    }
                }
                
                $message = "Statut mis à jour avec succès!";
                $messageType = "success";
            }
        }
        
        // ===== DEMANDE D'INFOS (depuis popup) =====
        if (isset($_POST['action']) && $_POST['action'] === 'send_info_request') {
            $infos_demandees = trim($_POST['infos_demandees']);
            
            if (!empty($infos_demandees)) {
                // Créer commentaire type demande_info
                $stmt = $pdo->prepare("
                    INSERT INTO commentaires (reclamation_id, auteur_id, message, type_commentaire, infos_demandees, visible_par_reclamant, date_commentaire)
                    VALUES (:reclamation_id, :auteur_id, :message, 'demande_info', :infos_demandees, 1, NOW())
                ");
                $stmt->execute([
                    ':reclamation_id' => $reclamation_id,
                    ':auteur_id' => $current_gestionnaire_id,
                    ':message' => 'Demande d\'informations supplémentaires',
                    ':infos_demandees' => $infos_demandees
                ]);
                
                $pdo->prepare("UPDATE reclamations SET date_dernier_update = NOW() WHERE id = ?")->execute([$reclamation_id]);
                
                // Notification pour user
                $stmt = $pdo->prepare("SELECT user_id FROM reclamations WHERE id = ?");
                $stmt->execute([$reclamation_id]);
                $recl = $stmt->fetch();
                
                if ($recl) {
                    $stmt = $pdo->prepare("
                        INSERT INTO notifications (user_id, type, reference_table, reference_id, contenu, lu, date_creation)
                        VALUES (?, 'demande_info', 'reclamations', ?, ?, 0, NOW())
                    ");
                    $stmt->execute([
                        $recl['user_id'],
                        $reclamation_id,
                        'Le gestionnaire demande plus d\'informations'
                    ]);
                }
                
                $message = "Demande d'informations envoyée!";
                $messageType = "success";
            }
        }
        
        // ===== UPDATE PRIORITY =====
        if (isset($_POST['action']) && $_POST['action'] === 'update_priority') {
            $new_priorite = $_POST['priorite'];
            
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
                
                $message = "Priorité mise à jour!";
                $messageType = "success";
            }
        }
        
        // ===== ADD COMMENT =====
        if (isset($_POST['action']) && $_POST['action'] === 'add_comment') {
            $comment_message = trim($_POST['message']);
            
            if (!empty($comment_message)) {
                $stmt = $pdo->prepare("
                    INSERT INTO commentaires (reclamation_id, auteur_id, message, type_commentaire, visible_par_reclamant, date_commentaire)
                    VALUES (:reclamation_id, :auteur_id, :message, 'commentaire', 1, NOW())
                ");
                $stmt->execute([
                    ':reclamation_id' => $reclamation_id,
                    ':auteur_id' => $current_gestionnaire_id,
                    ':message' => $comment_message
                ]);
                
                $pdo->prepare("UPDATE reclamations SET date_dernier_update = NOW() WHERE id = ?")->execute([$reclamation_id]);
                
                // Notification user
                $stmt = $pdo->prepare("SELECT user_id FROM reclamations WHERE id = ?");
                $stmt->execute([$reclamation_id]);
                $recl = $stmt->fetch();
                
                if ($recl) {
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
                
                $message = "Commentaire ajouté!";
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
    
    $stmt = $pdo->query("SELECT id, cle, libelle FROM statuts ORDER BY id");
    $statuts = $stmt->fetchAll();
    
    // Récupérer commentaires avec type
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
    
    // Séparer par type
    $demandes_info = [];
    $commentaires_normaux = [];
    
    foreach ($commentaires as $comment) {
        if (isset($comment['type_commentaire']) && $comment['type_commentaire'] === 'demande_info') {
            $demandes_info[] = $comment;
        } elseif (isset($comment['type_commentaire']) && $comment['type_commentaire'] === 'reponse_info') {
            // Les réponses d'infos vont avec les demandes
            $demandes_info[] = $comment;
        } else {
            $commentaires_normaux[] = $comment;
        }
    }
    
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

function formatDateShort($date) {
    return date('d/m/Y H:i', strtotime($date));
}
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
    
    <style>
    /* POPUP DEMANDE D'INFOS */
    .modal-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0,0,0,0.5);
        z-index: 9999;
        align-items: center;
        justify-content: center;
    }
    
    .modal-overlay.show {
        display: flex;
    }
    
    .modal-content {
        background: white;
        border-radius: 12px;
        padding: 30px;
        max-width: 500px;
        width: 90%;
        box-shadow: 0 10px 40px rgba(0,0,0,0.3);
        animation: slideDown 0.3s ease;
    }
    
    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-50px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    .modal-header {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 2px solid #f0f0f0;
    }
    
    .modal-header i {
        font-size: 32px;
        color: #ff9800;
    }
    
    .modal-header h3 {
        font-size: 20px;
        color: #2d3748;
        margin: 0;
    }
    
    .modal-body label {
        display: block;
        font-size: 14px;
        font-weight: 600;
        color: #2d3748;
        margin-bottom: 10px;
    }
    
    .modal-body textarea {
        width: 100%;
        min-height: 120px;
        padding: 12px;
        border: 2px solid #e0e0e0;
        border-radius: 8px;
        font-family: 'Afacad', sans-serif;
        font-size: 14px;
        resize: vertical;
        transition: border-color 0.3s;
    }
    
    .modal-body textarea:focus {
        outline: none;
        border-color: #45AECC;
    }
    
    .modal-templates {
        margin-top: 15px;
        padding: 15px;
        background: #f8f9fa;
        border-radius: 8px;
    }
    
    .modal-templates p {
        font-size: 12px;
        color: #666;
        margin-bottom: 10px;
        font-weight: 600;
    }
    
    .template-buttons {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }
    
    .template-btn {
        padding: 6px 12px;
        background: white;
        border: 1px solid #ddd;
        border-radius: 6px;
        font-size: 12px;
        color: #666;
        cursor: pointer;
        transition: all 0.3s;
    }
    
    .template-btn:hover {
        background: #45AECC;
        color: white;
        border-color: #45AECC;
    }
    
    .modal-footer {
        display: flex;
        gap: 10px;
        margin-top: 20px;
        justify-content: flex-end;
    }
    
    .modal-btn {
        padding: 10px 20px;
        border-radius: 8px;
        border: none;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s;
    }
    
    .modal-btn-cancel {
        background: #f0f0f0;
        color: #666;
    }
    
    .modal-btn-cancel:hover {
        background: #e0e0e0;
    }
    
    .modal-btn-send {
        background: #ff9800;
        color: white;
    }
    
    .modal-btn-send:hover {
        background: #f57c00;
    }
    
    /* Sections commentaires */
    .section-title-comments {
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 16px;
        font-weight: 600;
        color: #2d3748;
        margin: 20px 0 15px 0;
        padding: 10px 15px;
        background: #f8f9fa;
        border-radius: 8px;
    }
    
    .section-title-comments.info-section {
        background: #fff3cd;
        color: #856404;
    }
    
    .section-count {
        background: white;
        color: #666;
        padding: 2px 10px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 600;
        margin-left: auto;
    }
    
    .comment-badge {
        display: inline-block;
        padding: 3px 10px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 600;
        margin-left: 8px;
        text-transform: uppercase;
    }
    
    .comment-badge.badge-info {
        background: #ff9800;
        color: white;
    }
    
    .comment-badge.badge-comment {
        background: #45AECC;
        color: white;
    }
    </style>
</head>
<body>

    <div class="main-container">
        
        <a href="reclamation.php" class="back-link">
            <i class='bx bx-arrow-back'></i> Retour à la liste
        </a>

        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <div class="detail-container">
            
            <div class="detail-left">
                
                <div class="reclamation-header">
                    <div class="header-top">
                        <h1 class="reclamation-title"><?php echo htmlspecialchars($reclamation['objet']); ?></h1>
                    </div>
                    <span class="status-badge <?php echo getStatusClass($reclamation['statut_cle']); ?>">
                        <?php echo htmlspecialchars($reclamation['statut_libelle']); ?>
                    </span>
                </div>

                <div class="section">
                    <h3 class="section-title-detail">description</h3>
                    <p class="description-text"><?php echo nl2br(htmlspecialchars($reclamation['description'])); ?></p>
                </div>

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

                <div class="section">
                    <h3 class="section-title-detail">affecté à</h3>
                    <p class="assigned-name">
                        <?php 
                        if ($reclamation['gestionnaire_nom']) {
                            echo htmlspecialchars($reclamation['gestionnaire_nom']);
                        } else {
                            echo "Non assigné";
                        }
                        ?>
                    </p>
                </div>

                <?php if (!empty($pieces_jointes)): ?>
                <div class="section">
                    <h3 class="section-title-detail">pièces jointes</h3>
                    <div class="attachments-list">
                        <?php foreach ($pieces_jointes as $pj): ?>
                            <div class="attachment-item">
                                <i class='bx bx-file'></i>
                                <a href="<?php echo htmlspecialchars($pj['chemin_fichier']); ?>" target="_blank">
                                    <?php echo htmlspecialchars($pj['nom_original']); ?>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <div class="action-section">
                    <h3 class="action-title">mettre à jour le statut</h3>
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
                        <button type="submit" class="btn-action">changer statut</button>
                    </form>
                </div>

                

            </div>

            <div class="detail-right">
                <div class="comments-section">
                    <div class="comments-header">
                        <i class='bx bx-message-square-detail'></i>
                        <h3>Communication</h3>
                    </div>

                    <!-- DEMANDES D'INFOS -->
                    <?php if (count($demandes_info) > 0): ?>
                    <div class="section-title-comments info-section">
                        <i class='bx bx-info-circle'></i>
                        <span>DEMANDES D'INFORMATIONS</span>
                        <span class="section-count"><?php echo count($demandes_info); ?></span>
                    </div>
                    
                    <div class="comments-list">
                        <?php foreach ($demandes_info as $comment): ?>
                            <div class="comment-item comment-<?php echo $comment['auteur_role']; ?>">
                                <div class="comment-header">
                                    <span class="comment-author">
                                        <?php echo htmlspecialchars($comment['auteur_nom']); ?>
                                        <span class="comment-badge badge-info">⚠️ INFO</span>
                                    </span>
                                    <span class="comment-date"><?php echo formatDateShort($comment['date_commentaire']); ?></span>
                                </div>
                                <p class="comment-message">
                                    <?php 
                                    if (!empty($comment['infos_demandees'])) {
                                        echo nl2br(htmlspecialchars($comment['infos_demandees']));
                                    } else {
                                        echo nl2br(htmlspecialchars($comment['message']));
                                    }
                                    ?>
                                </p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <!-- COMMENTAIRES -->
                    <?php if (count($commentaires_normaux) > 0): ?>
                    <div class="section-title-comments">
                        <i class='bx bx-chat'></i>
                        <span>COMMENTAIRES</span>
                        <span class="section-count"><?php echo count($commentaires_normaux); ?></span>
                    </div>
                    
                    <div class="comments-list">
                        <?php foreach ($commentaires_normaux as $comment): ?>
                            <div class="comment-item comment-<?php echo $comment['auteur_role']; ?>">
                                <div class="comment-header">
                                    <span class="comment-author">
                                        <?php echo htmlspecialchars($comment['auteur_nom']); ?>
                                        <?php if ($comment['auteur_role'] === 'gestionnaire'): ?>
                                        <span class="comment-badge badge-comment">💬 RÉPONSE</span>
                                        <?php endif; ?>
                                    </span>
                                    <span class="comment-date"><?php echo formatDateShort($comment['date_commentaire']); ?></span>
                                </div>
                                <p class="comment-message"><?php echo nl2br(htmlspecialchars($comment['message'])); ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <!-- AJOUTER COMMENTAIRE -->
                    <div class="add-comment">
                        <h4>Ajouter un commentaire</h4>
                        <form method="POST">
                            <input type="hidden" name="action" value="add_comment">
                            <textarea 
                                name="message" 
                                class="comment-textarea" 
                                placeholder="Votre commentaire..."
                                required
                            ></textarea>
                            <button type="submit" class="btn-add-comment">
                                Envoyer
                            </button>
                        </form>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- POPUP DEMANDE D'INFOS -->
    <div class="modal-overlay <?php echo $show_info_modal ? 'show' : ''; ?>" id="infoModal">
        <div class="modal-content">
            <div class="modal-header">
                <i class='bx bx-info-circle'></i>
                <h3>Demande d'informations</h3>
            </div>
            
            <form method="POST">
                <input type="hidden" name="action" value="send_info_request">
                
                <div class="modal-body">
                    <label>Précisez les informations demandées :</label>
                    <textarea 
                        name="infos_demandees" 
                        id="infosTextarea"
                        placeholder="Ex: Veuillez fournir la facture et les photos du problème..."
                        required
                    ></textarea>
                    
                    <div class="modal-templates">
                        <p>Templates rapides :</p>
                        <div class="template-buttons">
                            <button type="button" class="template-btn" onclick="addTemplate('Veuillez fournir une copie de la facture')">📄 Facture</button>
                            <button type="button" class="template-btn" onclick="addTemplate('Veuillez fournir des photos du problème')">📸 Photos</button>
                            <button type="button" class="template-btn" onclick="addTemplate('Veuillez fournir le numéro de contrat')">📋 N° Contrat</button>
                            <button type="button" class="template-btn" onclick="addTemplate('Veuillez fournir une description détaillée')">📝 Description</button>
                            <button type="button" class="template-btn" onclick="addTemplate('Veuillez fournir la date exacte de l\'incident')">📅 Date</button>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="modal-btn modal-btn-cancel" onclick="closeModal()">Annuler</button>
                    <button type="submit" class="modal-btn modal-btn-send">Envoyer la demande</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function addTemplate(text) {
            const textarea = document.getElementById('infosTextarea');
            const currentValue = textarea.value.trim();
            
            if (currentValue === '') {
                textarea.value = text;
            } else {
                textarea.value = currentValue + '\n' + text;
            }
            
            textarea.focus();
        }
        
        function closeModal() {
            document.getElementById('infoModal').classList.remove('show');
        }
        
        // Si modal affiché, focus sur textarea
        <?php if ($show_info_modal): ?>
        document.getElementById('infosTextarea').focus();
        <?php endif; ?>
    </script>

</body>
</html>