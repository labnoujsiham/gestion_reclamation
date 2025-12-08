<!-- topbar.php -->
<head>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link href="https://fonts.googleapis.com/css2?family=Afacad:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>

<div class="topbar">
    <div class="topbar-left">
        <h2 class="page-title-topbar"><?php echo isset($page_title) ? $page_title : 'Dashboard'; ?></h2>
    </div>
    <div class="topbar-right">
        <div class="notification-icon">
            <i class='bx bx-bell'></i>
            <span class="notification-badge"></span>
        </div>
       <a href="profil.php" class="user-avatar">
    <i class='bx bx-user'></i>
</a>

    </div>
</div>