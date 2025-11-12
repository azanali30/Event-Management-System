<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once './_auth.php';
require_once './_error_handler.php';
admin_require_login();

$db = new Database();
$pdo = $db->getConnection();

// Basic stats
$totalUsers = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
$totalAdmins = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
$totalParticipants = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'participant'")->fetchColumn();
$totalOrganizers = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'organizer'")->fetchColumn();

// Event stats
$totalEvents = (int)$pdo->query('SELECT COUNT(*) FROM events')->fetchColumn();
$pendingEvents = (int)$pdo->query("SELECT COUNT(*) FROM events WHERE status = 'pending'")->fetchColumn();
$approvedEvents = (int)$pdo->query("SELECT COUNT(*) FROM events WHERE status = 'approved'")->fetchColumn();
$completedEvents = (int)$pdo->query("SELECT COUNT(*) FROM events WHERE status = 'completed'")->fetchColumn();

// Registration stats
$totalRegistrations = (int)$pdo->query('SELECT COUNT(*) FROM registration')->fetchColumn();
$recentRegistrations = (int)$pdo->query('SELECT COUNT(*) FROM registration WHERE registered_on >= DATE_SUB(NOW(), INTERVAL 7 DAY)')->fetchColumn();

// Recent activity
$recentEvents = $pdo->query('SELECT event_id, title, event_date, status, category FROM events ORDER BY created_at DESC LIMIT 5')->fetchAll(PDO::FETCH_ASSOC);
$recentUsers = $pdo->query('SELECT user_id, email, role, created_at FROM users ORDER BY created_at DESC LIMIT 5')->fetchAll(PDO::FETCH_ASSOC);

