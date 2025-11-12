<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '_auth.php';
require_once '_error_handler.php';

admin_require_login();
ErrorHandler::clear();

$db = new Database();
$pdo = $db->getConnection();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Protection
    if (!SecurityHelper::validateCSRFToken($_POST['csrf_token'] ?? '')) {
        ErrorHandler::addError('Invalid security token. Please try again.');
    } else {
        // Rate limiting
        if (!SecurityHelper::checkRateLimit('admin_feedback_action', 10, 60)) {
            ErrorHandler::addError('Too many actions. Please wait before trying again.');
        } else {
            $action = InputValidator::sanitize($_POST['action'] ?? '');
            
            if ($action === 'update_status') {
                $feedback_id = InputValidator::validateInteger($_POST['feedback_id'] ?? 0);
                $status = InputValidator::sanitize($_POST['status'] ?? '');
                
                if ($feedback_id > 0 && in_array($status, ['pending', 'approved', 'hidden'])) {
                    try {
                        // Add status column to feedback table if it doesn't exist
                        try {
                            DatabaseHelper::execute($pdo, "ALTER TABLE feedback ADD COLUMN status ENUM('pending', 'approved', 'hidden') DEFAULT 'pending'", []);
                        } catch (PDOException $e) {
                            // Column might already exist, ignore error
                        }
                        
                        DatabaseHelper::execute($pdo, 'UPDATE feedback SET status = ? WHERE feedback_id = ?', [$status, $feedback_id]);
                        ErrorHandler::addSuccess('Feedback status updated successfully.');
                        
                        // Log the action
                        error_log("Admin feedback status update: ID {$feedback_id} to {$status} by " . $_SESSION['admin_id']);
                    } catch (Exception $e) {
                        ErrorHandler::addError('Failed to update feedback status.');
                        error_log("Feedback status update error: " . $e->getMessage());
                    }
                } else {
                    ErrorHandler::addError('Invalid feedback or status.');
                }
            }
            
            if ($action === 'delete') {
                $feedback_id = InputValidator::validateInteger($_POST['feedback_id'] ?? 0);
                if ($feedback_id > 0) {
                    try {
                        DatabaseHelper::execute($pdo, 'DELETE FROM feedback WHERE feedback_id = ?', [$feedback_id]);
                        ErrorHandler::addSuccess('Feedback deleted successfully.');
                        
                        // Log the deletion
                        error_log("Admin feedback deletion: ID {$feedback_id} by " . $_SESSION['admin_id']);
                    } catch (Exception $e) {
                        ErrorHandler::addError('Failed to delete feedback.');
                        error_log("Feedback deletion error: " . $e->getMessage());
                    }
                }
            }
        }
    }
}

// Get filter parameters
$status_filter = InputValidator::sanitize($_GET['status'] ?? '');
$rating_filter = InputValidator::validateInteger($_GET['rating'] ?? 0);
$search = InputValidator::sanitize($_GET['search'] ?? '');

// Build query
$where_conditions = [];
$params = [];

if ($status_filter && in_array($status_filter, ['pending', 'approved', 'hidden'])) {
    $where_conditions[] = 'f.status = ?';
    $params[] = $status_filter;
}

if ($rating_filter > 0 && $rating_filter <= 5) {
    $where_conditions[] = 'f.rating = ?';
    $params[] = $rating_filter;
}

