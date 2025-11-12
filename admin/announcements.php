<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once './_auth.php';
require_once './_error_handler.php';
admin_require_login();

$db = new Database();
$pdo = $db->getConnection();

// Clear any previous errors
ErrorHandler::clear();

// Get errors and notices for display
$errors = ErrorHandler::getErrors();
$notice = ErrorHandler::getSuccessMessage();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // CSRF Protection
        if (!SecurityHelper::validateCSRFToken($_POST['csrf_token'] ?? '')) {
            throw new Exception('Invalid security token. Please try again.');
        }
        
        // Rate limiting
        RateLimiter::checkLimit('admin_announcements_' . $_SESSION['admin_id'], 20, 300);
        
        $action = InputValidator::sanitize($_POST['action'] ?? '');
        
        if ($action === 'create') {
            $title = InputValidator::sanitize($_POST['title'] ?? '');
            $content = InputValidator::sanitize($_POST['content'] ?? '');
            $priority = InputValidator::sanitize($_POST['priority'] ?? 'normal');
            $status = InputValidator::sanitize($_POST['status'] ?? 'draft');
            
            if (empty($title)) {
                throw new Exception('Title is required.');
            }
            if (empty($content)) {
                throw new Exception('Content is required.');
            }
            
            $query = 'INSERT INTO announcements (title, content, priority, status, created_by) VALUES (?, ?, ?, ?, ?)';
            $params = [$title, $content, $priority, $status, $_SESSION['admin_id']];
            
            if (DatabaseHelper::execute($query, $params)) {
                ErrorHandler::addSuccess('Announcement created successfully.');
            } else {
                throw new Exception('Failed to create announcement.');
            }
            
        } elseif ($action === 'update') {
            $id = InputValidator::validateInteger($_POST['id'] ?? 0, 1);
            $title = InputValidator::sanitize($_POST['title'] ?? '');
            $content = InputValidator::sanitize($_POST['content'] ?? '');
            $priority = InputValidator::sanitize($_POST['priority'] ?? 'normal');
            $status = InputValidator::sanitize($_POST['status'] ?? 'draft');
            
            if ($id === false) {
                throw new Exception('Invalid announcement ID.');
            }
            if (empty($title)) {
                throw new Exception('Title is required.');
            }
            if (empty($content)) {
                throw new Exception('Content is required.');
            }
            
            $query = 'UPDATE announcements SET title = ?, content = ?, priority = ?, status = ? WHERE announcement_id = ?';
            $params = [$title, $content, $priority, $status, $id];
            
            if (DatabaseHelper::execute($query, $params)) {
                ErrorHandler::addSuccess('Announcement updated successfully.');
            } else {
                throw new Exception('Failed to update announcement.');
            }
            
        } elseif ($action === 'delete') {
            $id = InputValidator::validateInteger($_POST['id'] ?? 0, 1);
            
            if ($id === false) {
                throw new Exception('Invalid announcement ID.');
            }
            
            $query = 'DELETE FROM announcements WHERE announcement_id = ?';
            
            if (DatabaseHelper::execute($query, [$id])) {
                ErrorHandler::addSuccess('Announcement deleted successfully.');
            } else {
                throw new Exception('Failed to delete announcement.');
            }
        }
        
    } catch (Exception $e) {
        ErrorHandler::addError($e->getMessage());
        error_log('Announcements error: ' . $e->getMessage());
    }
}

// Get filter parameters
$status_filter = InputValidator::sanitize($_GET['status'] ?? '');
$priority_filter = InputValidator::sanitize($_GET['priority'] ?? '');
$search = InputValidator::sanitize($_GET['search'] ?? '');

// Validate status filter
if ($status_filter && !in_array($status_filter, ['draft', 'published', 'archived'])) {
    $status_filter = '';
}

// Validate priority filter
if ($priority_filter && !in_array($priority_filter, ['low', 'normal', 'high', 'urgent'])) {
    $priority_filter = '';
}

// Build query
$where_conditions = [];
$params = [];

