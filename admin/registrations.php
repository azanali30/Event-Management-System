<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once './_auth.php';

admin_require_login();

$db = new Database();
$pdo = $db->getConnection();

$message = '';
$messageType = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_status') {
        $registration_id = (int)($_POST['registration_id'] ?? 0);
        $status = $_POST['status'] ?? '';

        if ($registration_id > 0 && in_array($status, ['pending', 'confirmed', 'rejected', 'waitlist_pending'])) {
            try {
                $stmt = $pdo->prepare('UPDATE registration SET status = ? WHERE id = ?');
                $stmt->execute([$status, $registration_id]);

                $message = 'Registration status updated successfully.';
                $messageType = 'success';
                error_log("Admin registration status update: ID {$registration_id} to {$status} by " . $_SESSION['user_email']);
            } catch (Exception $e) {
                $message = 'Failed to update registration status.';
                $messageType = 'error';
                error_log("Registration status update error: " . $e->getMessage());
            }
        } else {
            $message = 'Invalid registration or status.';
            $messageType = 'error';
        }
    }

    if ($action === 'delete') {
        $registration_id = (int)($_POST['registration_id'] ?? 0);
        if ($registration_id > 0) {
            try {
                $stmt = $pdo->prepare('DELETE FROM registration WHERE id = ?');
                $stmt->execute([$registration_id]);

                $message = 'Registration deleted successfully.';
                $messageType = 'success';
                error_log("Admin registration deletion: ID {$registration_id} by " . $_SESSION['user_email']);
            } catch (Exception $e) {
                $message = 'Failed to delete registration.';
                $messageType = 'error';
                error_log("Registration deletion error: " . $e->getMessage());
            }
        } else {
            $message = 'Invalid registration ID.';
            $messageType = 'error';
        }
    }
}

// Add CSRF token validation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !SecurityHelper::validateCSRFToken($_POST['csrf_token'])) {
        $message = 'Invalid security token. Please try again.';
        $messageType = 'error';
    }
}

// Get filter parameters
$event_id = (int)($_GET['event_id'] ?? 0);
$status_filter = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';

// Build query
$where_conditions = [];
$params = [];

if ($event_id > 0) {
    $where_conditions[] = 'r.event_id = ?';
    $params[] = $event_id;
}

if ($status_filter && in_array($status_filter, ['pending', 'confirmed', 'rejected', 'waitlist_pending'])) {
    $where_conditions[] = 'r.status = ?';
    $params[] = $status_filter;
}

if ($search) {
    $where_conditions[] = '(r.student_email LIKE ? OR e.title LIKE ? OR r.student_name LIKE ? OR ud.department LIKE ?)';
    $search_param = '%' . $search . '%';
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get registrations with event info
$query = "
    SELECT
        r.*,
        r.student_email as user_email,
        r.student_name as user_name,
        ud.department as user_department,
        ud.mobile as user_mobile,
        e.title as event_title,
        e.event_date,
        e.event_time,
        e.venue
    FROM registration r
    LEFT JOIN events e ON r.event_id = e.event_id
    LEFT JOIN users u ON r.student_id = u.user_id
    LEFT JOIN userdetails ud ON u.user_id = ud.user_id
    $where_clause
    ORDER BY r.registered_on DESC
";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $registrations = $stmt->fetchAll();

    if (empty($registrations)) {
        $registrations = [];
    }
} catch (Exception $e) {
    $message = 'Failed to load registrations data.';
    $messageType = 'error';
    error_log('Registrations query error: ' . $e->getMessage());
    $registrations = [];
}

// Get statistics
try {
    $total_registrations = (int)$pdo->query('SELECT COUNT(*) FROM registration')->fetchColumn();
    $pending_registrations = 0; // registration table doesn't have status column
    $approved_registrations = (int)$pdo->query('SELECT COUNT(*) FROM registration WHERE approved_at IS NOT NULL')->fetchColumn();
    $rejected_registrations = 0; // registration table doesn't have rejected status
    $waitlist_registrations = 0; // registration table doesn't have waitlist status
} catch (Exception $e) {
    $message = 'Failed to load statistics.';
    $messageType = 'error';
    error_log('Statistics query error: ' . $e->getMessage());
    $total_registrations = $pending_registrations = $approved_registrations = $rejected_registrations = $waitlist_registrations = 0;
}

