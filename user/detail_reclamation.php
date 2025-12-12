<?php 
/**
 * Détail Réclamation USER - SYSTÈME COMPLET
 * Avec audio + pièces jointes + zones intelligentes
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: ../connexion/connexion.php');
    exit();
}

$userId = $_SESSION['user_id'];
$userName = $_SESSION['user_name'] ?? '';
$userEmail = $_SESSION['user_email'] ?? '';
$userRole = $_SESSION['user_role'] ?? 'reclamant';

require_once '../connexion/db_config.php';

$reclamationId = $_GET['id'] ?? 0;
$message_success = '';
$message_error = '';

// ===== TRAITEMENT COMMENTAIRE =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_comment') {
    $comment_text = trim($_POST['message']);
    $audio_data = $_POST['audio_data'] ?? '';
    
    if (!empty($comment_text) || !empty($audio_data)) {
        try {
            $audio_filename = null;
            
            // Traiter l'audio si présent
            if (!empty($audio_data)) {
                $upload_dir = '../uploads/audio/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $audio_filename = 'audio_' . time() . '_' . $userId . '.webm';
                $audio_path = $upload_dir . $audio_filename;
                
                // Décoder base64 et sauvegarder
                $audio_binary = base64_decode(preg_replace('#^data:audio/\w+;base64,#i', '', $audio_data));
                file_put_contents($audio_path, $audio_binary);
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO commentaires (reclamation_id, auteur_id, message, audio_transcription, audio_fichier, type_commentaire, date_commentaire) 
                VALUES (?, ?, ?, ?, ?, 'commentaire', NOW())
            ");
            $stmt->execute([
                $reclamationId, 
                $userId, 
                $comment_text,
                !empty($audio_data) ? $comment_text : null,
                $audio_filename
            ]);
            
            // Notification gestionnaire
            try {
                $stmt = $pdo->prepare("SELECT gestionnaire_id FROM reclamations WHERE id = ?");
                $stmt->execute([$reclamationId]);
                $recl = $stmt->fetch();
                
                if ($recl && $recl['gestionnaire_id']) {
                    $stmt = $pdo->prepare("
                        INSERT INTO notifications (user_id, type, reference_table, reference_id, contenu, lu, date_creation) 
                        VALUES (?, 'nouveau_commentaire', 'reclamations', ?, ?, 0, NOW())
                    ");
                    $stmt->execute([
                        $recl['gestionnaire_id'],
                        $reclamationId,
                        'Le réclamant a répondu'
                    ]);
                }
            } catch (PDOException $e) {
                error_log("Erreur notif: " . $e->getMessage());
            }
            
            $message_success = "Commentaire ajouté !";
            header("Location: detail_reclamation.php?id=" . $reclamationId . "#commentaires");
            exit;
        } catch (PDOException $e) {
            $message_error = "Erreur lors de l'envoi";
            error_log($e->getMessage());
        }
    }
}

// ===== TRAITEMENT RÉPONSE INFOS =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_info_response') {
    $response_text = trim($_POST['info_message']);
    
    if (!empty($response_text) || !empty($_FILES['info_files']['name'][0])) {
        try {
            // Créer commentaire reponse_info
            $stmt = $pdo->prepare("
                INSERT INTO commentaires (reclamation_id, auteur_id, message, type_commentaire, visible_par_reclamant, date_commentaire)
                VALUES (?, ?, ?, 'reponse_info', 1, NOW())
            ");
            $stmt->execute([
                $reclamationId,
                $userId,
                !empty($response_text) ? $response_text : 'Pièces jointes fournies'
            ]);
            
            $comment_id = $pdo->lastInsertId();
            
            // Traiter les fichiers
            if (!empty($_FILES['info_files']['name'][0])) {
                $upload_dir = '../uploads/infos/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                foreach ($_FILES['info_files']['name'] as $key => $filename) {
                    if ($_FILES['info_files']['error'][$key] === UPLOAD_ERR_OK) {
                        $tmp_name = $_FILES['info_files']['tmp_name'][$key];
                        $file_size = $_FILES['info_files']['size'][$key];
                        $file_type = $_FILES['info_files']['type'][$key];
                        
                        $safe_filename = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
                        $file_path = $upload_dir . $safe_filename;
                        
                        if (move_uploaded_file($tmp_name, $file_path)) {
                            $stmt = $pdo->prepare("
                                INSERT INTO pieces_jointes_infos (commentaire_id, nom_original, nom_stockage, chemin_fichier, mime, taille, date_ajout)
                                VALUES (?, ?, ?, ?, ?, ?, NOW())
                            ");
                            $stmt->execute([
                                $comment_id,
                                $filename,
                                $safe_filename,
                                $file_path,
                                $file_type,
                                $file_size
                            ]);
                        }
                    }
                }
            }
            
            // Changer statut à "en_cours"
            $stmt = $pdo->prepare("SELECT id FROM statuts WHERE cle = 'en_cours'");
            $stmt->execute();
            $statut_en_cours = $stmt->fetch();
            
            if ($statut_en_cours) {
                $stmt = $pdo->prepare("UPDATE reclamations SET statut_id = ?, date_dernier_update = NOW() WHERE id = ?");
                $stmt->execute([$statut_en_cours['id'], $reclamationId]);
            }
            
            // Notification gestionnaire
            $stmt = $pdo->prepare("SELECT gestionnaire_id FROM reclamations WHERE id = ?");
            $stmt->execute([$reclamationId]);
            $recl = $stmt->fetch();
            
            if ($recl && $recl['gestionnaire_id']) {
                $stmt = $pdo->prepare("
                    INSERT INTO notifications (user_id, type, reference_table, reference_id, contenu, lu, date_creation)
                    VALUES (?, 'info_fournie', 'reclamations', ?, ?, 0, NOW())
                ");
                $stmt->execute([
                    $recl['gestionnaire_id'],
                    $reclamationId,
                    'Le réclamant a fourni les informations demandées'
                ]);
            }
            
            $message_success = "Informations envoyées !";
            header("Location: detail_reclamation.php?id=" . $reclamationId . "#commentaires");
            exit;
        } catch (PDOException $e) {
            $message_error = "Erreur";
            error_log($e->getMessage());
        }
    }
}

// Récupérer les détails
try {
    $stmt = $pdo->prepare("
        SELECT 
            r.*,
            c.nom AS categorie_nom,
            s.libelle AS statut_libelle,
            s.cle AS statut_cle,
            u.nom AS reclamant_nom,
            g.nom AS gestionnaire_nom,
            g.email AS gestionnaire_email,
            g.role AS gestionnaire_role
        FROM reclamations r
        LEFT JOIN categories c ON r.categorie_id = c.id
        LEFT JOIN statuts s ON r.statut_id = s.id
        LEFT JOIN users u ON r.user_id = u.id
        LEFT JOIN users g ON r.gestionnaire_id = g.id
        WHERE r.id = ? AND r.user_id = ?
    ");
    $stmt->execute([$reclamationId, $userId]);
    $reclamation = $stmt->fetch();
    
    if (!$reclamation) {
        header('Location: mes_reclamations.php');
        exit;
    }
    
    $stmt = $pdo->prepare("SELECT * FROM pieces_jointes WHERE reclamation_id = ?");
    $stmt->execute([$reclamationId]);
    $pieces_jointes = $stmt->fetchAll();
    
    $stmt = $pdo->prepare("
        SELECT 
            h.*,
            s_ancien.libelle AS ancien_statut_libelle,
            s_nouveau.libelle AS nouveau_statut_libelle,
            u.nom AS modif_par_nom
        FROM historique_statuts h
        LEFT JOIN statuts s_ancien ON h.ancien_statut_id = s_ancien.id
        LEFT JOIN statuts s_nouveau ON h.nouveau_statut_id = s_nouveau.id
        LEFT JOIN users u ON h.modif_par = u.id
        WHERE h.reclamation_id = ?
        ORDER BY h.date_modification ASC
    ");
    $stmt->execute([$reclamationId]);
    $historique = $stmt->fetchAll();
    
    // Commentaires avec type
    $stmt = $pdo->prepare("
        SELECT 
            c.*,
            u.nom AS auteur_nom,
            u.role AS auteur_role
        FROM commentaires c
        LEFT JOIN users u ON c.auteur_id = u.id
        WHERE c.reclamation_id = ?
        ORDER BY c.date_commentaire ASC
    ");
    $stmt->execute([$reclamationId]);
    $commentaires = $stmt->fetchAll();
    
    // Séparer par type
    $demandes_info = [];
    $commentaires_normaux = [];
    $derniere_demande_info = null;
    
    foreach ($commentaires as $comment) {
        if (isset($comment['type_commentaire'])) {
            if ($comment['type_commentaire'] === 'demande_info') {
                $demandes_info[] = $comment;
                $derniere_demande_info = $comment;
            } elseif ($comment['type_commentaire'] === 'reponse_info') {
                $demandes_info[] = $comment;
            } else {
                $commentaires_normaux[] = $comment;
            }
        } else {
            $commentaires_normaux[] = $comment;
        }
    }
    
    // Vérifier si gestionnaire a écrit
    $gestionnaire_a_ecrit = false;
    foreach ($commentaires as $comment) {
        if ($comment['auteur_role'] === 'gestionnaire' || $comment['auteur_role'] === 'administrateur') {
            $gestionnaire_a_ecrit = true;
            break;
        }
    }
    
    // Déterminer si peut commenter/répondre
    $statut_cle = $reclamation['statut_cle'];
    $peut_commenter = false;
    $peut_repondre_info = false;
    $message_desactive = '';
    
    if (in_array($statut_cle, ['fermee', 'acceptee', 'rejetee'])) {
        // Réclamation clôturée → Tout bloqué
        $peut_commenter = false;
        $peut_repondre_info = false;
        $message_desactive = 'Réclamation clôturée';
    } elseif ($statut_cle === 'attente_info_reclamant') {
        // ✅ EN ATTENTE D'INFOS → BLOQUER commentaires normaux, FORCER réponse infos
        $peut_commenter = false; // ❌ Pas de commentaires normaux
        $peut_repondre_info = true; // ✅ DOIT répondre aux infos
        $message_desactive = 'Vous devez d\'abord répondre à la demande d\'informations ci-dessus';
    } elseif ($gestionnaire_a_ecrit) {
        $peut_commenter = true;
    } else {
        $message_desactive = 'En attente du gestionnaire';
    }
    
} catch (PDOException $e) {
    header('Location: mes_reclamations.php');
    exit;
}

$progression_steps = [
    'en_attente' => 1,
    'attente_info_reclamant' => 2,
    'en_cours' => 2,
    'acceptee' => 3,
    'rejetee' => 3,
    'fermee' => 4
];
$current_step = $progression_steps[$reclamation['statut_cle']] ?? 1;

$user = [
    'id' => $userId,
    'nom' => $userName,
    'email' => $userEmail,
    'role' => $userRole
];
?>
<?php include 'sidebar2.php'; ?>
<?php include 'topbar2.php'; ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Détails Réclamation #<?php echo $reclamation['id']; ?> - ReclaNova</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link href="https://fonts.googleapis.com/css2?family=Afacad:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="sidebar2.css">
    <link rel="stylesheet" href="topbar2.css">
    <link rel="stylesheet" href="detail_reclamation.css">
    
    <style>
    /* Sections */
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
        padding: 2px 10px;
        border-radius: 12px;
        font-size: 12px;
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
    
    /* Bouton audio */
    .audio-btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 16px;
        background: #f0f0f0;
        border: 2px solid #ddd;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.3s;
        font-size: 14px;
        font-weight: 600;
        color: #666;
    }
    
    .audio-btn:hover {
        background: #45AECC;
        border-color: #45AECC;
        color: white;
    }
    
    .audio-btn.recording {
        background: #e74c3c;
        border-color: #e74c3c;
        color: white;
        animation: pulse 1s infinite;
    }
    
    @keyframes pulse {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.05); }
    }
    
    /* Zone désactivée */
    .zone-desactivee {
        background: #f5f5f5;
        border: 2px dashed #ddd;
        border-radius: 8px;
        padding: 30px 20px;
        text-align: center;
        margin: 20px 0;
    }
    
    .zone-desactivee i {
        font-size: 48px;
        color: #ccc;
        margin-bottom: 15px;
        display: block;
    }
    
    .zone-desactivee p {
        color: #999;
        margin: 5px 0;
        font-size: 14px;
    }
    
    .zone-desactivee p.main {
        font-weight: 600;
        color: #666;
        font-size: 16px;
    }
    
    /* Réponse infos */
    .info-response-form {
        background: #fff3cd;
        border: 2px solid #ffc107;
        border-radius: 8px;
        padding: 20px;
        margin: 20px 0;
    }
    
    .info-response-header {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 15px;
        padding-bottom: 10px;
        border-bottom: 2px solid #ffc107;
    }
    
    .info-response-header i {
        font-size: 24px;
        color: #ff9800;
    }
    
    .info-response-header h4 {
        margin: 0;
        color: #856404;
    }
    
    .infos-demandees-box {
        background: white;
        padding: 15px;
        border-radius: 6px;
        margin-bottom: 15px;
        border-left: 4px solid #ff9800;
    }
    
    .infos-demandees-box p {
        margin: 0;
        color: #666;
        font-size: 14px;
        line-height: 1.6;
    }
    
    .file-upload-zone {
        border: 2px dashed #ffc107;
        border-radius: 8px;
        padding: 20px;
        text-align: center;
        background: white;
        cursor: pointer;
        transition: all 0.3s;
        margin-bottom: 15px;
    }
    
    .file-upload-zone:hover {
        border-color: #ff9800;
        background: #fffbf0;
    }
    
    .file-upload-zone i {
        font-size: 48px;
        color: #ff9800;
        margin-bottom: 10px;
        display: block;
    }
    
    .file-list {
        margin-top: 10px;
    }
    
    .file-item {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 8px 12px;
        background: white;
        border-radius: 6px;
        margin-bottom: 5px;
        font-size: 13px;
    }
    
    .file-item i {
        color: #ff9800;
    }
    
    .btn-send-info {
        width: 100%;
        padding: 12px;
        background: #ff9800;
        color: white;
        border: none;
        border-radius: 8px;
        font-size: 15px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s;
    }
    
    .btn-send-info:hover {
        background: #f57c00;
        transform: translateY(-2px);
    }
    </style>
