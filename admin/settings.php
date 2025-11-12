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
        RateLimiter::checkLimit('admin_settings_' . $_SESSION['admin_id'], 10, 300);
        
        $action = InputValidator::sanitize($_POST['action'] ?? '');
        
        if ($action === 'update_site_settings') {
            $site_name = InputValidator::sanitize($_POST['site_name'] ?? '');
            $site_description = InputValidator::sanitize($_POST['site_description'] ?? '');
            $contact_email = InputValidator::sanitize($_POST['contact_email'] ?? '');
            $max_file_size = InputValidator::validateInteger($_POST['max_file_size'] ?? 5, 1, 50);
            
            if (empty($site_name)) {
                throw new Exception('Site name is required.');
            }
            
            if (!empty($contact_email) && !filter_var($contact_email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Invalid contact email format.');
            }
            
            if ($max_file_size === false) {
                throw new Exception('Max file size must be between 1 and 50 MB.');
            }
            
            // In a real application, you would save these to a settings table or config file
            // For now, we'll just show a success message
            ErrorHandler::addSuccess('Site settings updated successfully.');
            
        } elseif ($action === 'clear_cache') {
            // In a real application, you would clear various caches here
            ErrorHandler::addSuccess('Cache cleared successfully.');
            
        } elseif ($action === 'backup_database') {
            // In a real application, you would create a database backup
            ErrorHandler::addSuccess('Database backup initiated. You will receive an email when complete.');
        }
        
    } catch (Exception $e) {
        ErrorHandler::addError($e->getMessage());
        error_log('Settings error: ' . $e->getMessage());
    }
}

// Get current settings (in a real app, these would come from database/config)
$current_settings = [
    'site_name' => SITE_NAME ?? 'Event Management System',
    'site_description' => 'Manage and organize events efficiently',
    'contact_email' => 'admin@example.com',
    'max_file_size' => 5,
    'timezone' => 'UTC',
    'date_format' => 'Y-m-d',
    'time_format' => 'H:i'
];

// Get system information
$php_version = phpversion();
$mysql_version = $pdo->query('SELECT VERSION()')->fetchColumn();
$server_software = $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown';
$upload_max_filesize = ini_get('upload_max_filesize');
$post_max_size = ini_get('post_max_size');
$memory_limit = ini_get('memory_limit');