if ($search) {
    $where_conditions[] = '(e.title LIKE ? OR f.comments LIKE ? OR ud.full_name LIKE ?)';
    $search_param = '%' . $search . '%';
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get feedback data from database
try {
    $feedback = DatabaseHelper::fetchAll($pdo, "
        SELECT 
            f.feedback_id,
            f.rating,
            f.comments,
            f.submitted_on,
            COALESCE(f.status, 'pending') as status,
            e.title as event_name,
            COALESCE(ud.full_name, u.email) as user_name
        FROM feedback f
        LEFT JOIN events e ON f.event_id = e.event_id
        LEFT JOIN users u ON f.student_id = u.user_id
        LEFT JOIN userdetails ud ON f.student_id = ud.user_id
        $where_clause
        ORDER BY f.submitted_on DESC
    ", $params);
    
    if (empty($feedback)) {
        $feedback = [];
    }
} catch (Exception $e) {
    ErrorHandler::addError('Failed to load feedback data.');
    error_log('Feedback query error: ' . $e->getMessage());
    $feedback = [];
}

// Get statistics
$total_feedback = count($feedback);
$pending_feedback = count(array_filter($feedback, fn($f) => ($f['status'] ?? 'pending') === 'pending'));
$approved_feedback = count(array_filter($feedback, fn($f) => ($f['status'] ?? 'pending') === 'approved'));
$average_rating = $feedback ? round(array_sum(array_column($feedback, 'rating')) / count($feedback), 1) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feedback Management - <?php echo SITE_NAME; ?></title>
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
                    <a href="./feedback.php" class="active">
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

        <main class="main">
            <header class="topbar">
                <div class="topbar-left">
                    <button class="btn-topbar" onclick="toggleSidebar()">
                        <i data-lucide="menu"></i>
                    </button>
                    <h1>Feedback Management</h1>
                </div>
                <div class="topbar-right">
                    <button class="btn-topbar" onclick="toggleNotifications()">
                        <i data-lucide="bell"></i>
                    </button>
                    <div class="user-menu">
                        <span>Admin</span>
                        <i data-lucide="chevron-down"></i>
                    </div>
                </div>
            </header>

            <div class="content">
                <!-- Breadcrumbs -->
                <div class="breadcrumbs">
                    <a href="./dashboard.php">Dashboard</a>
                    <span class="breadcrumb-separator">/</span>
                    <span>Feedback</span>
                </div>

                <!-- Alerts -->
                <?php ErrorHandler::displayMessages(); ?>



                <!-- Feedback Table -->
                <div class="table-card">
                    <div class="table-header">
                        <h3>Feedback Submissions</h3>
                        <p>Manage and moderate user feedback for events</p>
                    </div>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Event</th>
                                    <th>User</th>
                                    <th>Rating</th>
                                    <th>Comment</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($feedback)): ?>
                                    <tr>
                                        <td colspan="7" class="no-data">No feedback found.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($feedback as $item): ?>
                                        <tr>
                                            <td>
                                                <div class="event-info">
                                                    <div class="event-title"><?php echo htmlspecialchars($item['event_name'] ?? 'Unknown Event'); ?></div>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($item['user_name'] ?? 'Unknown User'); ?></td>
                                            <td>
                                                <div class="rating">
                                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                                        <span class="star <?php echo $i <= $item['rating'] ? 'filled' : ''; ?>">★</span>
                                                    <?php endfor; ?>
                                                    <span class="rating-number">(<?php echo $item['rating']; ?>/5)</span>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="comment-preview">
                                                    <?php echo htmlspecialchars(substr($item['comments'] ?? '', 0, 50)) . (strlen($item['comments'] ?? '') > 50 ? '...' : ''); ?>
                                                </div>
                                            </td>
                                            <td><?php echo date('M j, Y', strtotime($item['submitted_on'])); ?></td>
                                            <td>
                                                <span class="status-badge <?php echo $item['status']; ?>">
                                                    <?php echo ucfirst($item['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="action-buttons">
                                                    <button class="btn-icon" title="View Full Comment" onclick="viewComment(<?php echo $item['feedback_id']; ?>, '<?php echo htmlspecialchars($item['comments'] ?? '', ENT_QUOTES); ?>')">
                                                        <i data-lucide="eye"></i>
                                                    </button>
                                                    <?php if ($item['status'] === 'pending'): ?>
                                                        <form method="POST" style="display: inline;">
                                                            <input type="hidden" name="action" value="update_status">
                                                            <input type="hidden" name="csrf_token" value="<?php echo SecurityHelper::generateCSRFToken(); ?>">
                                                            <input type="hidden" name="feedback_id" value="<?php echo $item['feedback_id']; ?>">
                                                            <input type="hidden" name="status" value="approved">
                                                            <button type="submit" class="btn-icon success" title="Approve">
                                                                <i data-lucide="check"></i>
                                                            </button>
                                                        </form>
                                                        <form method="POST" style="display: inline;">
                                                            <input type="hidden" name="action" value="update_status">
                                                            <input type="hidden" name="csrf_token" value="<?php echo SecurityHelper::generateCSRFToken(); ?>">
                                                            <input type="hidden" name="feedback_id" value="<?php echo $item['feedback_id']; ?>">
                                                            <input type="hidden" name="status" value="hidden">
                                                            <button type="submit" class="btn-icon danger" title="Hide">
                                                                <i data-lucide="eye-off"></i>
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this feedback?');">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="csrf_token" value="<?php echo SecurityHelper::generateCSRFToken(); ?>">
                                                        <input type="hidden" name="feedback_id" value="<?php echo $item['feedback_id']; ?>">
                                                        <button type="submit" class="btn-icon danger" title="Delete">
                                                            <i data-lucide="trash-2"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Comment Modal -->
    <div id="commentModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Full Comment</h3>
                <button class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body">
                <p id="fullComment"></p>
            </div>
        </div>
    </div>

    <script>
        // Initialize Lucide icons
        lucide.createIcons();

        function toggleSidebar() {
            document.querySelector('.sidebar').classList.toggle('collapsed');
        }

        function toggleNotifications() {
            alert('Notifications feature coming soon!');
        }

        function viewComment(feedbackId, comment) {
            document.getElementById('fullComment').textContent = comment;
            document.getElementById('commentModal').style.display = 'block';
        }

        function closeModal() {
            document.getElementById('commentModal').style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('commentModal');
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        }
    </script>
</body>
</html>
