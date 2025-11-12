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
        $event_id = (int)($_POST['event_id'] ?? 0);
        $status = $_POST['status'] ?? '';

        if ($event_id && in_array($status, ['pending', 'approved', 'rejected', 'cancelled'])) {
            try {
                // Check if event exists
                $checkStmt = $pdo->prepare('SELECT title FROM events WHERE event_id = ?');
                $checkStmt->execute([$event_id]);
                $eventData = $checkStmt->fetch();

                if (!$eventData) {
                    $message = 'Event not found.';
                    $messageType = 'error';
                } else {
                    $stmt = $pdo->prepare('UPDATE events SET status = ? WHERE event_id = ?');
                    $stmt->execute([$status, $event_id]);

                    $message = 'Event status updated successfully.';
                    $messageType = 'success';
                    error_log("Admin {$_SESSION['user_email']} changed status of event '{$eventData['title']}' (ID: $event_id) to $status");
                }
            } catch (Exception $e) {
                $message = 'Database error: ' . $e->getMessage();
                $messageType = 'error';
            }
        } else {
            $message = 'Invalid event ID or status.';
            $messageType = 'error';
        }
    }

    if ($action === 'delete') {
        $event_id = (int)($_POST['event_id'] ?? 0);

        if ($event_id) {
            try {
                // Check if event exists and get info for logging
                $eventStmt = $pdo->prepare('SELECT title, organizer_id FROM events WHERE event_id = ?');
                $eventStmt->execute([$event_id]);
                $eventData = $eventStmt->fetch();

                if (!$eventData) {
                    $message = 'Event not found.';
                    $messageType = 'error';
                } else {
                    // Check for existing registrations
        $regStmt = $pdo->prepare('SELECT COUNT(*) as count FROM registration WHERE event_id = ?');
                    $regStmt->execute([$event_id]);
                    $regCount = $regStmt->fetch()['count'];

                    if ($regCount > 0) {
                        $message = 'Cannot delete event with existing registrations. Please cancel the event instead.';
                        $messageType = 'error';
                    } else {
                        $stmt = $pdo->prepare('DELETE FROM events WHERE event_id = ?');
                        $stmt->execute([$event_id]);

                        $message = 'Event deleted successfully.';
                        $messageType = 'success';
                        error_log("Admin {$_SESSION['user_email']} deleted event '{$eventData['title']}' (ID: $event_id)");
                    }
                }
            } catch (Exception $e) {
                $message = 'Database error: ' . $e->getMessage();
                $messageType = 'error';
            }
        } else {
            $message = 'Invalid event ID.';
            $messageType = 'error';
        }
    }
}

// Get filter parameters
$status_filter = $_GET['status'] ?? '';
$category_filter = $_GET['category'] ?? '';
$search = $_GET['search'] ?? '';

// Build query
$where_conditions = [];
$params = [];

if ($status_filter && in_array($status_filter, ['pending', 'approved', 'rejected', 'cancelled'])) {
    $where_conditions[] = 'e.status = ?';
    $params[] = $status_filter;
}

if ($category_filter) {
    $where_conditions[] = 'e.category = ?';
    $params[] = $category_filter;
}

