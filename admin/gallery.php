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

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // CSRF Protection
        if (!SecurityHelper::validateCSRFToken($_POST['csrf_token'] ?? '')) {
            throw new Exception('Invalid security token. Please try again.');
        }
        
        // Rate limiting
        RateLimiter::checkLimit('admin_gallery_' . $_SESSION['admin_id'], 15, 300);
        
        $action = InputValidator::sanitize($_POST['action'] ?? '');

        if ($action === 'update_caption') {
            $media_id = InputValidator::validateInteger($_POST['media_id'] ?? 0, 1);
            $caption = InputValidator::sanitize($_POST['caption'] ?? '');

            if ($media_id === false) {
                throw new Exception('Invalid media ID.');
            }
            
            $query = 'UPDATE mediagallery SET caption = ? WHERE media_id = ?';
            $params = [$caption, $media_id];
            
            if (DatabaseHelper::execute($query, $params)) {
                ErrorHandler::addSuccess('Media caption updated successfully.');
            } else {
                throw new Exception('Failed to update media caption.');
            }
            
        } elseif ($action === 'update_status') {
            $media_id = InputValidator::validateInteger($_POST['media_id'] ?? 0, 1);
            $status = InputValidator::sanitize($_POST['status'] ?? '');

            if ($media_id === false) {
                throw new Exception('Invalid media ID.');
            }
            
            if (!in_array($status, ['active', 'inactive'])) {
                throw new Exception('Invalid status value.');
            }
            
            $query = 'UPDATE mediagallery SET status = ? WHERE media_id = ?';
            $params = [$status, $media_id];
            
            if (DatabaseHelper::execute($query, $params)) {
                ErrorHandler::addSuccess('Media status updated successfully.');
            } else {
                throw new Exception('Failed to update media status.');
            }
            
        } elseif ($action === 'delete') {
            $media_id = InputValidator::validateInteger($_POST['media_id'] ?? 0, 1);
            
            if ($media_id === false) {
                throw new Exception('Invalid media ID.');
            }
            
            // Get file path before deletion
            $media = DatabaseHelper::fetchOne($pdo, 'SELECT file_url FROM mediagallery WHERE media_id = ?', [$media_id]);
            
            if ($media && $media['file_url']) {
                $file_path = '../' . $media['file_url'];
                if (file_exists($file_path)) {
                    unlink($file_path);
                }
            }
            
            $query = 'DELETE FROM mediagallery WHERE media_id = ?';
            $params = [$media_id];
            
            if (DatabaseHelper::execute($query, $params)) {
                ErrorHandler::addSuccess('Media deleted successfully.');
            } else {
                throw new Exception('Failed to delete media.');
            }
        }
        
    } catch (Exception $e) {
        ErrorHandler::addError($e->getMessage());
        error_log('Gallery error: ' . $e->getMessage());
    }
}

// Get filter parameters with validation
$event_id = InputValidator::validateInteger($_GET['event_id'] ?? 0, 0);
$type_filter = InputValidator::sanitize($_GET['type'] ?? '');
$status_filter = InputValidator::sanitize($_GET['status'] ?? '');

// Build query with positional parameters
$where_conditions = [];
$params = [];

if ($event_id > 0) {
    $where_conditions[] = 'm.event_id = ?';
    $params[] = $event_id;
}

if ($type_filter && in_array($type_filter, ['image', 'video'])) {
    $where_conditions[] = 'm.file_type = ?';
    $params[] = $type_filter;
}

if ($status_filter && in_array($status_filter, ['active', 'inactive'])) {
    $where_conditions[] = 'm.status = ?';
    $params[] = $status_filter;
}

$where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get media with event and user info
$query = "
    SELECT 
        m.*,
        e.title as event_title,
        u.email as uploader_email
    FROM mediagallery m
    LEFT JOIN events e ON m.event_id = e.event_id
    LEFT JOIN users u ON m.uploaded_by = u.user_id
    $where_clause
    ORDER BY m.uploaded_on DESC
";

try {
    $media_items = DatabaseHelper::fetchAll($pdo, $query, $params);
} catch (Exception $e) {
    ErrorHandler::addError('Failed to fetch media items.');
    error_log('Gallery fetch error: ' . $e->getMessage());
    $media_items = [];
}

// Get statistics
try {
    $total_media = DatabaseHelper::fetchOne($pdo, 'SELECT COUNT(*) as count FROM mediagallery')['count'] ?? 0;
    $total_images = DatabaseHelper::fetchOne($pdo, "SELECT COUNT(*) as count FROM mediagallery WHERE file_type = 'image'")['count'] ?? 0;
    $total_videos = DatabaseHelper::fetchOne($pdo, "SELECT COUNT(*) as count FROM mediagallery WHERE file_type = 'video'")['count'] ?? 0;
    $recent_uploads = DatabaseHelper::fetchOne($pdo, "SELECT COUNT(*) as count FROM mediagallery WHERE uploaded_on >= DATE_SUB(NOW(), INTERVAL 7 DAY)")['count'] ?? 0;
} catch (Exception $e) {
    ErrorHandler::addError('Failed to fetch statistics.');
    error_log('Gallery stats error: ' . $e->getMessage());
    $total_media = $total_images = $total_videos = $recent_uploads = 0;
}

