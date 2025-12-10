<?php
/**
 * Dashboard - ReclaNova
 * Displays statistics and recent claims
 */

// Include database configuration
require_once 'db_config.php';

// Get database connection
$pdo = getDBConnection();

// Initialize ALL variables at the top (prevents undefined variable errors)
$totalReclamations = 0;
$enCoursCount = 0;
$accepteCount = 0;
$fermeCount = 0;
$recentReclamations = [];
$statuts = [];

// Initialize filter variables with defaults
$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';
$filterStatut = isset($_GET['statut']) ? $_GET['statut'] : '';
$sortBy = isset($_GET['sort']) ? $_GET['sort'] : 'date';
$sortOrder = isset($_GET['order']) ? $_GET['order'] : 'DESC';

// Validate sort order
$sortOrder = strtoupper($sortOrder) === 'ASC' ? 'ASC' : 'DESC';

// Fetch statistics and data if connection is successful
if ($pdo) {
    try {
        // Get total number of reclamations
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM reclamations");
        $totalReclamations = $stmt->fetch()['total'];

        // Get count by status (using the 'cle' field from statuts table)
        // Status: en_cours
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM reclamations r 
            JOIN statuts s ON r.statut_id = s.id 
            WHERE s.cle = 'en_cours'
        ");
        $stmt->execute();
        $enCoursCount = $stmt->fetch()['count'];

        // Status: acceptee
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM reclamations r 
            JOIN statuts s ON r.statut_id = s.id 
            WHERE s.cle = 'acceptee'
        ");
        $stmt->execute();
        $accepteCount = $stmt->fetch()['count'];

        // Status: fermee
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM reclamations r 
            JOIN statuts s ON r.statut_id = s.id 
            WHERE s.cle = 'fermee'
        ");
        $stmt->execute();
        $fermeCount = $stmt->fetch()['count'];

        // Get all statuts for filter dropdown
        $stmt = $pdo->query("SELECT id, cle, libelle FROM statuts WHERE 1 ORDER BY id");
        $statuts = $stmt->fetchAll();

        // Build query for recent reclamations
        $sql = "
            SELECT 
                r.id,
                r.objet,
                r.date_soumission,
                c.nom AS categorie_nom,
                s.libelle AS statut_libelle,
                s.cle AS statut_cle,
                u.nom AS user_nom
            FROM reclamations r
            LEFT JOIN categories c ON r.categorie_id = c.id
            LEFT JOIN statuts s ON r.statut_id = s.id
            LEFT JOIN users u ON r.user_id = u.id
            WHERE 1=1
        ";
        
        $params = [];

        // Add search filter
        if (!empty($searchTerm)) {
            $sql .= " AND (r.objet LIKE :search OR u.nom LIKE :search2)";
            $params[':search'] = '%' . $searchTerm . '%';
            $params[':search2'] = '%' . $searchTerm . '%';
        }

        // Add status filter
        if (!empty($filterStatut)) {
            $sql .= " AND s.id = :statut_id";
            $params[':statut_id'] = $filterStatut;
        }

        // Add sorting
        if ($sortBy === 'nom') {
            $sql .= " ORDER BY r.objet " . $sortOrder;
        } else {
            $sql .= " ORDER BY r.date_soumission " . $sortOrder;
        }

        // Limit to 4 most recent (or filtered results)
        $sql .= " LIMIT 4";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $recentReclamations = $stmt->fetchAll();

    } catch (PDOException $e) {
        error_log("Dashboard query error: " . $e->getMessage());
    }
}

// Helper function to get CSS class based on status
function getStatusClass($statutCle) {
    $classes = [
        'en_cours'    => 'status-processing',
        'en_attente'  => 'status-pending',
        'acceptee'    => 'status-accepted',
        'rejetee'     => 'status-rejected',
        'fermee'      => 'status-closed',
    ];
    return $classes[$statutCle] ?? 'status-default';
}

// Format date to French format
function formatDate($date) {
    return date('d/m/Y', strtotime($date));
}
?>
<?php include 'sidebar2.php'; ?>
<?php include 'topbar2.php'; ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - ReclaNova</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link href="https://fonts.googleapis.com/css2?family=Afacad:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="sidebar2.css">
    <link rel="stylesheet" href="dashboard.css">
    <link rel="stylesheet" href="topbar2.css">
