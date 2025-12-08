
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
                <div class="stat-number">20</div>
            </div>

            <div class="stat-card femme">
                <div class="stat-header">
                    <span class="stat-label">Fermé</span>
                    <div class="stat-icon">
                        <i class='bx bx-lock-alt'></i>
                    </div>
                </div>
                <div class="stat-number">10</div>
            </div>

            <div class="stat-card traite">
                <div class="stat-header">
                    <span class="stat-label">En traitement</span>
                    <div class="stat-icon">
                        <i class='bx bx-time-five'></i>
                    </div>
                </div>
                <div class="stat-number">5</div>
            </div>

            <div class="stat-card accepte">
                <div class="stat-header">
                    <span class="stat-label">Accepté</span>
                    <div class="stat-icon">
                        <i class='bx bx-check-circle'></i>
                    </div>
                </div>
                <div class="stat-number">5</div>
            </div>
        </div>

        <!-- Welcome Banner -->
        <div class="welcome-banner">
            <div class="banner-content">
                <h2>Bonjour!</h2>
                <p>Avez-vous un problème? n'hésitez pas de le réclamer!</p>
                <a href="ajouter_reclamation.php" class="banner-btn">Ajouter une réclamation</a>
            </div>
        </div>

        <!-- Recent Claims Section -->
        <div class="recent-section">
            <div class="section-header">
                <h3 class="section-title">Réclamations Récentes</h3>
            </div>

            <!-- Filters -->
            <div class="filters-row">
                <div class="filter-group">
                    <label class="filter-label">chercher</label>
                    <input type="text" class="filter-input" placeholder="chercher...">
                </div>

                <div class="filter-group">
                    <label class="filter-label">statue</label>
                    <select class="filter-select">
                        <option value="">Tous</option>
                        <option value="technique">Technique</option>
                        <option value="facturation">Facturation</option>
                        <option value="service">Service</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label class="filter-label">trier par</label>
                    <select class="filter-select">
                        <option value="">Date</option>
                        <option value="nom">nom</option>
                        
                    </select>
                </div>

                <button class="filter-btn reset-btn" title="Réinitialiser">
                    <i class='bx bx-reset'></i>
                </button>

                <button class="filter-btn search-btn">
                    chercher
                </button>
            </div>

            <!-- Table -->
            <div class="table-wrapper">
                <table class="claims-table">
                    <thead>
                        <tr>
                            <th>Nom</th>
                            <th>Catégorie</th>
                            <th>Statut</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Problème de facturation</td>
                            <td>Facturation</td>
                            <td><span class="status-badge status-processing">En cours</span></td>
                            <td>01/12/2025</td>
                        </tr>
                        <tr>
                            <td>Bug sur l'application</td>
                            <td>Technique</td>
                            <td><span class="status-badge status-pending">En attente</span></td>
                            <td>30/11/2025</td>
                        </tr>
                        <tr>
                            <td>Service client non réactif</td>
                            <td>Service</td>
                            <td><span class="status-badge status-accepted">Accepté</span></td>
                            <td>28/11/2025</td>
                        </tr>
                        <tr>
                            <td>Livraison retardée</td>
                            <td>Livraison</td>
                            <td><span class="status-badge status-rejected">Rejeté</span></td>
                            <td>25/11/2025</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

</body>
</html>