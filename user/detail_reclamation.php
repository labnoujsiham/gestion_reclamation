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

// Récupérer les infos utilisateur depuis la session
$userId = $_SESSION['user_id'];
$userName = $_SESSION['user_name'] ?? '';
$userEmail = $_SESSION['user_email'] ?? '';
$userRole = $_SESSION['user_role'] ?? 'reclamant';

// Connexion à la base de données
require_once '../connexion/db_config.php';

// Récupérer l'ID de la réclamation
$reclamationId = $_GET['id'] ?? 0;

// Traitement de l'envoi d'un commentaire
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    $message = trim($_POST['message']);
    
    if (!empty($message)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO commentaires (reclamation_id, auteur_id, message, date_commentaire) 
                VALUES (?, ?, ?, NOW())
            ");
            $stmt->execute([$reclamationId, $userId, $message]);
            
            header("Location: detail_reclamation.php?id=" . $reclamationId . "#commentaires");
            exit;
        } catch (PDOException $e) {
            $error_message = "Erreur lors de l'envoi du commentaire";
        }
    }
}

// Récupérer les détails de la réclamation
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
    
    // Récupérer les pièces jointes
    $stmt = $pdo->prepare("SELECT * FROM pieces_jointes WHERE reclamation_id = ?");
    $stmt->execute([$reclamationId]);
    $pieces_jointes = $stmt->fetchAll();
    
    // Récupérer l'historique des statuts
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
    
    // Récupérer les commentaires
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
    
    // Vérifier si un gestionnaire a écrit
    $gestionnaire_a_ecrit = false;
    foreach ($commentaires as $comment) {
        if ($comment['auteur_role'] === 'gestionnaire' || $comment['auteur_role'] === 'administrateur') {
            $gestionnaire_a_ecrit = true;
            break;
        }
    }
    
} catch (PDOException $e) {
    header('Location: mes_reclamations.php');
    exit;
}

// Calculer la progression
$progression_steps = [
    'en_attente' => 1,
    'en_cours' => 2,
    'acceptee' => 3,
    'rejetee' => 3,
    'fermee' => 4
];
$current_step = $progression_steps[$reclamation['statut_cle']] ?? 1;

// Créer un tableau $user pour compatibilité avec topbar2.php
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

    
    