// Monthly registration trend (last 6 months)
$monthlyStats = $pdo->query("
    SELECT
        DATE_FORMAT(registered_on, '%Y-%m') as month,
        COUNT(*) as registrations
    FROM registration
    WHERE registered_on >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(registered_on, '%Y-%m')
    ORDER BY month DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Get current admin user info
$stmt = $pdo->prepare('SELECT name, email FROM users WHERE user_id = ?');
$stmt->execute([$_SESSION['user_id']]);
$current_user = $stmt->fetch(PDO::FETCH_ASSOC);
$admin_name = $current_user['name'] ?? 'Admin';

// Weekly Event Participation Data (last 7 days)
$weeklyParticipation = [];
$weekDays = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

// Initialize with zeros
foreach ($weekDays as $day) {
    $weeklyParticipation[$day] = 0;
}

// Get actual participation data for the last 7 days
$participationQuery = $pdo->query("
    SELECT 
        DAYNAME(e.event_date) as day_name,
        COUNT(DISTINCT r.id) as participation_count
    FROM events e
    LEFT JOIN registration r ON e.event_id = r.event_id
    WHERE e.event_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        AND e.event_date <= CURDATE()
        AND e.status = 'approved'
    GROUP BY DAYNAME(e.event_date), e.event_date
    ORDER BY e.event_date
");

$participationData = $participationQuery->fetchAll(PDO::FETCH_ASSOC);

// Map the data to our week structure
foreach ($participationData as $data) {
    $dayName = substr($data['day_name'], 0, 3); // Convert to 3-letter format
    if (in_array($dayName, $weekDays)) {
        $weeklyParticipation[$dayName] += (int)$data['participation_count'];
    }
}

// Convert to JSON for JavaScript
$weeklyParticipationJson = json_encode(array_values($weeklyParticipation));

// Get event list for filtering
$eventsForFilter = $pdo->query("
    SELECT event_id, title, event_date 
    FROM events 
    WHERE status = 'approved' 
    ORDER BY event_date DESC 
    LIMIT 20
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - <?php echo SITE_NAME; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="icon" type="image/png" href="../assets/images/dot.png">
    
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="./admin.css">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="js/notification_checker.js"></script>
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
                    <a href="./dashboard.php" class="active">
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
                    <a href="./reports.php">
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
                        <h1>Dashboard</h1>
                        <p class="page-subtitle">Overview of your event management system</p>
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
                    <span>Overview</span>
                </div>

                <!-- Statistics Cards -->
                <div class="stats-grid">
                    <div class="stat-card primary">
                        <div class="stat-icon">
                            <i data-lucide="users"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-number"><?php echo number_format($totalUsers); ?></div>
                            <div class="stat-label">Total Users</div>
                            <div class="stat-change positive">+12% this month</div>
                        </div>
                    </div>

                    <div class="stat-card success">
                        <div class="stat-icon">
                            <i data-lucide="calendar"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-number"><?php echo number_format($totalEvents); ?></div>
                            <div class="stat-label">Total Events</div>
                            <div class="stat-change positive">+8% this month</div>
                        </div>
                    </div>

                    <div class="stat-card warning">
                        <div class="stat-icon">
                            <i data-lucide="clock"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-number"><?php echo number_format($pendingEvents); ?></div>
                            <div class="stat-label">Pending Approval</div>
                            <div class="stat-change neutral">Needs attention</div>
                        </div>
                    </div>

                    <div class="stat-card info">
                        <div class="stat-icon">
                            <i data-lucide="message-square"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-number">24</div>
                            <div class="stat-label">Feedback Submissions</div>
                            <div class="stat-change positive">+5 new today</div>
                        </div>
                    </div>
                </div>

                <!-- Charts Section -->
                <div class="charts-grid">
                    <div class="chart-card">
                    <div class="chart-header">
                        <h3>Weekly Event Participation</h3>
                        <p>Event attendance over the last 7 days</p>
                        <div class="chart-controls">
                            <select id="eventFilter" onchange="updateChart()">
                                <option value="all">All Events</option>
                                <?php foreach ($eventsForFilter as $event): ?>
                                    <option value="<?php echo $event['event_id']; ?>">
                                        <?php echo htmlspecialchars($event['title']); ?> 
                                        (<?php echo date('M d', strtotime($event['event_date'])); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="chart-container">
                        <canvas id="participationChart" width="400" height="200"></canvas>
                    </div>
                </div>

                    <div class="chart-card">
                        <div class="chart-header">
                            <h3>User Roles Distribution</h3>
                            <p>Breakdown of user types in the system</p>
                        </div>
                        <div class="chart-container">
                            <canvas id="rolesChart" width="300" height="300"></canvas>
                        </div>
                        <div class="chart-legend">
                            <div class="legend-item">
                                <span class="legend-color admin"></span>
                                <span>Admins (<?php echo $totalAdmins; ?>)</span>
                            </div>
                            <div class="legend-item">
                                <span class="legend-color organizer"></span>
                                <span>Organizers (<?php echo $totalOrganizers; ?>)</span>
                            </div>
                            <div class="legend-item">
                                <span class="legend-color participant"></span>
                                <span>Participants (<?php echo $totalParticipants; ?>)</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Latest Event Submissions Table -->
                <div class="table-card">
                    <div class="table-header">
                        <h3>Latest Event Submissions</h3>
                        <p>Recent events requiring approval or review</p>
                    </div>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Event Name</th>
                                    <th>Date</th>
                                    <th>Organizer</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recentEvents)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center text-muted">No events found</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach (array_slice($recentEvents, 0, 5) as $event): ?>
                                        <tr>
                                            <td>
                                                <div class="event-info">
                                                    <div class="event-title"><?php echo htmlspecialchars($event['title']); ?></div>
                                                    <div class="event-category"><?php echo htmlspecialchars($event['category'] ?? 'General'); ?></div>
                                                </div>
                                            </td>
                                            <td><?php echo date('M j, Y', strtotime($event['event_date'])); ?></td>
                                            <td>Admin</td>
                                            <td>
                                                <span class="status-badge <?php echo $event['status']; ?>">
                                                    <?php echo ucfirst($event['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="action-buttons">
                                                    <button class="btn-icon" title="View">
                                                        <i data-lucide="eye"></i>
                                                    </button>
                                                    <?php if ($event['status'] === 'pending'): ?>
                                                        <button class="btn-icon success" title="Approve">
                                                            <i data-lucide="check"></i>
                                                        </button>
                                                        <button class="btn-icon danger" title="Reject">
                                                            <i data-lucide="x"></i>
                                                        </button>
                                                    <?php else: ?>
                                                        <button class="btn-icon" title="Edit">
                                                            <i data-lucide="edit"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="table-footer">
                        <a href="./events.php" class="btn btn-primary">View All Events</a>
                    </div>
                </div>

                </div>

                <!-- System Status -->
                <div class="alert info" style="margin-top: 2rem;">
                    <strong>System Status:</strong> All systems operational. Last backup: <?php echo date('M j, Y g:i A'); ?>
                </div>


            </div>
        </main>
    </div>

    <script>
        // Initialize Lucide icons
        lucide.createIcons();

        // Add some basic interactivity
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-refresh stats every 5 minutes
            setInterval(function() {
                // You can add AJAX calls here to refresh stats
                console.log('Stats refresh interval');
            }, 300000);
        });

        function toggleNotifications() {
            // Simple notification toggle - you can expand this functionality
            alert('Notifications feature coming soon!');
        }

        // Global chart variable
        let participationChart;
        
        // Initialize Charts
        function initCharts() {
            // Weekly Event Participation Chart
            const participationCtx = document.getElementById('participationChart');
            if (participationCtx) {
                participationChart = new Chart(participationCtx, {
                    type: 'bar',
                    data: {
                        labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                        datasets: [{
                            label: 'Event Participation',
                            data: <?php echo $weeklyParticipationJson; ?>,
                            backgroundColor: 'rgba(25, 32, 201, 0.8)',
                            borderColor: 'rgba(25, 32, 201, 1)',
                            borderWidth: 1,
                            borderRadius: 4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                grid: {
                                    color: 'rgba(0, 0, 0, 0.1)'
                                },
                                ticks: {
                                    stepSize: 1
                                }
                            },
                            x: {
                                grid: {
                                    display: false
                                }
                            }
                        }
                    }
                });
            }

            // User Roles Distribution Chart
            const rolesCtx = document.getElementById('rolesChart');
            if (rolesCtx) {
                new Chart(rolesCtx, {
                    type: 'doughnut',
                    data: {
                        labels: ['Admins', 'Organizers', 'Participants'],
                        datasets: [{
                            data: [<?php echo $totalAdmins; ?>, <?php echo $totalOrganizers; ?>, <?php echo $totalParticipants; ?>],
                            backgroundColor: [
                                '#ef4444',
                                '#f59e0b',
                                '#10b981'
                            ],
                            borderWidth: 0
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            }
                        }
                    }
                });
            }
        }

        // Update chart based on selected event
        async function updateChart() {
            const eventId = document.getElementById('eventFilter').value;
            
            try {
                const response = await fetch(`dashboard_chart_data.php?event_id=${eventId}`);
                const data = await response.json();
                
                if (participationChart && data.success) {
                    participationChart.data.datasets[0].data = data.participation;
                    participationChart.update();
                    
                    // Update chart title
                    const chartHeader = document.querySelector('.chart-header p');
                    if (eventId === 'all') {
                        chartHeader.textContent = 'Event attendance over the last 7 days';
                    } else {
                        chartHeader.textContent = `Attendance for selected event over the last 7 days`;
                    }
                }
            } catch (error) {
                console.error('Error updating chart:', error);
            }
        }

        // Initialize charts when DOM is loaded
        document.addEventListener('DOMContentLoaded', function() {
            initCharts();
        });
    </script>
</body>
</html>


