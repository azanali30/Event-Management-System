<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once './_auth.php';
require_once './_error_handler.php';
admin_require_login();

$db = new Database();
$pdo = $db->getConnection();

// Get date range from query parameters
$start_date = $_GET['start_date'] ?? date('Y-m-01'); // First day of current month
$end_date = $_GET['end_date'] ?? date('Y-m-t'); // Last day of current month

// Get comprehensive statistics
$stats = [];

// User statistics
$stats['total_users'] = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
$stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE created_at >= ? AND created_at <= ?');
$stmt->execute([$start_date, $end_date . ' 23:59:59']);
$stats['new_users'] = (int)$stmt->fetchColumn();

// Event statistics
$stats['total_events'] = (int)$pdo->query('SELECT COUNT(*) FROM events')->fetchColumn();
$stats['upcoming_events'] = (int)$pdo->query('SELECT COUNT(*) FROM events WHERE event_date >= CURDATE()')->fetchColumn();
$stats['past_events'] = (int)$pdo->query('SELECT COUNT(*) FROM events WHERE event_date < CURDATE()')->fetchColumn();

// Registration statistics
$stats['total_registrations'] = (int)$pdo->query('SELECT COUNT(*) FROM registration')->fetchColumn();
$stats['confirmed_registrations'] = (int)$pdo->query('SELECT COUNT(*) FROM registration WHERE approved_at IS NOT NULL')->fetchColumn();
$stats['pending_registrations'] = (int)$pdo->query('SELECT COUNT(*) FROM registration WHERE approved_at IS NULL')->fetchColumn();

// Media statistics
$stats['total_media'] = (int)$pdo->query('SELECT COUNT(*) FROM mediagallery')->fetchColumn();
$stats['approved_media'] = (int)$pdo->query("SELECT COUNT(*) FROM mediagallery WHERE status = 'approved'")->fetchColumn();
$stats['pending_media'] = (int)$pdo->query("SELECT COUNT(*) FROM mediagallery WHERE status = 'pending'")->fetchColumn();