</head>
<body>

    <!-- Top Bar -->
    <div class="topbar">
        <div class="topbar-left">
            <h2>Dashboard</h2>
        </div>
        <div class="topbar-right">
            <div class="notification-icon">
                <i class='bx bx-bell'></i>
                <span class="notification-badge"></span>
            </div>
            <div class="user-avatar">
                <i class='bx bx-user'></i>
            </div>
        </div>
    </div>

    <!-- Main Dashboard Content -->
    <div class="dashboard-container">
        
        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card total">
                <div class="stat-header">
                    <span class="stat-label">Nombre totale</span>
                    <div class="stat-icon">
                        <i class='bx bx-file'></i>
                    </div>
                </div>
                <div class="stat-number"><?php echo htmlspecialchars($totalReclamations); ?></div>
            </div>

            <div class="stat-card femme">
                <div class="stat-header">
                    <span class="stat-label">Fermé</span>
                    <div class="stat-icon">
                        <i class='bx bx-lock-alt'></i>
                    </div>
                </div>
                <div class="stat-number"><?php echo htmlspecialchars($fermeCount); ?></div>
            </div>

            <div class="stat-card traite">
                <div class="stat-header">
                    <span class="stat-label">En traitement</span>
                    <div class="stat-icon">
                        <i class='bx bx-time-five'></i>
                    </div>
                </div>
                <div class="stat-number"><?php echo htmlspecialchars($enCoursCount); ?></div>
            </div>

            <div class="stat-card accepte">
                <div class="stat-header">
                    <span class="stat-label">Accepté</span>
                    <div class="stat-icon">
                        <i class='bx bx-check-circle'></i>
                    </div>
                </div>
                <div class="stat-number"><?php echo htmlspecialchars($accepteCount); ?></div>
            </div>
        </div>

        <!-- Welcome Banner -->
        <div class="welcome-banner">
            <div class="banner-content">
                <h2>Bonjour!</h2>
                <p>Voulez-vous consulter vos réclamations? Cliquez ici!</p>
                <a href="reclamation.php" class="banner-btn">Gérer les réclamations</a>
            </div>
        </div>

        <!-- Recent Claims Section -->
        <div class="recent-section">
            <div class="section-header">
                <h3 class="section-title">Réclamations Récentes</h3>
            </div>

            <!-- Filters Form -->
            <form method="GET" action="dashboard.php" id="filterForm">
                <div class="filters-row">
                    <!-- Search Input -->
                    <div class="filter-group">
                        <label class="filter-label">Chercher</label>
                        <input 
                            type="text" 
                            name="search" 
                            class="filter-input" 
                            placeholder="Chercher..."
                            value="<?php echo htmlspecialchars($searchTerm); ?>"
                        >
                    </div>

                    <!-- Status Filter -->
                    <div class="filter-group">
                        <label class="filter-label">Statut</label>
                        <select name="statut" class="filter-select">
                            <option value="">Tous</option>
                            <?php foreach ($statuts as $statut): ?>
                                <option 
                                    value="<?php echo htmlspecialchars($statut['id']); ?>"
                                    <?php echo ($filterStatut == $statut['id']) ? 'selected' : ''; ?>
                                >
                                    <?php echo htmlspecialchars($statut['libelle']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Sort By -->
                    <div class="filter-group">
                        <label class="filter-label">Trier par</label>
                        <select name="sort" class="filter-select">
                            <option value="date" <?php echo ($sortBy === 'date') ? 'selected' : ''; ?>>Date</option>
                            <option value="nom" <?php echo ($sortBy === 'nom') ? 'selected' : ''; ?>>Nom</option>
                        </select>
                    </div>

                    <!-- Hidden field for sort order -->
                    <input type="hidden" name="order" id="sortOrder" value="<?php echo htmlspecialchars($sortOrder); ?>">

                    <!-- Sort Order Toggle Button (ASC/DESC) -->
                    <button 
                        type="button" 
                        class="filter-btn order-btn" 
                        id="orderToggle"
                        title="<?php echo ($sortOrder === 'ASC') ? 'Croissant' : 'Décroissant'; ?>"
                    >
                        <i class='bx <?php echo ($sortOrder === 'ASC') ? 'bx-sort-up' : 'bx-sort-down'; ?>'></i>
                    </button>

                    <!-- Reset Button -->
                    <button type="button" class="filter-btn reset-btn" id="resetBtn" title="Réinitialiser">
                        <i class='bx bx-reset'></i>
                    </button>

                    <!-- Search Button -->
                    <button type="submit" class="filter-btn search-btn">
                        Chercher
                    </button>
                </div>
            </form>

            <!-- Table -->
            <div class="table-wrapper">
                <table class="claims-table">
                    <thead>
                        <tr>
                            <th>Nom</th>
                            <th>Catégorie</th>
                            <th>Statut</th>
                            <th>Date</th>
                            <th>Réclamant</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentReclamations)): ?>
                            <tr>
                                <td colspan="5" style="text-align: center; padding: 20px;">
                                    Aucune réclamation trouvée
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recentReclamations as $reclamation): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($reclamation['objet']); ?></td>
                                    <td><?php echo htmlspecialchars($reclamation['categorie_nom'] ?? 'N/A'); ?></td>
                                    <td>
                                        <span class="status-badge <?php echo getStatusClass($reclamation['statut_cle']); ?>">
                                            <?php echo htmlspecialchars($reclamation['statut_libelle'] ?? 'N/A'); ?>
                                        </span>
                                    </td>
                                    <td><?php echo formatDate($reclamation['date_soumission']); ?></td>
                                    <td><?php echo htmlspecialchars($reclamation['user_nom'] ?? 'N/A'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <!-- JavaScript for Filter Functionality -->
    <script>
        // Toggle sort order (ASC/DESC)
        document.getElementById('orderToggle').addEventListener('click', function() {
            const orderInput = document.getElementById('sortOrder');
            const icon = this.querySelector('i');
            
            if (orderInput.value === 'DESC') {
                orderInput.value = 'ASC';
                icon.className = 'bx bx-sort-up';
                this.title = 'Croissant';
            } else {
                orderInput.value = 'DESC';
                icon.className = 'bx bx-sort-down';
                this.title = 'Décroissant';
            }
        });

        // Reset all filters
        document.getElementById('resetBtn').addEventListener('click', function() {
            window.location.href = 'dashboard.php';
        });
    </script>

</body>
</html>