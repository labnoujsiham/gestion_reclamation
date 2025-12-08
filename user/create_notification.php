<?php
/**
 * Helper Function - Créer une notification
 * À inclure dans les fichiers qui doivent créer des notifications
 */

/**
 * Créer une notification pour un utilisateur
 * 
 * @param PDO $db Connexion base de données
 * @param int $userId ID de l'utilisateur qui recevra la notification
 * @param int $reclamationId ID de la réclamation concernée
 * @param string $message Message de la notification
 * @param string $type Type de notification ('nouveau_commentaire', 'changement_statut')
 * @param int|null $gestionnaireId ID du gestionnaire (optionnel)
 * @return bool True si succès, False sinon
 */
function createNotification($db, $userId, $reclamationId, $message, $type = 'nouveau_commentaire', $gestionnaireId = null) {
    try {
        $stmt = $db->prepare("
            INSERT INTO notifications (user_id, reclamation_id, gestionnaire_id, message, type, lu, date_creation) 
            VALUES (?, ?, ?, ?, ?, 0, NOW())
        ");
        
        return $stmt->execute([$userId, $reclamationId, $gestionnaireId, $message, $type]);
    } catch (PDOException $e) {
        error_log("Erreur création notification: " . $e->getMessage());
        return false;
    }
}

/**
 * Créer une notification pour un nouveau commentaire gestionnaire
 * 
 * @param PDO $db Connexion base de données
 * @param int $reclamationId ID de la réclamation
 * @param int $gestionnaireId ID du gestionnaire qui a commenté
 * @return bool True si succès, False sinon
 */
function createCommentNotification($db, $reclamationId, $gestionnaireId) {
    try {
        // Récupérer l'user_id de la réclamation
        $stmt = $db->prepare("SELECT user_id, objet FROM reclamations WHERE id = ?");
        $stmt->execute([$reclamationId]);
        $reclamation = $stmt->fetch();
        
        if (!$reclamation) {
            return false;
        }
        
        $message = "Le gestionnaire a répondu à votre réclamation";
        
        return createNotification(
            $db, 
            $reclamation['user_id'], 
            $reclamationId, 
            $message, 
            'nouveau_commentaire',
            $gestionnaireId
        );
    } catch (PDOException $e) {
        error_log("Erreur création notification commentaire: " . $e->getMessage());
        return false;
    }
}

/**
 * Créer une notification pour un changement de statut
 * 
 * @param PDO $db Connexion base de données
 * @param int $reclamationId ID de la réclamation
 * @param string $nouveauStatut Libellé du nouveau statut
 * @param int|null $gestionnaireId ID du gestionnaire qui a changé le statut
 * @return bool True si succès, False sinon
 */
function createStatusChangeNotification($db, $reclamationId, $nouveauStatut, $gestionnaireId = null) {
    try {
        // Récupérer l'user_id de la réclamation
        $stmt = $db->prepare("SELECT user_id FROM reclamations WHERE id = ?");
        $stmt->execute([$reclamationId]);
        $reclamation = $stmt->fetch();
        
        if (!$reclamation) {
            return false;
        }
        
        $message = "Le statut de votre réclamation a été changé en : " . $nouveauStatut;
        
        return createNotification(
            $db, 
            $reclamation['user_id'], 
            $reclamationId, 
            $message, 
            'changement_statut',
            $gestionnaireId
        );
    } catch (PDOException $e) {
        error_log("Erreur création notification statut: " . $e->getMessage());
        return false;
    }
}