// Get top events by registration count
$top_events = $pdo->query("
    SELECT 
        e.title,
        e.event_date,
        e.venue,
        COUNT(r.id) as registration_count
    FROM events e
    LEFT JOIN registration r ON e.event_id = r.event_id
    GROUP BY e.event_id
    ORDER BY registration_count DESC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

// Get recent activity
$recent_registrations = $pdo->query("
    SELECT 
        r.student_id,
        r.student_name as user_name,
        r.student_email as user_email,
        e.title as event_title,
        r.registered_on,
        CASE WHEN r.approved_at IS NOT NULL THEN 'approved' ELSE 'pending' END as status
    FROM registration r
    JOIN events e ON r.event_id = e.event_id
    ORDER BY r.registered_on DESC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

// Get user role distribution
$user_roles = $pdo->query("
    SELECT 
        role,
        COUNT(*) as count
    FROM users
    GROUP BY role
    ORDER BY count DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Get monthly registration trends (last 6 months)
$monthly_trends = $pdo->query("
    SELECT 
        DATE_FORMAT(registered_on, '%Y-%m') as month,
        COUNT(*) as registrations
    FROM registration
    WHERE registered_on >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(registered_on, '%Y-%m')
    ORDER BY month ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Get current admin user info
$current_user = DatabaseHelper::fetchOne($pdo, 'SELECT name, email FROM users WHERE user_id = ?', [$_SESSION['user_id']]);
$admin_name = $current_user['name'] ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports & Analytics - <?php echo SITE_NAME; ?></title>
    <link rel="icon" type="image/png" href="../assets/images/dot.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="./admin.css">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
</head>
<body>
    <div class="admin-layout">
        <aside class="sidebar">
            <div class="brand">
                <div class="brand-logo">
                    <i data-lucide="calendar"></i>
                </div>
                <div class="brand-title">EventSphere</div>
                <div class="brand-subtitle">ADMIN PANEL</div>
            </div>
            
            <nav class="nav">
                <div class="nav-section">
                    <div class="nav-section-title">Overview</div>
                    <a href="./dashboard.php">
                        <i data-lucide="layout-dashboard"></i>
                        <span>Dashboard</span>
                    </a>
                </div>

                <div class="nav-section">
                    <div class="nav-section-title">Management</div>
                    <a href="./users.php">
                        <i data-lucide="users"></i>
                        <span>Users</span>
                    </a>
                    <a href="./events.php">
                        <i data-lucide="calendar"></i>
                        <span>Events</span>
                    </a>
                    <a href="./registrations.php">
                        <i data-lucide="user-check"></i>
                        <span>Registrations</span>
                    </a>
                    <a href="./registration-approvals.php">
                        <i data-lucide="user-plus"></i>
                        <span>Registration Approvals</span>
                    </a>
                    <a href="./feedback.php">
                        <i data-lucide="message-square"></i>
                        <span>Feedback</span>
                    </a>
                    <a href="./gallery.php">
                        <i data-lucide="image"></i>
                        <span>Media Gallery</span>
                    </a>
                    <a href="./certificates.php">
                        <i data-lucide="award"></i>
                        <span>Certificates</span>
                    </a>
                    <a href="./announcements.php">
                        <i data-lucide="megaphone"></i>
                        <span>Announcements</span>
                    </a>
                </div>

                <div class="nav-section">
                    <div class="nav-section-title">Analytics</div>
                    <a href="./reports.php" class="active">
                        <i data-lucide="bar-chart-3"></i>
                        <span>Reports</span>
                    </a>
                </div>

                <div class="nav-section">
                    <div class="nav-section-title">System</div>
                    <a href="./settings.php">
                        <i data-lucide="settings"></i>
                        <span>Settings</span>
                    </a>
                    <a href="./logout.php" class="nav-logout">
                        <i data-lucide="log-out"></i>
                        <span>Logout</span>
                    </a>
                </div>
            </nav>
        </aside>
        <main>
            <div class="topbar">
                <div class="topbar-left">
                    <div class="page-title">
                        <h1>Reports & Analytics</h1>
                        <p class="page-subtitle">View detailed analytics and generate reports</p>
                    </div>
                </div>
                <div class="topbar-right">
                    <div class="user-welcome">
                        <div class="welcome-text">
                            <span class="greeting">Welcome back,</span>
                            <span class="user-name"><?php echo htmlspecialchars($admin_name); ?></span>
                        </div>
                        <div class="user-avatar">
                            <i data-lucide="user-circle"></i>
                        </div>
                    </div>
                    <div class="topbar-actions">
                        <a class="btn-topbar" href="<?php echo SITE_URL; ?>" target="_blank" title="View Website">
                            <i data-lucide="external-link"></i>
                            <span>View Site</span>
                        </a>
                        <button class="btn-topbar" onclick="toggleNotifications()" title="Notifications">
                            <i data-lucide="bell"></i>
                            <span class="notification-badge">3</span>
                        </button>
                    </div>
                </div>
            </div>
            <div class="content">
                <!-- Breadcrumbs -->
                <div class="breadcrumbs">
                    <a href="./dashboard.php">Dashboard</a>
                    <span class="breadcrumb-separator">/</span>
                    <span>Reports</span>
                </div>

                <!-- Date Range Filter -->
                <div class="search-container">
                    <form method="GET" class="search-grid">
                        <div style="display: flex; gap: 1rem; align-items: end;">
                            <div class="field">
                                <label for="start_date">Start Date</label>
                                <input id="start_date" class="input" type="date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
                            </div>
                            <div class="field">
                                <label for="end_date">End Date</label>
                                <input id="end_date" class="input" type="date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
                            </div>
                            <button type="submit" class="btn">
                                <i data-lucide="calendar"></i>
                                Update Report
                            </button>
                            <button type="button" class="btn success" onclick="exportReport()">
                                <i data-lucide="download"></i>
                                Export Report
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Overview Statistics -->
                <div class="cards">
                    <div class="card">
                        <h4>Total Users</h4>
                        <div class="value"><?php echo number_format($stats['total_users']); ?></div>
                        <div class="description">Registered users</div>
                    </div>
                    <div class="card">
                        <h4>Total Events</h4>
                        <div class="value"><?php echo number_format($stats['total_events']); ?></div>
                        <div class="description"><?php echo $stats['upcoming_events']; ?> upcoming</div>
                    </div>
                    <div class="card">
                        <h4>Total Registrations</h4>
                        <div class="value"><?php echo number_format($stats['total_registrations']); ?></div>
                        <div class="description"><?php echo $stats['confirmed_registrations']; ?> confirmed</div>
                    </div>
                    <div class="card">
                        <h4>Media Files</h4>
                        <div class="value"><?php echo number_format($stats['total_media']); ?></div>
                        <div class="description"><?php echo $stats['approved_media']; ?> approved</div>
                    </div>
                </div>

                <!-- Charts and Tables -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 2rem;">
                    <!-- Top Events -->
                    <div class="table-container">
                        <div style="padding: 1.5rem; border-bottom: 1px solid var(--border-color);">
                            <h3 style="margin: 0; font-size: 1.125rem; font-weight: 600; color: var(--text-primary);">Top Events</h3>
                            <p style="margin: 0.25rem 0 0; color: var(--text-secondary); font-size: 0.875rem;">Events by registration count</p>
                        </div>
                        <div style="overflow-x: auto;">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Event</th>
                                        <th>Date</th>
                                        <th>Registrations</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($top_events as $event): ?>
                                        <tr>
                                            <td>
                                                <div style="font-weight: 500;"><?php echo htmlspecialchars($event['title']); ?></div>
                                                <div style="font-size: 0.875rem; color: var(--text-secondary);"><?php echo htmlspecialchars($event['venue']); ?></div>
                                            </td>
                                            <td><?php echo date('M j, Y', strtotime($event['event_date'])); ?></td>
                                            <td><span class="badge info"><?php echo $event['registration_count']; ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- User Roles -->
                    <div class="table-container">
                        <div style="padding: 1.5rem; border-bottom: 1px solid var(--border-color);">
                            <h3 style="margin: 0; font-size: 1.125rem; font-weight: 600; color: var(--text-primary);">User Distribution</h3>
                            <p style="margin: 0.25rem 0 0; color: var(--text-secondary); font-size: 0.875rem;">Users by role</p>
                        </div>
                        <div style="padding: 1.5rem;">
                            <?php foreach ($user_roles as $role): ?>
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                                    <span style="font-weight: 500; text-transform: capitalize;"><?php echo htmlspecialchars($role['role']); ?></span>
                                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                                        <div style="width: 100px; height: 8px; background: var(--bg-tertiary); border-radius: 4px; overflow: hidden;">
                                            <div style="width: <?php echo ($role['count'] / $stats['total_users']) * 100; ?>%; height: 100%; background: var(--primary-color);"></div>
                                        </div>
                                        <span class="badge info"><?php echo $role['count']; ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="table-container">
                    <div style="padding: 1.5rem; border-bottom: 1px solid var(--border-color);">
                        <h3 style="margin: 0; font-size: 1.125rem; font-weight: 600; color: var(--text-primary);">Recent Registrations</h3>
                        <p style="margin: 0.25rem 0 0; color: var(--text-secondary); font-size: 0.875rem;">Latest event registrations</p>
                    </div>
                    <div style="overflow-x: auto;">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Event</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_registrations as $registration): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($registration['user_email']); ?></td>
                                        <td><?php echo htmlspecialchars($registration['event_title']); ?></td>
                                        <td><?php echo date('M j, Y g:i A', strtotime($registration['registered_on'])); ?></td>
                                        <td>
                                            <span class="badge <?php 
                                                echo $registration['status'] === 'confirmed' ? 'success' : 
                                                    ($registration['status'] === 'waitlist' ? 'warning' : 'danger'); 
                                            ?>">
                                                <?php echo ucfirst($registration['status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Initialize Lucide icons
        lucide.createIcons();
        
        // Export functionality
        function exportReport() {
            const startDate = document.getElementById('start_date').value;
            const endDate = document.getElementById('end_date').value;
            const params = new URLSearchParams({
                export: 'csv',
                start_date: startDate,
                end_date: endDate
            });
            window.location.href = './export-reports.php?' + params.toString();
        }
    </script>
</body>
</html>