// Get events for filter dropdown
try {
    $events = DatabaseHelper::fetchAll($pdo, 'SELECT event_id, title FROM events ORDER BY title');
} catch (Exception $e) {
    ErrorHandler::addError('Failed to fetch events.');
    error_log('Gallery events error: ' . $e->getMessage());
    $events = [];
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
    <title>Gallery Management - <?php echo SITE_NAME; ?></title>
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
                    <a href="./gallery.php" class="active">
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
                        <h1>Gallery Management</h1>
                        <p class="page-subtitle">Manage media uploads and gallery content</p>
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
                    <span>Gallery</span>
                </div>

                <!-- Page Header -->
                <div class="page-header">
                    <div>
                        <h2>Media Gallery</h2>
                        <p>Manage and organize media files for events</p>
                    </div>
                    <div class="page-actions">
                        <a href="./add-media.php" class="btn">
                            <i data-lucide="plus"></i>
                            Add Media
                        </a>
                    </div>
                </div>

                <!-- Alerts -->
                <?php echo ErrorHandler::displayMessages(); ?>

                <!-- Statistics Cards -->
                <div class="cards">
                    <div class="card">
                        <h4>Total Media</h4>
                        <div class="value"><?php echo number_format($total_media); ?></div>
                        <div class="description">All media files</div>
                    </div>
                    <div class="card">
                        <h4>Images</h4>
                        <div class="value"><?php echo number_format($total_images); ?></div>
                        <div class="description">Photo uploads</div>
                    </div>
                    <div class="card">
                        <h4>Videos</h4>
                        <div class="value"><?php echo number_format($total_videos); ?></div>
                        <div class="description">Video uploads</div>
                    </div>
                    <div class="card">
                        <h4>Recent Uploads</h4>
                        <div class="value"><?php echo number_format($recent_uploads); ?></div>
                        <div class="description">Last 7 days</div>
                    </div>
                </div>

                <!-- Search and Filters -->
                <div class="search-container">
                    <form method="GET" class="search-grid">
                        <div style="display: flex; gap: 1rem; width: 100%;">
                            <select name="event_id" class="select">
                                <option value="">All Events</option>
                                <?php foreach ($events as $event): ?>
                                    <option value="<?php echo $event['event_id']; ?>" <?php echo $event_id == $event['event_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($event['title']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <select name="type" class="select">
                                <option value="">All Types</option>
                                <option value="image" <?php echo $type_filter === 'image' ? 'selected' : ''; ?>>Images</option>
                                <option value="video" <?php echo $type_filter === 'video' ? 'selected' : ''; ?>>Videos</option>
                            </select>
                            <select name="status" class="select">
                                <option value="">All Status</option>
                                <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                            <button type="submit" class="btn">
                                <i data-lucide="filter"></i>
                                Filter
                            </button>
                            <a href="./gallery.php" class="btn secondary">
                                <i data-lucide="x"></i>
                                Clear
                            </a>
                        </div>
                    </form>
                </div>

                <!-- Media Gallery Grid -->
                <div class="table-container">
                    <div style="padding: 1.5rem; border-bottom: 1px solid var(--border-color);">
                        <h3 style="margin: 0; font-size: 1.125rem; font-weight: 600; color: var(--text-primary);">Media Gallery</h3>
                        <p style="margin: 0.25rem 0 0; color: var(--text-secondary); font-size: 0.875rem;">Manage uploaded media files</p>
                    </div>

                    <?php if (empty($media_items)): ?>
                        <div style="padding: 3rem; text-align: center;">
                            <i data-lucide="image" style="width: 48px; height: 48px; color: var(--text-muted); margin-bottom: 1rem;"></i>
                            <h3 style="color: var(--text-muted); margin-bottom: 0.5rem;">No media found</h3>
                            <p style="color: var(--text-secondary);">No media files match your current filters.</p>
                        </div>
                    <?php else: ?>
                        <div style="padding: 1.5rem;">
                            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 1.5rem;">
                                <?php foreach ($media_items as $media): ?>
                                    <div class="card" style="padding: 1rem;">
                                        <div style="position: relative; margin-bottom: 1rem;">
                                            <?php if ($media['file_type'] === 'image'): ?>
                                                <img src="../<?php echo htmlspecialchars($media['file_url']); ?>"
                                                     alt="<?php echo htmlspecialchars($media['caption'] ?: 'Gallery image'); ?>"
                                                     style="width: 100%; height: 200px; object-fit: cover; border-radius: var(--radius-md);">
                                            <?php else: ?>
                                                <div style="width: 100%; height: 200px; background: var(--bg-tertiary); border-radius: var(--radius-md); display: flex; align-items: center; justify-content: center;">
                                                    <i data-lucide="play-circle" style="width: 48px; height: 48px; color: var(--text-muted);"></i>
                                                </div>
                                            <?php endif; ?>

                                            <span class="badge success" style="position: absolute; top: 0.5rem; right: 0.5rem;">
                                                <?php echo ucfirst($media['file_type']); ?>
                                            </span>
                                        </div>

                                        <div style="margin-bottom: 1rem;">
                                            <h4 style="margin: 0 0 0.5rem; font-size: 1rem; font-weight: 600;">
                                                <?php echo htmlspecialchars($media['caption'] ?: 'Untitled'); ?>
                                            </h4>
                                            <div style="font-size: 0.875rem; color: var(--text-secondary); margin-bottom: 0.5rem;">
                                                <strong>Event:</strong> <?php echo htmlspecialchars($media['event_title'] ?: 'No event'); ?>
                                            </div>
                                            <div style="font-size: 0.875rem; color: var(--text-secondary); margin-bottom: 0.5rem;">
                                                <strong>Uploaded by:</strong> <?php echo htmlspecialchars($media['uploader_email'] ?: 'Unknown'); ?>
                                            </div>
                                            <div style="font-size: 0.875rem; color: var(--text-secondary); margin-bottom: 0.5rem;">
                                                <strong>Date:</strong> <?php echo date('M j, Y g:i A', strtotime($media['uploaded_on'])); ?>
                                            </div>
                                            <div style="font-size: 0.875rem; color: var(--text-secondary);">
                                                <strong>Status:</strong> 
                                                <span class="badge <?php echo $media['status'] === 'active' ? 'success' : 'warning'; ?>">
                                                    <?php echo ucfirst($media['status']); ?>
                                                </span>
                                            </div>
                                        </div>

                                        <div style="margin-bottom: 1rem;">
                                            <!-- Caption Edit Form -->
                                            <form method="POST" style="display: flex; gap: 0.5rem; margin-bottom: 0.5rem;">
                                                <input type="hidden" name="action" value="update_caption">
                                                <input type="hidden" name="media_id" value="<?php echo $media['media_id']; ?>">
                                                <input type="hidden" name="csrf_token" value="<?php echo SecurityHelper::generateCSRFToken(); ?>">
                                                <input type="text" name="caption" value="<?php echo htmlspecialchars($media['caption'] ?: ''); ?>"
                                                       placeholder="Add caption..." class="input" style="flex: 1; font-size: 0.875rem;">
                                                <button type="submit" class="btn sm" title="Update Caption">
                                                    <i data-lucide="save"></i>
                                                </button>
                                            </form>
                                            
                                            <!-- Status Update Form -->
                                            <form method="POST" style="display: flex; gap: 0.5rem;">
                                                <input type="hidden" name="action" value="update_status">
                                                <input type="hidden" name="media_id" value="<?php echo $media['media_id']; ?>">
                                                <input type="hidden" name="csrf_token" value="<?php echo SecurityHelper::generateCSRFToken(); ?>">
                                                <select name="status" class="select" style="flex: 1; font-size: 0.875rem;">
                                                    <option value="active" <?php echo $media['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                                    <option value="inactive" <?php echo $media['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                                </select>
                                                <button type="submit" class="btn sm" title="Update Status">
                                                    <i data-lucide="refresh-cw"></i>
                                                </button>
                                            </form>
                                        </div>

                                        <div style="display: flex; gap: 0.5rem; justify-content: space-between;">
                                            <!-- View Full Size -->
                                            <a href="../<?php echo htmlspecialchars($media['file_url']); ?>" target="_blank" class="btn ghost sm" title="View Full Size">
                                                <i data-lucide="external-link"></i>
                                                View
                                            </a>

                                            <!-- Delete Media -->
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this media? This action cannot be undone.')">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="media_id" value="<?php echo $media['media_id']; ?>">
                                                <input type="hidden" name="csrf_token" value="<?php echo SecurityHelper::generateCSRFToken(); ?>">
                                                <button type="submit" class="btn danger sm" title="Delete Media">
                                                    <i data-lucide="trash-2"></i>
                                                    Delete
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Initialize Lucide icons
        lucide.createIcons();

        // Auto-submit status changes with confirmation
        document.addEventListener('DOMContentLoaded', function() {
            const statusSelects = document.querySelectorAll('select[name="status"]');
            statusSelects.forEach(select => {
                select.addEventListener('change', function(e) {
                    const newStatus = this.value;

                    if (newStatus === 'rejected') {
                        if (!confirm('Are you sure you want to reject this media?')) {
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