</head>
<body>
    <div class="detail-container">
        <a href="mes_reclamations.php" class="back-btn">
            <i class='bx bx-arrow-back'></i>
            Retour à mes réclamations
        </a>
        
        <?php if ($message_success): ?>
        <div class="alert alert-success"><?php echo $message_success; ?></div>
        <?php endif; ?>
        
        <?php if ($message_error): ?>
        <div class="alert alert-error"><?php echo $message_error; ?></div>
        <?php endif; ?>
        
        <!-- DÉTAILS RÉCLAMATION (identique à avant) -->
        <div class="detail-card">
            <div class="detail-header">
                <div>
                    <h1 class="detail-title"><?php echo htmlspecialchars($reclamation['objet']); ?></h1>
                    <p class="detail-id">Réclamation #<?php echo $reclamation['id']; ?></p>
                </div>
                <span class="status-badge status-<?php echo $reclamation['statut_cle']; ?>">
                    <?php echo htmlspecialchars($reclamation['statut_libelle']); ?>
                </span>
            </div>
            
            <!-- Grid, historique, etc. (garder ton code existant) -->
            <div class="detail-grid">
                <div class="detail-section">
                    <div class="detail-label">Catégorie</div>
                    <div class="detail-value"><?php echo htmlspecialchars($reclamation['categorie_nom']); ?></div>
                </div>
                <div class="detail-section">
                    <div class="detail-label">Priorité</div>
                    <div class="detail-value">
                        <span class="priority-badge priority-<?php echo $reclamation['priorite']; ?>">
                            <?php echo ucfirst($reclamation['priorite']); ?>
                        </span>
                    </div>
                </div>
                <div class="detail-section">
                    <div class="detail-label">Date de soumission</div>
                    <div class="detail-value"><?php echo date('d/m/Y à H:i', strtotime($reclamation['date_soumission'])); ?></div>
                </div>
                <div class="detail-section">
                    <div class="detail-label">Dernière mise à jour</div>
                    <div class="detail-value"><?php echo date('d/m/Y à H:i', strtotime($reclamation['date_dernier_update'])); ?></div>
                </div>
            </div>
            
            <div class="detail-section">
                <div class="detail-label">Description</div>
                <div class="detail-value"><?php echo nl2br(htmlspecialchars($reclamation['description'])); ?></div>
            </div>
            
            <!-- Progression, pièces jointes, etc. (garder ton code) -->
        </div>
        
        <!-- SECTION COMMENTAIRES -->
        <div class="comments-section" id="commentaires">
            <div class="comments-header">
                <i class='bx bx-message-square-dots'></i>
                <h3>Communication</h3>
            </div>
            
            <!-- DEMANDES D'INFOS -->
            <?php if (count($demandes_info) > 0): ?>
            <div class="section-title-comments info-section">
                <i class='bx bx-info-circle'></i>
                <span>DEMANDES D'INFORMATIONS</span>
                <span class="section-count"><?php echo count($demandes_info); ?></span>
            </div>
            
            <div class="chat-container">
                <?php foreach ($demandes_info as $comment): ?>
                    <?php 
                    $isGestionnaire = ($comment['auteur_role'] === 'gestionnaire');
                    $isReponse = ($comment['type_commentaire'] === 'reponse_info');
                    ?>
                    <div class="chat-message <?php echo $isGestionnaire ? 'gestionnaire' : 'reclamant'; ?>">
                        <div class="message-bubble">
                            <div class="message-author">
                                <i class='bx <?php echo $isGestionnaire ? 'bx-shield-alt-2' : 'bx-user'; ?>'></i>
                                <?php echo $isGestionnaire ? 'Gestionnaire' : 'Vous'; ?>
                                <span class="comment-badge badge-info">
                                    <?php echo $isReponse ? '📎 RÉPONSE' : '⚠️ DEMANDE'; ?>
                                </span>
                            </div>
                            <div class="message-text">
                                <?php 
                                if ($isGestionnaire && !empty($comment['infos_demandees'])) {
                                    echo nl2br(htmlspecialchars($comment['infos_demandees']));
                                } else {
                                    echo nl2br(htmlspecialchars($comment['message']));
                                }
                                ?>
                            </div>
                            <div class="message-time">
                                <?php echo date('d/m/Y à H:i', strtotime($comment['date_commentaire'])); ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- FORMULAIRE RÉPONSE INFOS -->
            <?php if ($peut_repondre_info && $derniere_demande_info): ?>
            <div class="info-response-form">
                <div class="info-response-header">
                    <i class='bx bx-file-find'></i>
                    <h4>Fournir les informations demandées</h4>
                </div>
                
                <div class="infos-demandees-box">
                    <p><strong>Informations demandées :</strong></p>
                    <p><?php echo nl2br(htmlspecialchars($derniere_demande_info['infos_demandees'] ?? $derniere_demande_info['message'])); ?></p>
                </div>
                
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="send_info_response">
                    
                    <div class="file-upload-zone" onclick="document.getElementById('info_files').click()">
                        <i class='bx bx-cloud-upload'></i>
                        <p><strong>Cliquez pour ajouter des fichiers</strong></p>
                        <p style="font-size: 12px; color: #999; margin-top: 5px;">Factures, photos, documents...</p>
                    </div>
                    
                    <input type="file" id="info_files" name="info_files[]" multiple style="display: none;" onchange="showFiles(this)">
                    
                    <div id="filesList" class="file-list"></div>
                    
                    <textarea 
                        name="info_message" 
                        class="comment-textarea" 
                        placeholder="Message complémentaire (optionnel)..."
                        style="margin-top: 15px;"
                    ></textarea>
                    
                    <button type="submit" class="btn-send-info">
                        <i class='bx bx-send'></i> Envoyer les informations
                    </button>
                </form>
            </div>
            <?php endif; ?>
            <?php endif; ?>
            
            <!-- COMMENTAIRES NORMAUX -->
            <?php if (count($commentaires_normaux) > 0): ?>
            <div class="section-title-comments">
                <i class='bx bx-chat'></i>
                <span>COMMENTAIRES</span>
                <span class="section-count"><?php echo count($commentaires_normaux); ?></span>
            </div>
            
            <div class="chat-container" id="chatContainer">
                <?php foreach ($commentaires_normaux as $comment): ?>
                    <?php 
                    $isGestionnaire = ($comment['auteur_role'] === 'gestionnaire' || $comment['auteur_role'] === 'administrateur');
                    ?>
                    <div class="chat-message <?php echo $isGestionnaire ? 'gestionnaire' : 'reclamant'; ?>">
                        <div class="message-bubble">
                            <div class="message-author">
                                <i class='bx <?php echo $isGestionnaire ? 'bx-shield-alt-2' : 'bx-user'; ?>'></i>
                                <?php echo $isGestionnaire ? 'Gestionnaire' : 'Vous'; ?>
                                <?php if ($isGestionnaire): ?>
                                <span class="comment-badge badge-comment">💬 RÉPONSE</span>
                                <?php endif; ?>
                            </div>
                            <div class="message-text">
                                <?php echo nl2br(htmlspecialchars($comment['message'])); ?>
                            </div>
                            <div class="message-time">
                                <?php echo date('d/m/Y à H:i', strtotime($comment['date_commentaire'])); ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            
            <!-- FORMULAIRE COMMENTAIRE -->
            <?php if ($peut_commenter): ?>
            <form class="comment-form" method="POST" id="commentForm">
                <input type="hidden" name="action" value="add_comment">
                
                <div style="display: flex; gap: 10px; margin-bottom: 10px; align-items: center;">
                    <button type="button" class="audio-btn" id="micBtn" onclick="toggleSpeechRecognition()">
                        <i class='bx bx-microphone' id="micIcon"></i>
                        <span id="micText">Dicter</span>
                    </button>
                    <span id="listeningIndicator" style="display: none; color: #e74c3c; font-weight: 600;">
                        <i class='bx bx-radio-circle-marked' style='animation: blink 1s infinite;'></i>
                        Écoute en cours...
                    </span>
                </div>
                
                <textarea name="message" id="messageTextarea" placeholder="Écrivez votre message ou cliquez sur 'Dicter'..." required></textarea>
                <button type="submit">
                    <i class='bx bx-send'></i>
                    Envoyer
                </button>
            </form>
            <?php else: ?>
            <div class="zone-desactivee">
                <i class='bx bx-lock-alt'></i>
                <p class="main"><?php echo htmlspecialchars($message_desactive); ?></p>
                <?php if (!$gestionnaire_a_ecrit && $statut_cle !== 'fermee'): ?>
                <p>Vous pourrez commenter dès que le gestionnaire répondra</p>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
        </div>
    </div>
    
    <style>
    @keyframes blink {
        0%, 50%, 100% { opacity: 1; }
        25%, 75% { opacity: 0.3; }
    }
    
    .audio-btn {
        background: #45AECC;
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 8px;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 8px;
        font-weight: 600;
        transition: all 0.3s;
        font-family: 'Afacad', sans-serif;
    }
    
    .audio-btn:hover {
        background: #3a9bb5;
        transform: translateY(-2px);
    }
    
    .audio-btn.recording {
        background: #e74c3c;
        animation: pulse 1.5s infinite;
    }
    
    @keyframes pulse {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.05); }
    }
    
    .audio-btn i {
        font-size: 18px;
    }
    </style>
    
    <script>
    // ✅ SPEECH-TO-TEXT (Web Speech API)
    let recognition;
    let isListening = false;
    
    // Vérifier si le navigateur supporte Web Speech API
    if ('webkitSpeechRecognition' in window || 'SpeechRecognition' in window) {
        const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
        recognition = new SpeechRecognition();
        
        // Configuration
        recognition.lang = 'fr-FR'; // Français
        recognition.continuous = true; // Continue à écouter
        recognition.interimResults = true; // Résultats temporaires
        
        // Quand la reconnaissance détecte du texte
        recognition.onresult = (event) => {
            let transcript = '';
            
            // Récupérer tout le texte transcrit
            for (let i = event.resultIndex; i < event.results.length; i++) {
                transcript += event.results[i][0].transcript;
            }
            
            // Mettre à jour le textarea
            const textarea = document.getElementById('messageTextarea');
            if (textarea) {
                // Ajouter au texte existant ou remplacer
                if (event.results[event.results.length - 1].isFinal) {
                    textarea.value = transcript + ' ';
                } else {
                    textarea.value = transcript;
                }
            }
        };
        
        // Quand la reconnaissance se termine
        recognition.onend = () => {
            if (isListening) {
                // Redémarrer automatiquement si toujours en mode écoute
                recognition.start();
            }
        };
        
        // En cas d'erreur
        recognition.onerror = (event) => {
            console.error('Erreur reconnaissance vocale:', event.error);
            
            if (event.error === 'no-speech') {
                alert('Aucune parole détectée. Veuillez réessayer.');
            } else if (event.error === 'not-allowed') {
                alert('Veuillez autoriser l\'accès au microphone dans les paramètres de votre navigateur.');
            } else {
                alert('Erreur : ' + event.error);
            }
            
            stopSpeechRecognition();
        };
    }
    
    function toggleSpeechRecognition() {
        if (!recognition) {
            alert('La reconnaissance vocale n\'est pas supportée par votre navigateur. Utilisez Chrome ou Edge.');
            return;
        }
        
        if (!isListening) {
            startSpeechRecognition();
        } else {
            stopSpeechRecognition();
        }
    }
    
    function startSpeechRecognition() {
        const micBtn = document.getElementById('micBtn');
        const micIcon = document.getElementById('micIcon');
        const micText = document.getElementById('micText');
        const indicator = document.getElementById('listeningIndicator');
        
        try {
            recognition.start();
            isListening = true;
            
            // Changer l'apparence du bouton
            micBtn.classList.add('recording');
            micIcon.className = 'bx bx-stop-circle';
            micText.textContent = 'Arrêter';
            indicator.style.display = 'inline';
            
            // Vider le textarea au début
            document.getElementById('messageTextarea').value = '';
            
        } catch (error) {
            console.error('Erreur démarrage reconnaissance:', error);
        }
    }
    
    function stopSpeechRecognition() {
        const micBtn = document.getElementById('micBtn');
        const micIcon = document.getElementById('micIcon');
        const micText = document.getElementById('micText');
        const indicator = document.getElementById('listeningIndicator');
        
        if (recognition) {
            recognition.stop();
        }
        
        isListening = false;
        
        // Restaurer l'apparence du bouton
        micBtn.classList.remove('recording');
        micIcon.className = 'bx bx-microphone';
        micText.textContent = 'Dicter';
        indicator.style.display = 'none';
    }
    
    // Arrêter l'enregistrement avant de soumettre le formulaire
    document.getElementById('commentForm')?.addEventListener('submit', (e) => {
        if (isListening) {
            stopSpeechRecognition();
        }
    });
    
    function showFiles(input) {
        const filesList = document.getElementById('filesList');
        filesList.innerHTML = '';
        
        for (let file of input.files) {
            const div = document.createElement('div');
            div.className = 'file-item';
            div.innerHTML = `
                <i class='bx bx-file'></i>
                <span>${file.name}</span>
                <span style="margin-left: auto; color: #999; font-size: 12px;">${(file.size / 1024).toFixed(1)} KB</span>
            `;
            filesList.appendChild(div);
        }
    }
    
    const chatContainer = document.getElementById('chatContainer');
    if (chatContainer) {
        chatContainer.scrollTop = chatContainer.scrollHeight;
    }
    </script>
</body>
</html>