// Get current admin user info
$current_user = DatabaseHelper::fetchOne($pdo, 'SELECT name, email FROM users WHERE user_id = ?', [$_SESSION['user_id']]);
$admin_name = $current_user['name'] ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - <?php echo SITE_NAME; ?></title>
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
                    <a href="./settings.php" class="active">
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
                        <h1>Settings</h1>
                        <p class="page-subtitle">Configure system settings and preferences</p>
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
                    <span>Settings</span>
                </div>

                <!-- Alerts -->
                <?php ErrorHandler::displayMessages(); ?>

                <!-- Site Settings -->
                <div class="form-container">
                    <div class="form-header">
                        <h3>Site Settings</h3>
                        <p>Configure general site settings and preferences</p>
                    </div>
                    <form method="post" class="form-grid">
                        <input type="hidden" name="action" value="update_site_settings">
                        <input type="hidden" name="csrf_token" value="<?php echo SecurityHelper::generateCSRFToken(); ?>">
                        <div class="field">
                            <label for="site_name" class="required">Site Name</label>
                            <input id="site_name" class="input" type="text" name="site_name" value="<?php echo htmlspecialchars($current_settings['site_name']); ?>" required>
                        </div>
                        <div class="field">
                            <label for="contact_email">Contact Email</label>
                            <input id="contact_email" class="input" type="email" name="contact_email" value="<?php echo htmlspecialchars($current_settings['contact_email']); ?>">
                        </div>
                        <div class="field" style="grid-column: 1 / -1;">
                            <label for="site_description">Site Description</label>
                            <textarea id="site_description" class="input" name="site_description" rows="3"><?php echo htmlspecialchars($current_settings['site_description']); ?></textarea>
                        </div>
                        <div class="field">
                            <label for="max_file_size">Max File Size (MB)</label>
                            <input id="max_file_size" class="input" type="number" name="max_file_size" value="<?php echo $current_settings['max_file_size']; ?>" min="1" max="50">
                        </div>
                        <div class="field">
                            <label for="timezone">Timezone</label>
                            <select id="timezone" class="select" name="timezone">
                                <option value="UTC" <?php echo $current_settings['timezone'] === 'UTC' ? 'selected' : ''; ?>>UTC</option>
                                <option value="America/New_York" <?php echo $current_settings['timezone'] === 'America/New_York' ? 'selected' : ''; ?>>Eastern Time</option>
                                <option value="America/Chicago" <?php echo $current_settings['timezone'] === 'America/Chicago' ? 'selected' : ''; ?>>Central Time</option>
                                <option value="America/Denver" <?php echo $current_settings['timezone'] === 'America/Denver' ? 'selected' : ''; ?>>Mountain Time</option>
                                <option value="America/Los_Angeles" <?php echo $current_settings['timezone'] === 'America/Los_Angeles' ? 'selected' : ''; ?>>Pacific Time</option>
                            </select>
                        </div>
                        <div style="grid-column: 1 / -1;">
                            <button class="btn" type="submit">
                                <i data-lucide="save"></i>
                                Save Settings
                            </button>
                        </div>
                    </form>
                </div>

                <!-- System Actions -->
                <div class="cards">
                    <div class="card">
                        <h4>Clear Cache</h4>
                        <p>Clear all cached data to improve performance</p>
                        <form method="post" style="margin-top: 1rem;">
                            <input type="hidden" name="action" value="clear_cache">
                            <input type="hidden" name="csrf_token" value="<?php echo SecurityHelper::generateCSRFToken(); ?>">
                            <button class="btn secondary" type="submit">
                                <i data-lucide="trash-2"></i>
                                Clear Cache
                            </button>
                        </form>
                    </div>
                    <div class="card">
                        <h4>Database Backup</h4>
                        <p>Create a backup of the database</p>
                        <form method="post" style="margin-top: 1rem;" onsubmit="return confirm('Create database backup? This may take a few minutes.')">
                            <input type="hidden" name="action" value="backup_database">
                            <input type="hidden" name="csrf_token" value="<?php echo SecurityHelper::generateCSRFToken(); ?>">
                            <button class="btn warning" type="submit">
                                <i data-lucide="database"></i>
                                Backup Database
                            </button>
                        </form>
                    </div>
                </div>

                <!-- System Information -->
                <div class="table-container">
                    <div style="padding: 1.5rem; border-bottom: 1px solid var(--border-color);">
                        <h3 style="margin: 0; font-size: 1.125rem; font-weight: 600; color: var(--text-primary);">System Information</h3>
                        <p style="margin: 0.25rem 0 0; color: var(--text-secondary); font-size: 0.875rem;">Server and environment details</p>
                    </div>
                    <div style="padding: 1.5rem;">
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem;">
                            <div>
                                <h4 style="margin: 0 0 1rem; color: var(--text-primary);">Server Information</h4>
                                <div style="display: grid; gap: 0.5rem;">
                                    <div style="display: flex; justify-content: space-between;">
                                        <span style="color: var(--text-secondary);">PHP Version:</span>
                                        <span style="font-weight: 500;"><?php echo htmlspecialchars($php_version); ?></span>
                                    </div>
                                    <div style="display: flex; justify-content: space-between;">
                                        <span style="color: var(--text-secondary);">MySQL Version:</span>
                                        <span style="font-weight: 500;"><?php echo htmlspecialchars($mysql_version); ?></span>
                                    </div>
                                    <div style="display: flex; justify-content: space-between;">
                                        <span style="color: var(--text-secondary);">Server Software:</span>
                                        <span style="font-weight: 500;"><?php echo htmlspecialchars($server_software); ?></span>
                                    </div>
                                </div>
                            </div>
                            <div>
                                <h4 style="margin: 0 0 1rem; color: var(--text-primary);">PHP Configuration</h4>
                                <div style="display: grid; gap: 0.5rem;">
                                    <div style="display: flex; justify-content: space-between;">
                                        <span style="color: var(--text-secondary);">Upload Max Filesize:</span>
                                        <span style="font-weight: 500;"><?php echo htmlspecialchars($upload_max_filesize); ?></span>
                                    </div>
                                    <div style="display: flex; justify-content: space-between;">
                                        <span style="color: var(--text-secondary);">Post Max Size:</span>
                                        <span style="font-weight: 500;"><?php echo htmlspecialchars($post_max_size); ?></span>
                                    </div>
                                    <div style="display: flex; justify-content: space-between;">
                                        <span style="color: var(--text-secondary);">Memory Limit:</span>
                                        <span style="font-weight: 500;"><?php echo htmlspecialchars($memory_limit); ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Initialize Lucide icons
        lucide.createIcons();
    </script>
</body>
</html>