if ($status_filter) {
    $where_conditions[] = 'status = ?';
    $params[] = $status_filter;
}

if ($priority_filter) {
    $where_conditions[] = 'priority = ?';
    $params[] = $priority_filter;
}

if ($search) {
    $where_conditions[] = '(title LIKE ? OR content LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}

$where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get announcements
try {
    $query = "SELECT a.*, u.email as creator_email FROM announcements a LEFT JOIN users u ON a.created_by = u.user_id $where_clause ORDER BY a.created_at DESC";
    $announcements = DatabaseHelper::fetchAll($pdo, $query, $params);
    if (empty($announcements)) {
        $announcements = [];
    }
} catch (Exception $e) {
    ErrorHandler::addError('Failed to load announcements.');
    $announcements = [];
}

// Get announcement for editing
$editing_announcement = null;
if (isset($_GET['edit'])) {
    $edit_id = InputValidator::validateInteger($_GET['edit'], 1);
    if ($edit_id !== false) {
        try {
            $editing_announcement = DatabaseHelper::fetchOne($pdo, 'SELECT * FROM announcements WHERE announcement_id = ?', [$edit_id]);
        } catch (Exception $e) {
            ErrorHandler::addError('Failed to load announcement for editing.');
        }
    }
}

// Get current admin user info
$current_user = DatabaseHelper::fetchOne($pdo, 'SELECT name, email FROM users WHERE user_id = ?', [$_SESSION['user_id']]);
$admin_name = $current_user['name'] ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Announcements Management - <?php echo SITE_NAME; ?></title>
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
                    <a href="./announcements.php" class="active">
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
                        <h1>Announcements</h1>
                        <p class="page-subtitle">Manage system announcements and notifications</p>
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
                    </div>
                </div>
            </div>
            <div class="content">
                <!-- Breadcrumbs -->
                <div class="breadcrumbs">
                    <a href="./dashboard.php">Dashboard</a>
                    <span class="breadcrumb-separator">/</span>
                    <span>Announcements</span>
                </div>

                <?php ErrorHandler::displayMessages(); ?>

                <!-- Search and Filters -->
                <div class="search-container">
                    <form method="GET" class="search-grid">
                        <div class="search-input">
                            <i data-lucide="search" class="search-icon"></i>
                            <input type="text" name="search" placeholder="Search announcements..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <select name="status">
                            <option value="">All Status</option>
                            <option value="draft" <?php echo $status_filter === 'draft' ? 'selected' : ''; ?>>Draft</option>
                            <option value="published" <?php echo $status_filter === 'published' ? 'selected' : ''; ?>>Published</option>
                            <option value="archived" <?php echo $status_filter === 'archived' ? 'selected' : ''; ?>>Archived</option>
                        </select>
                        <select name="priority">
                            <option value="">All Priorities</option>
                            <option value="low" <?php echo $priority_filter === 'low' ? 'selected' : ''; ?>>Low</option>
                            <option value="normal" <?php echo $priority_filter === 'normal' ? 'selected' : ''; ?>>Normal</option>
                            <option value="high" <?php echo $priority_filter === 'high' ? 'selected' : ''; ?>>High</option>
                            <option value="urgent" <?php echo $priority_filter === 'urgent' ? 'selected' : ''; ?>>Urgent</option>
                        </select>
                        <button type="submit" class="btn btn-primary">Filter</button>
                    </form>
                </div>

                <!-- Create/Edit Form -->
                <div class="form-card">
                    <div class="form-header">
                        <h3><?php echo $editing_announcement ? 'Edit Announcement' : 'Create New Announcement'; ?></h3>
                        <p><?php echo $editing_announcement ? 'Update the announcement details below.' : 'Fill in the details to create a new announcement.'; ?></p>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo SecurityHelper::generateCSRFToken(); ?>">
                        <input type="hidden" name="action" value="<?php echo $editing_announcement ? 'update' : 'create'; ?>">
                        <?php if ($editing_announcement): ?>
                            <input type="hidden" name="id" value="<?php echo $editing_announcement['announcement_id']; ?>">
                        <?php endif; ?>
                        
                        <div class="form-grid">
                            <div class="field">
                                <label for="title" class="required">Title</label>
                                <input type="text" id="title" name="title" class="input" 
                                       value="<?php echo htmlspecialchars($editing_announcement['title'] ?? ''); ?>" required>
                            </div>
                            
                            <div class="field">
                                <label for="priority">Priority</label>
                                <select id="priority" name="priority" class="select">
                                    <option value="low" <?php echo ($editing_announcement['priority'] ?? '') === 'low' ? 'selected' : ''; ?>>Low</option>
                                    <option value="normal" <?php echo ($editing_announcement['priority'] ?? 'normal') === 'normal' ? 'selected' : ''; ?>>Normal</option>
                                    <option value="high" <?php echo ($editing_announcement['priority'] ?? '') === 'high' ? 'selected' : ''; ?>>High</option>
                                    <option value="urgent" <?php echo ($editing_announcement['priority'] ?? '') === 'urgent' ? 'selected' : ''; ?>>Urgent</option>
                                </select>
                            </div>
                            
                            <div class="field">
                                <label for="status">Status</label>
                                <select id="status" name="status" class="select">
                                    <option value="draft" <?php echo ($editing_announcement['status'] ?? 'draft') === 'draft' ? 'selected' : ''; ?>>Draft</option>
                                    <option value="published" <?php echo ($editing_announcement['status'] ?? '') === 'published' ? 'selected' : ''; ?>>Published</option>
                                    <option value="archived" <?php echo ($editing_announcement['status'] ?? '') === 'archived' ? 'selected' : ''; ?>>Archived</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="field">
                            <label for="content" class="required">Content</label>
                            <textarea id="content" name="content" class="textarea" rows="6" required><?php echo htmlspecialchars($editing_announcement['content'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <i data-lucide="save"></i>
                                <?php echo $editing_announcement ? 'Update Announcement' : 'Create Announcement'; ?>
                            </button>
                            <?php if ($editing_announcement): ?>
                                <a href="./announcements.php" class="btn btn-secondary">
                                    <i data-lucide="x"></i>
                                    Cancel
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>

                <!-- Announcements Table -->
                <div class="table-card">
                    <div class="table-header">
                        <h3>All Announcements</h3>
                        <p>Manage and monitor all system announcements</p>
                    </div>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Priority</th>
                                    <th>Status</th>
                                    <th>Created By</th>
                                    <th>Created Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($announcements)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center">No announcements found.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($announcements as $announcement): ?>
                                        <tr>
                                            <td>
                                                <div class="announcement-info">
                                                    <div class="announcement-title"><?php echo htmlspecialchars($announcement['title']); ?></div>
                                                    <div class="announcement-preview"><?php echo htmlspecialchars(substr($announcement['content'], 0, 100)) . (strlen($announcement['content']) > 100 ? '...' : ''); ?></div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="priority-badge priority-<?php echo $announcement['priority']; ?>">
                                                    <?php echo ucfirst($announcement['priority']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="status-badge <?php echo $announcement['status']; ?>">
                                                    <?php echo ucfirst($announcement['status']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($announcement['creator_email'] ?? 'Unknown'); ?></td>
                                            <td><?php echo date('M j, Y g:i A', strtotime($announcement['created_at'])); ?></td>
                                            <td>
                                                <div class="action-buttons">
                                                    <a href="?edit=<?php echo $announcement['announcement_id']; ?>" class="btn-icon" title="Edit">
                                                        <i data-lucide="edit"></i>
                                                    </a>
                                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this announcement?');">
                                                        <input type="hidden" name="csrf_token" value="<?php echo SecurityHelper::generateCSRFToken(); ?>">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="id" value="<?php echo $announcement['announcement_id']; ?>">
                                                        <button type="submit" class="btn-icon btn-danger" title="Delete">
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

    <script>
        lucide.createIcons();
    </script>
</body>
</html>