</head>
<body>
    <div class="detail-container">
        <a href="mes_reclamations.php" class="back-btn">
            <i class='bx bx-arrow-back'></i>
            Retour à mes réclamations
        </a>
        <!-- DÉTAILS RÉCLAMATION -->
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
            <div class="detail-section">
                <div class="detail-label">Pièces jointes (<?php echo count($pieces_jointes); ?>)</div>
                <?php if (count($pieces_jointes) > 0): ?>
                <ul class="attachments-list">
                    <?php foreach ($pieces_jointes as $fichier): ?>
                    <li class="attachment-item">
                        <i class='bx bx-file'></i>
                        <div class="attachment-info">
                            <div class="attachment-name"><?php echo htmlspecialchars($fichier['nom_original']); ?></div>
                            <div class="attachment-size"><?php echo round($fichier['taille'] / 1024, 2); ?> KB</div>
                        </div>
                        <a href="<?php echo htmlspecialchars($fichier['chemin_fichier']); ?>" download class="btn" style="background: #45AECC; color: white; padding: 8px 16px;">
                            <i class='bx bx-download'></i>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php else: ?>
                <div class="empty-attachments">
                    <i class='bx bx-folder-open' style="font-size: 48px; color: #ddd;"></i>
                    <p>Aucune pièce jointe</p>
                </div>
                <?php endif; ?>
            </div>
            <div class="action-buttons">
                <a href="modifier_reclamation.php?id=<?php echo $reclamation['id']; ?>" class="btn btn-edit">
                    <i class='bx bx-edit'></i>
                    Modifier
                </a>
                <a href="supprimer_reclamation.php?id=<?php echo $reclamation['id']; ?>" class="btn btn-delete" onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette réclamation ?');">
                    <i class='bx bx-trash'></i>
                    Supprimer
                </a>
            </div>
        </div>
        <!-- SECTION INFO : TIMELINE + GESTIONNAIRE -->
        <div class="info-section">
            <!-- TIMELINE -->
            <div class="timeline-card">
                <div class="timeline-header">
                    <i class='bx bx-time-five'></i>
                    <h3>Historique</h3>
                </div>
                <div class="timeline">
                    <!-- Soumission -->
                    <div class="timeline-item">
                        <div class="timeline-date"><?php echo date('d/m/Y à H:i', strtotime($reclamation['date_soumission'])); ?></div>
                        <div class="timeline-title">Réclamation soumise</div>
                        <div class="timeline-desc">Par <?php echo htmlspecialchars($reclamation['reclamant_nom']); ?></div>
                    </div>
                    
                    <!-- Historique des changements -->
                    <?php foreach ($historique as $h): ?>
                    <div class="timeline-item">
                        <div class="timeline-date"><?php echo date('d/m/Y à H:i', strtotime($h['date_modification'])); ?></div>
                        <div class="timeline-title">Statut changé</div>
                        <div class="timeline-desc">
                            <?php echo htmlspecialchars($h['ancien_statut_libelle'] ?? 'N/A'); ?> 
                            → <?php echo htmlspecialchars($h['nouveau_statut_libelle']); ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    
                    <!-- Statut actuel -->
                    <div class="timeline-item current">
                        <div class="timeline-date">En cours</div>
                        <div class="timeline-title"><?php echo htmlspecialchars($reclamation['statut_libelle']); ?></div>
                        <div class="timeline-desc">Statut actuel</div>
                    </div>
                </div>
            </div>
            <!-- GESTIONNAIRE -->
            <?php if ($reclamation['gestionnaire_id']): ?>
            <div class="gestionnaire-card">
                <div class="gestionnaire-header">
                    <i class='bx bx-shield-alt-2'></i>
                    <h3>Gestionnaire assigné</h3>
                </div>
                <div class="gestionnaire-info">
                    <div class="gestionnaire-avatar">
                        <?php echo strtoupper(substr($reclamation['gestionnaire_nom'], 0, 1)); ?>
                    </div>
                    <div class="gestionnaire-details">
                        <h4><?php echo htmlspecialchars($reclamation['gestionnaire_nom']); ?></h4>
                        <p><i class='bx bx-envelope'></i> <?php echo htmlspecialchars($reclamation['gestionnaire_email']); ?></p>
                        <p><i class='bx bx-badge-check'></i> <?php echo ucfirst($reclamation['gestionnaire_role']); ?></p>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <div class="no-gestionnaire">
                <i class='bx bx-user-x'></i>
                <p>Aucun gestionnaire assigné</p>
                <p style="font-size: 12px; margin-top: 5px;">En attente d'attribution</p>
            </div>
            <?php endif; ?>
        </div>
        <!-- BARRE DE PROGRESSION -->
        <div class="progress-card">
            <div class="progress-header">
                <i class='bx bx-chart'></i>
                <h3>Progression</h3>
            </div>
            <div class="progress-steps">
                <div class="progress-line">
                    <div class="progress-line-fill" style="width: <?php echo (($current_step - 1) / 3) * 100; ?>%;"></div>
                </div>
                
                <div class="progress-step <?php echo $current_step >= 1 ? 'completed' : ''; ?> <?php echo $current_step == 1 ? 'active' : ''; ?>">
                    <div class="progress-circle">1</div>
                    <div class="progress-label">Soumise</div>
                </div>
                
                <div class="progress-step <?php echo $current_step >= 2 ? 'completed' : ''; ?> <?php echo $current_step == 2 ? 'active' : ''; ?>">
                    <div class="progress-circle">2</div>
                    <div class="progress-label">En cours</div>
                </div>
                
                <div class="progress-step <?php echo $current_step >= 3 ? 'completed' : ''; ?> <?php echo $current_step == 3 ? 'active' : ''; ?>">
                    <div class="progress-circle">3</div>
                    <div class="progress-label">Traitée</div>
                </div>
                
                <div class="progress-step <?php echo $current_step >= 4 ? 'completed' : ''; ?> <?php echo $current_step == 4 ? 'active' : ''; ?>">
                    <div class="progress-circle">4</div>
                    <div class="progress-label">Fermée</div>
                </div>
            </div>
        </div>
        <!-- COMMENTAIRES -->
        <div class="comments-section" id="commentaires">
            <div class="comments-header">
                <i class='bx bx-message-square-dots'></i>
                <h3>Commentaires</h3>
                <?php if (count($commentaires) > 0): ?>
                <span class="comments-count"><?php echo count($commentaires); ?></span>
                <?php endif; ?>
            </div>
            <?php if ($gestionnaire_a_ecrit): ?>
                <div class="chat-container" id="chatContainer">
                    <?php foreach ($commentaires as $comment): ?>
                        <?php 
                        $isGestionnaire = ($comment['auteur_role'] === 'gestionnaire' || $comment['auteur_role'] === 'administrateur');
                        $messageClass = $isGestionnaire ? 'gestionnaire' : 'reclamant';
                        ?>
                        <div class="chat-message <?php echo $messageClass; ?>">
                            <div class="message-bubble">
                                <div class="message-author">
                                    <i class='bx <?php echo $isGestionnaire ? 'bx-shield-alt-2' : 'bx-user'; ?>'></i>
                                    <?php echo $isGestionnaire ? 'Gestionnaire' : 'Vous'; ?>
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
                <form class="comment-form" method="POST">
                    <textarea name="message" placeholder="Écrivez votre réponse..." required></textarea>
                    <button type="submit">
                        <i class='bx bx-send'></i>
                        Envoyer
                    </button>
                </form>
            <?php else: ?>
                <div class="comments-disabled">
                    <i class='bx bx-message-square-x'></i>
                    <p><strong>En attente de la réponse du gestionnaire</strong></p>
                    <p style="margin-top: 10px; font-size: 13px;">
                        Vous pourrez répondre une fois que le gestionnaire aura commenté votre réclamation.
                    </p>
                </div>
                <form class="comment-form">
                    <textarea placeholder="En attente de la réponse du gestionnaire..." disabled></textarea>
                    <button type="button" disabled>
                        <i class='bx bx-send'></i>
                        Envoyer
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>
    <script>
        const chatContainer = document.getElementById('chatContainer');
        if (chatContainer) {
            chatContainer.scrollTop = chatContainer.scrollHeight;
        }
        if (window.location.hash === '#commentaires') {
            document.getElementById('commentaires').scrollIntoView({ behavior: 'smooth' });
        }
    </script>
</body>
</html>