// Get events for filter dropdown
try {
    $events_stmt = $pdo->prepare('SELECT event_id, title FROM events ORDER BY title');
    $events_stmt->execute();
    $events = $events_stmt->fetchAll();
} catch (Exception $e) {
    $message = 'Failed to load events list.';
    $messageType = 'error';
    error_log('Events query error: ' . $e->getMessage());
    $events = [];
}

// Get current admin user info
$admin_name = 'Admin';
try {
    $current_user_stmt = $pdo->prepare('SELECT name, email FROM users WHERE id = ?');
    $current_user_stmt->execute([$_SESSION['user_id']]);
    $current_user = $current_user_stmt->fetch();
    $admin_name = $current_user['name'] ?? 'Admin';
} catch (Exception $e) {
    error_log('Failed to get admin user info: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrations Management - <?php echo SITE_NAME; ?></title>
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
                    <a href="./registrations.php" class="active">
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
                        <h1>Registrations Management</h1>
                        <p class="page-subtitle">Monitor and manage event registrations</p>
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
                    <span>Registrations</span>
                </div>

                <!-- Alerts -->
                <?php if ($message): ?>
                    <div class="alert <?php echo $messageType === 'error' ? 'alert-error' : 'alert-success'; ?>">
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>

                <!-- Statistics Cards -->
                <div class="cards">
                    <div class="card">
                        <h4>Total Registrations</h4>
                        <div class="value"><?php echo number_format($total_registrations); ?></div>
                        <div class="description">All event registrations</div>
                    </div>
                    <div class="card">
                        <h4>Pending</h4>
                        <div class="value"><?php echo number_format($pending_registrations); ?></div>
                        <div class="description">Awaiting approval</div>
                    </div>
                    <div class="card">
                        <h4>Approved</h4>
                        <div class="value"><?php echo number_format($approved_registrations); ?></div>
                        <div class="description">Approved registrations</div>
                    </div>
                    <div class="card">
                        <h4>Rejected</h4>
                        <div class="value"><?php echo number_format($rejected_registrations); ?></div>
                        <div class="description">Rejected registrations</div>
                    </div>
                </div>

                <!-- Search and Filters -->
                <div class="search-container">
                    <form method="GET" class="search-grid">
                        <div class="search-input">
                            <i data-lucide="search" class="search-icon"></i>
                            <input type="text" name="search" placeholder="Search registrations..." value="<?php echo htmlspecialchars($search); ?>" class="input">
                        </div>
                        <div style="display: flex; gap: 1rem;">
                            <select name="event_id" class="select">
                                <option value="">All Events</option>
                                <?php foreach ($events as $event): ?>
                                    <option value="<?php echo $event['event_id']; ?>" <?php echo $event_id == $event['event_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($event['title']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <select name="status" class="select">
                                <option value="">All Status</option>
                                <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="confirmed" <?php echo $status_filter === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                <option value="waitlist_pending" <?php echo $status_filter === 'waitlist_pending' ? 'selected' : ''; ?>>Waitlist</option>
                            </select>
                            <button type="submit" class="btn">
                                <i data-lucide="filter"></i>
                                Filter
                            </button>
                            <a href="./registrations.php" class="btn secondary">
                                <i data-lucide="x"></i>
                                Clear
                            </a>
                            <button type="button" class="btn success" onclick="exportRegistrations()">
                                <i data-lucide="download"></i>
                                Export
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Registrations Table -->
                <div class="table-container">
                    <div style="padding: 1.5rem; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <h3 style="margin: 0; font-size: 1.125rem; font-weight: 600; color: var(--text-primary);">Registrations List</h3>
                            <p style="margin: 0.25rem 0 0; color: var(--text-secondary); font-size: 0.875rem;">Manage all event registrations</p>
                        </div>
                    </div>

                    <?php if (empty($registrations)): ?>
                        <div style="padding: 3rem; text-align: center;">
                            <i data-lucide="user-x" style="width: 48px; height: 48px; color: var(--text-muted); margin-bottom: 1rem;"></i>
                            <h3 style="color: var(--text-muted); margin-bottom: 0.5rem;">No registrations found</h3>
                            <p style="color: var(--text-secondary);">No registrations match your current filters.</p>
                        </div>
                    <?php else: ?>
                        <div style="overflow-x: auto;">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Participant</th>
                                        <th>Event</th>
                                        <th>Registration Date</th>
                                        <th>Contact</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($registrations as $registration): ?>
                                        <tr>
                                            <td>
                                                <div>
                                                    <div style="font-weight: 500; color: var(--text-primary);">
                                                        <?php echo htmlspecialchars($registration['user_name'] ?: $registration['user_email']); ?>
                                                    </div>
                                                    <div style="font-size: 0.875rem; color: var(--text-secondary);">
                                                        <?php echo htmlspecialchars($registration['user_email']); ?>
                                                    </div>
                                                    <?php if (!empty($registration['user_department'])): ?>
                                                        <div style="font-size: 0.75rem; color: var(--text-muted);">
                                                            <?php echo htmlspecialchars($registration['user_department']); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div>
                                                    <div style="font-weight: 500; color: var(--text-primary);">
                                                        <?php echo htmlspecialchars($registration['event_title']); ?>
                                                    </div>
                                                    <div style="font-size: 0.875rem; color: var(--text-secondary);">
                                                        <?php echo date('M j, Y', strtotime($registration['event_date'])); ?>
                                                        <?php if ($registration['event_time']): ?>
                                                            at <?php echo date('g:i A', strtotime($registration['event_time'])); ?>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div style="font-size: 0.75rem; color: var(--text-muted);">
                                                        <?php echo htmlspecialchars($registration['venue']); ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div style="font-size: 0.875rem;">
                                                    <?php echo date('M j, Y g:i A', strtotime($registration['registered_on'])); ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div style="font-size: 0.875rem;">
                                                    <?php if (!empty($registration['user_mobile'])): ?>
                                                        <div><?php echo htmlspecialchars($registration['user_mobile']); ?></div>
                                                    <?php else: ?>
                                                        <span style="color: var(--text-muted);">No phone</span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge <?php
                                                    echo $registration['status'] === 'confirmed' ? 'success' :
                                                        ($registration['status'] === 'pending' ? 'warning' : 
                                                        ($registration['status'] === 'waitlist_pending' ? 'info' : 'danger'));
                                                ?>">
                                                    <?php echo ucfirst(str_replace('_', ' ', $registration['status'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div style="display: flex; gap: 0.5rem; align-items: center;">
                                                    <!-- Status Update Form -->
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="action" value="update_status">
                                                        <input type="hidden" name="csrf_token" value="<?php echo SecurityHelper::generateCSRFToken(); ?>">
                                                        <input type="hidden" name="registration_id" value="<?php echo $registration['id']; ?>">
                                                        <select name="status" class="select" style="padding: 0.25rem 0.5rem; font-size: 0.75rem;" onchange="this.form.submit()">
                                                            <option value="pending" <?php echo $registration['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                            <option value="confirmed" <?php echo $registration['status'] === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                                            <option value="rejected" <?php echo $registration['status'] === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                                            <option value="waitlist_pending" <?php echo $registration['status'] === 'waitlist_pending' ? 'selected' : ''; ?>>Waitlist</option>
                                                        </select>
                                                    </form>

                                                    <!-- Delete Registration -->
                                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this registration? This action cannot be undone.')">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="csrf_token" value="<?php echo SecurityHelper::generateCSRFToken(); ?>">
                                                        <input type="hidden" name="registration_id" value="<?php echo $registration['id']; ?>">
                                                        <button type="submit" class="btn danger sm" title="Delete Registration">
                                                            <i data-lucide="trash-2"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Initialize Lucide icons
        lucide.createIcons();

        // Export functionality
        function exportRegistrations() {
            const params = new URLSearchParams(window.location.search);
            params.set('export', 'csv');
            window.location.href = './export-registrations.php?' + params.toString();
        }

        // Auto-submit status changes with confirmation
        document.addEventListener('DOMContentLoaded', function() {
            const statusSelects = document.querySelectorAll('select[name="status"]');
            statusSelects.forEach(select => {
                select.addEventListener('change', function(e) {
                    const newStatus = this.value;
                    const userName = this.closest('tr').querySelector('td:first-child div div').textContent;

                    if (newStatus === 'cancelled') {
                        if (!confirm(`Are you sure you want to cancel the registration for "${userName}"?`)) {
                            e.preventDefault();
                            this.value = this.getAttribute('data-original-value') || 'confirmed';
                            return false;
                        }
                    }
                });

                // Store original value
                select.setAttribute('data-original-value', select.value);
            });
        });
    </script>
</body>
</html>
