<?php
require_once '../config/config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' . SITE_NAME : SITE_NAME; ?></title>
    <meta name="description" content="<?php echo isset($page_description) ? $page_description : 'Centralized platform for college event management, registration, and participation'; ?>">
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- CSS -->
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/animations.css">

    <!-- Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/lucide/0.263.1/lucide.min.css">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    
    <!-- Additional CSS -->
    <?php if (isset($additional_css)): ?>
        <?php foreach ($additional_css as $css): ?>
            <link rel="stylesheet" href="../assets/css/<?php echo $css; ?>">
        <?php endforeach; ?>
    <?php endif; ?>
</head>
<body>
    <header class="header">
        <div class="container">
            <nav class="navbar">
                <div class="logo">
                    <img src="../images/logo.png" alt="<?php echo SITE_NAME; ?>" class="logo-img">
                </div>
                
                <ul class="nav-menu">
                    <li><a href="../index.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">Home</a></li>
                    <li><a href="events.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'events-working.php' ? 'active' : ''; ?>">Events</a></li>
                    <li><a href="gallery.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'gallery-working.php' ? 'active' : ''; ?>">Gallery</a></li>
                    <li><a href="about.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'about-working.php' ? 'active' : ''; ?>">About</a></li>
                    <li><a href="contact.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'contact-working.php' ? 'active' : ''; ?>">Contact</a></li>
                    
                    <?php if (isLoggedIn()): ?>
                        <li><a href="dashboard.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">Dashboard</a></li>
                        <li><a href="logout.php" class="btn btn-primary btn-sm">Logout</a></li>
                    <?php else: ?>
                        <li><a href="login.php" class="btn btn-primary btn-sm">Login</a></li>
                    <?php endif; ?>
                </ul>
                
                <!-- Mobile menu toggle -->
                <div class="mobile-menu-toggle d-none">
                    <i data-lucide="menu"></i>
                </div>
            </nav>
        </div>
    </header>

    <main class="main-content">