if ($search) {
    $where_conditions[] = '(e.title LIKE ? OR e.description LIKE ? OR e.venue LIKE ?)';
    $searchParam = '%' . $search . '%';
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

$where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Initialize default values
$admin_name = 'Admin';
$events = [];
$total_events = 0;
$pending_events = 0;
$approved_events = 0;

// Get events with organizer info
$query = "
    SELECT
        e.*,
        u.email as organizer_email,
        u.name as organizer_name,
        (SELECT COUNT(*) FROM registration r WHERE r.event_id = e.event_id) as registration_count
    FROM events e
    LEFT JOIN users u ON e.organizer_id = u.user_id
    $where_clause
    ORDER BY e.created_at DESC
";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $events = $stmt->fetchAll();

    // Get current admin user info
    $current_user_stmt = $pdo->prepare('SELECT name, email FROM users WHERE user_id = ?');
    $current_user_stmt->execute([$_SESSION['user_id']]);
    $current_user = $current_user_stmt->fetch();
    $admin_name = $current_user['name'] ?? 'Admin';

    // Get statistics
    $total_events = (int)$pdo->query('SELECT COUNT(*) FROM events')->fetchColumn();
    $pending_events = (int)$pdo->query("SELECT COUNT(*) FROM events WHERE status = 'pending'")->fetchColumn();
    $approved_events = (int)$pdo->query("SELECT COUNT(*) FROM events WHERE status = 'approved'")->fetchColumn();
} catch (Exception $e) {
    $message = 'Database operation failed. Please try again.';
    $messageType = 'error';
    // Default values are already set above
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Events Management - <?php echo SITE_NAME; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="icon" type="image/png" href="../assets/images/dot.png">
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
                    <a href="./events.php" class="active">
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
                        <h1>Events Management</h1>
                        <p class="page-subtitle">Create and manage events for your platform</p>
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
                    <span>Events</span>
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
                        <h4>Total Events</h4>
                        <div class="value"><?php echo number_format($total_events); ?></div>
                        <div class="description">All events in system</div>
                    </div>
                    <div class="card">
                        <h4>Pending Approval</h4>
                        <div class="value"><?php echo number_format($pending_events); ?></div>
                        <div class="description">Awaiting review</div>
                    </div>
                    <div class="card">
                        <h4>Approved Events</h4>
                        <div class="value"><?php echo number_format($approved_events); ?></div>
                        <div class="description">Ready for registration</div>
                    </div>
                    <div class="card">
                        <h4>Total Registrations</h4>
                        <div class="value"><?php echo number_format(array_sum(array_column($events, 'registration_count'))); ?></div>
                        <div class="description">Across all events</div>
                    </div>
                </div>

                <!-- Search and Filters -->
                <div class="search-container">
                    <form method="GET" class="search-grid">
                        <div class="search-input">
                            <i data-lucide="search" class="search-icon"></i>
                            <input type="text" name="search" placeholder="Search events..." value="<?php echo htmlspecialchars($search); ?>" class="input">
                        </div>
                        <div style="display: flex; gap: 1rem;">
                            <select name="status" class="select">
                                <option value="">All Status</option>
                                <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                                <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                            <select name="category" class="select">
                                <option value="">All Categories</option>
                                <option value="technical" <?php echo $category_filter === 'technical' ? 'selected' : ''; ?>>Technical</option>
                                <option value="cultural" <?php echo $category_filter === 'cultural' ? 'selected' : ''; ?>>Cultural</option>
                                <option value="sports" <?php echo $category_filter === 'sports' ? 'selected' : ''; ?>>Sports</option>
                                <option value="workshop" <?php echo $category_filter === 'workshop' ? 'selected' : ''; ?>>Workshop</option>
                                <option value="seminar" <?php echo $category_filter === 'seminar' ? 'selected' : ''; ?>>Seminar</option>
                                <option value="other" <?php echo $category_filter === 'other' ? 'selected' : ''; ?>>Other</option>
                            </select>
                            <button type="submit" class="btn">
                                <i data-lucide="filter"></i>
                                Filter
                            </button>
                            <a href="./events.php" class="btn secondary">
                                <i data-lucide="x"></i>
                                Clear
                            </a>
                        </div>
                    </form>
                </div>

                <!-- Events Table -->
                <div class="table-container">
                    <div style="padding: 1.5rem; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <h3 style="margin: 0; font-size: 1.125rem; font-weight: 600; color: var(--text-primary);">Events List</h3>
                            <p style="margin: 0.25rem 0 0; color: var(--text-secondary); font-size: 0.875rem;">Manage all events in the system</p>
                        </div>
                        <div>
                            <a href="./add-event.php" class="btn">
                                <i data-lucide="plus"></i>
                                Add Event
                            </a>
                        </div>
                    </div>

                    <?php if (empty($events)): ?>
                        <div style="padding: 3rem; text-align: center;">
                            <i data-lucide="calendar-x" style="width: 48px; height: 48px; color: var(--text-muted); margin-bottom: 1rem;"></i>
                            <h3 style="color: var(--text-muted); margin-bottom: 0.5rem;">No events found</h3>
                            <p style="color: var(--text-secondary);">No events match your current filters.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Event</th>
                                        <th>Category</th>
                                        <th>Date & Time</th>
                                        <th>Organizer</th>
                                        <th>Registrations</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($events as $event): ?>
                                        <tr>
                                            <td>
                                                <div>
                                                    <div style="font-weight: 500; color: var(--text-primary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                                        <?php echo htmlspecialchars($event['title']); ?>
                                                    </div>
                                                    <div style="font-size: 0.75rem; color: var(--text-secondary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                                        <?php echo htmlspecialchars($event['venue']); ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge secondary">
                                                    <?php echo ucfirst($event['category']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div>
                                                    <div style="font-weight: 500;">
                                                        <?php echo date('M j, Y', strtotime($event['event_date'])); ?>
                                                    </div>
                                                    <div style="font-size: 0.875rem; color: var(--text-secondary);">
                                                        <?php echo $event['event_time'] ? date('g:i A', strtotime($event['event_time'])) : 'TBD'; ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div style="font-size: 0.875rem;">
                                                    <?php echo htmlspecialchars($event['organizer_email'] ?? 'Unknown'); ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div style="font-weight: 500;">
                                                    <?php echo number_format($event['registration_count']); ?>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge <?php
                                                    echo $event['status'] === 'approved' ? 'success' :
                                                        ($event['status'] === 'pending' ? 'warning' :
                                                        ($event['status'] === 'rejected' ? 'danger' : 'secondary'));
                                                ?>">
                                                    <?php echo ucfirst($event['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div style="display: flex; gap: 0.25rem; align-items: center; flex-wrap: wrap;">
                                                    <!-- Status Update Form -->
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="action" value="update_status">
                                                        <input type="hidden" name="event_id" value="<?php echo $event['event_id']; ?>">
                                                        <select name="status" class="select" style="padding: 0.25rem; font-size: 0.75rem; min-width: 80px;" onchange="this.form.submit()">
                                                            <option value="pending" <?php echo $event['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                            <option value="approved" <?php echo $event['status'] === 'approved' ? 'selected' : ''; ?>>Approved</option>
                                                            <option value="rejected" <?php echo $event['status'] === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                                            <option value="cancelled" <?php echo $event['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                                        </select>
                                                    </form>

                                                    <!-- View Registrations -->
                                                    <a href="./registrations.php?event_id=<?php echo $event['event_id']; ?>" class="btn ghost sm" title="View Registrations" style="padding: 0.25rem; font-size: 0.75rem;">
                                                        <i data-lucide="users"></i>
                                                    </a>

                                                    <!-- Delete Event -->
                                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this event? This action cannot be undone.')">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="event_id" value="<?php echo $event['event_id']; ?>">
                                                        <button type="submit" class="btn danger sm" title="Delete Event" style="padding: 0.25rem; font-size: 0.75rem;">
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

        // Auto-submit status changes
        document.addEventListener('DOMContentLoaded', function() {
            // Add confirmation for status changes
            const statusSelects = document.querySelectorAll('select[name="status"]');
            statusSelects.forEach(select => {
                select.addEventListener('change', function(e) {
                    const newStatus = this.value;
                    const eventTitle = this.closest('tr').querySelector('td:first-child div div').textContent;

                    if (newStatus === 'rejected' || newStatus === 'cancelled') {
                        if (!confirm(`Are you sure you want to ${newStatus} the event "${eventTitle}"?`)) {
                            e.preventDefault();
                            this.value = this.getAttribute('data-original-value') || 'pending';
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
