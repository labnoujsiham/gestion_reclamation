/**
 * Système de Notifications - JavaScript
 * Gère le badge, modal, toast et polling
 * ADAPTÉ pour le projet de ton ami
 */

let notificationsData = [];
let pollInterval = null;

// Initialisation au chargement de la page
document.addEventListener('DOMContentLoaded', () => {
    loadNotifications();
    startPolling();
    setupEventListeners();
});

// Charger les notifications
async function loadNotifications() {
    try {
        const response = await fetch('get_notifications.php');
        const data = await response.json();
        
        if (data.success) {
            notificationsData = data.notifications;
            updateBadge(data.count_non_lues);
            renderNotifications(data.notifications);
            
            // Afficher un toast pour les nouvelles notifications
            checkForNewNotifications(data.notifications);
        }
    } catch (error) {
        console.error('Erreur chargement notifications:', error);
    }
}

// Mettre à jour le badge
function updateBadge(count) {
    const badge = document.getElementById('notificationBadge');
    if (badge) {
        if (count > 0) {
            badge.textContent = count > 99 ? '99+' : count;
            badge.classList.add('show');
        } else {
            badge.classList.remove('show');
        }
    }
}

// Rendre les notifications dans la modal
function renderNotifications(notifications) {
    const list = document.getElementById('notificationsList');
    if (!list) return;
    
    if (notifications.length === 0) {
        list.innerHTML = `
            <div class="empty-notifications">
                <i class='bx bx-bell-off'></i>
                <p>Aucune notification</p>
            </div>
        `;
        return;
    }
    
    list.innerHTML = notifications.map(notif => {
        // ADAPTÉ : La table de ton ami utilise reference_id au lieu de reclamation_id
        const reclamationId = notif.reference_id || 0;
        // ADAPTÉ : La table de ton ami utilise contenu au lieu de message
        const message = notif.contenu || notif.message || 'Nouvelle notification';
        const titre = notif.reclamation_objet || 'Réclamation';
        
        return `
            <div class="notification-item ${notif.lu == 0 ? 'unread' : ''}" 
                 onclick="handleNotificationClick(${notif.id}, ${reclamationId})">
                <div class="notification-content">
                    <div class="notification-title">
                        ${escapeHtml(titre)}
                    </div>
                    <div class="notification-message">
                        ${escapeHtml(message)}
                    </div>
                    <div class="notification-time">
                        ${formatDate(notif.date_creation)}
                    </div>
                </div>
            </div>
        `;
    }).join('');
}

// Gérer le clic sur une notification
async function handleNotificationClick(notifId, reclamationId) {
    // Marquer comme lue
    await markAsRead(notifId);
    
    // Fermer la modal
    closeModal();
    
    // Rediriger vers la réclamation
    window.location.href = `detail_reclamation.php?id=${reclamationId}#commentaires`;
}

// Marquer comme lue
async function markAsRead(notifId) {
    try {
        await fetch('mark_as_read.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ notification_id: notifId })
        });
        
        // Recharger les notifications
        loadNotifications();
    } catch (error) {
        console.error('Erreur marquage comme lu:', error);
    }
}

// Marquer toutes comme lues
async function markAllAsRead() {
    try {
        await fetch('mark_as_read.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ mark_all: true })
        });
        
        // Recharger les notifications
        loadNotifications();
    } catch (error) {
        console.error('Erreur marquage toutes comme lues:', error);
    }
}

// Vérifier les nouvelles notifications (pour toast)
let lastNotificationId = 0;

function checkForNewNotifications(notifications) {
    if (notifications.length === 0) return;
    
    const latestNotif = notifications[0];
    
    // Si c'est une nouvelle notification non lue
    if (latestNotif.id > lastNotificationId && latestNotif.lu == 0) {
        showToast(latestNotif);
        lastNotificationId = latestNotif.id;
    } else if (lastNotificationId === 0 && notifications.length > 0) {
        // Initialiser avec la dernière notification
        lastNotificationId = latestNotif.id;
    }
}

// Afficher le toast
function showToast(notif) {
    const toast = document.getElementById('toastNotification');
    const title = document.getElementById('toastTitle');
    const message = document.getElementById('toastMessage');
    const link = document.getElementById('toastLink');
    
    if (!toast || !title || !message || !link) return;
    
    // ADAPTÉ : Utiliser reference_id et contenu
    const reclamationId = notif.reference_id || 0;
    const messageText = notif.contenu || notif.message || 'Nouvelle notification';
    const titre = notif.reclamation_objet || 'Réclamation';
    
    title.textContent = titre;
    message.textContent = messageText;
    link.href = `detail_reclamation.php?id=${reclamationId}#commentaires`;
    
    toast.classList.add('show');
    
    // Auto-fermer après 7 secondes
    setTimeout(() => {
        closeToast();
    }, 7000);
}

// Fermer le toast
function closeToast() {
    const toast = document.getElementById('toastNotification');
    if (toast) {
        toast.classList.remove('show');
    }
}

// Ouvrir/fermer la modal
function toggleModal() {
    const modal = document.getElementById('notificationsModal');
    if (modal) {
        modal.classList.toggle('show');
    }
}

function closeModal() {
    const modal = document.getElementById('notificationsModal');
    if (modal) {
        modal.classList.remove('show');
    }
}

// Polling toutes les 30 secondes
function startPolling() {
    pollInterval = setInterval(() => {
        loadNotifications();
    }, 30000); // 30 secondes
}

// Event listeners
function setupEventListeners() {
    // Clic sur la cloche
    const bell = document.getElementById('notificationBell');
    if (bell) {
        bell.addEventListener('click', toggleModal);
    }
    
    // Marquer toutes comme lues
    const markAll = document.getElementById('markAllRead');
    if (markAll) {
        markAll.addEventListener('click', () => {
            markAllAsRead();
        });
    }
    
    // Fermer la modal si clic en dehors
    document.addEventListener('click', (e) => {
        const modal = document.getElementById('notificationsModal');
        const bell = document.getElementById('notificationBell');
        
        if (modal && bell && modal.classList.contains('show') && 
            !modal.contains(e.target) && 
            !bell.contains(e.target)) {
            closeModal();
        }
    });
}

// Utilitaires
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatDate(dateString) {
    const date = new Date(dateString);
    const now = new Date();
    const diff = Math.floor((now - date) / 1000); // en secondes
    
    if (diff < 60) return 'À l\'instant';
    if (diff < 3600) return `Il y a ${Math.floor(diff / 60)} min`;
    if (diff < 86400) return `Il y a ${Math.floor(diff / 3600)} h`;
    if (diff < 604800) return `Il y a ${Math.floor(diff / 86400)} j`;
    
    return date.toLocaleDateString('fr-FR', { 
        day: '2-digit', 
        month: '2-digit', 
        year: 'numeric' 
    });
}

// Nettoyer le polling quand on quitte la page
window.addEventListener('beforeunload', () => {
    if (pollInterval) {
        clearInterval(pollInterval);
    }
});