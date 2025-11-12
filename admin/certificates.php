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
        RateLimiter::checkLimit('admin_certificates_' . $_SESSION['admin_id'], 15, 300);
        
        $action = InputValidator::sanitize($_POST['action'] ?? '');
        
        if ($action === 'generate_certificate') {
            $certificate_id = InputValidator::validateInteger($_POST['certificate_id'] ?? 0, 1);
            
            if ($certificate_id === false) {
                throw new Exception('Invalid certificate ID.');
            }
            
            $query = 'UPDATE certificates SET status = "issued", issued_date = NOW() WHERE certificate_id = ?';
            
            if (DatabaseHelper::execute($query, [$certificate_id])) {
                ErrorHandler::addSuccess('Certificate generated successfully.');
            } else {
                throw new Exception('Failed to generate certificate.');
            }
            
        } elseif ($action === 'bulk_generate') {
            $event_id = InputValidator::validateInteger($_POST['event_id'] ?? 0, 1);
            
            if ($event_id === false) {
                throw new Exception('Invalid event ID.');
            }
            
            // Generate certificates for all registered participants of an event
            $query = '
                INSERT INTO certificates (event_id, student_id, certificate_type, status, created_at)
                SELECT r.event_id, r.id, "Participation", "pending", NOW()
                FROM registration r
                LEFT JOIN certificates c ON r.event_id = c.event_id AND r.id = c.student_id
                WHERE r.event_id = ? AND c.certificate_id IS NULL
            ';
            
            if (DatabaseHelper::execute($query, [$event_id])) {
                ErrorHandler::addSuccess('Certificates created for all participants.');
            } else {
                throw new Exception('Failed to create certificates.');
            }
            
        } elseif ($action === 'delete_certificate') {
            $certificate_id = InputValidator::validateInteger($_POST['certificate_id'] ?? 0, 1);
            
            if ($certificate_id === false) {
                throw new Exception('Invalid certificate ID.');
            }
            
            $query = 'DELETE FROM certificates WHERE certificate_id = ?';
            
            if (DatabaseHelper::execute($query, [$certificate_id])) {
                ErrorHandler::addSuccess('Certificate deleted successfully.');
            } else {
                throw new Exception('Failed to delete certificate.');
            }
        }
        
    } catch (Exception $e) {
        ErrorHandler::addError($e->getMessage());
        error_log('Certificates error: ' . $e->getMessage());
    }
}

// Get filter parameters
$status_filter = InputValidator::sanitize($_GET['status'] ?? '');
$event_filter = InputValidator::validateInteger($_GET['event'] ?? '', 1);
$type_filter = InputValidator::sanitize($_GET['type'] ?? '');
$search = InputValidator::sanitize($_GET['search'] ?? '');

// Build query
$where_conditions = [];
$params = [];

if ($status_filter && in_array($status_filter, ['pending', 'issued', 'revoked'])) {
    $where_conditions[] = 'c.status = ?';
    $params[] = $status_filter;
}

if ($event_filter !== false) {
    $where_conditions[] = 'c.event_id = ?';
    $params[] = $event_filter;
}

if ($type_filter && in_array($type_filter, ['Participation', 'Achievement', 'Completion'])) {
    $where_conditions[] = 'c.certificate_type = ?';
    $params[] = $type_filter;
}

if ($search) {
    $where_conditions[] = '(e.title LIKE ? OR ud.full_name LIKE ? OR u.email LIKE ?)';
    $search_param = '%' . $search . '%';
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get certificate data from database
try {
    $query = "
        SELECT 
            c.certificate_id,
            c.certificate_type,
            c.status,
            c.issued_date,
            c.created_at,
            e.title as event_name,
            COALESCE(ud.full_name, u.email) as participant_name,
            u.email as participant_email
        FROM certificates c
        LEFT JOIN events e ON c.event_id = e.event_id
        LEFT JOIN users u ON c.student_id = u.user_id
        LEFT JOIN userdetails ud ON c.student_id = ud.user_id
        $where_clause
        ORDER BY c.created_at DESC
    ";
    
    $certificates = DatabaseHelper::fetchAll($pdo, $query, $params);
    if (empty($certificates)) {
        $certificates = [];
    }
} catch (Exception $e) {
    ErrorHandler::addError('Failed to load certificates.');
    error_log('Certificates query error: ' . $e->getMessage());
    $certificates = [];
}

// Get statistics
$total_certificates = count($certificates);
$issued_certificates = count(array_filter($certificates, fn($c) => $c['status'] === 'issued'));
$pending_certificates = count(array_filter($certificates, fn($c) => $c['status'] === 'pending'));

// Get events for dropdown
try {
    $events = DatabaseHelper::fetchAll($pdo, 'SELECT event_id, title FROM events ORDER BY title');
    if (empty($events)) {
        $events = [];
    }
} catch (Exception $e) {
    ErrorHandler::addError('Failed to load events.');
    error_log('Events query error: ' . $e->getMessage());
    $events = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificates Management - <?php echo SITE_NAME; ?></title>
    <link rel="icon" type="image/png" href="../assets/images/dot.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="./admin.css">
    <style>
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            align-items: center;
            justify-content: center;
        }
        .modal-content {
            background-color: white;
            border-radius: 8px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .modal-header {
            padding: 20px;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .modal-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #6b7280;
        }
        .modal-body {
            padding: 20px;
        }
        .modal-footer {
            padding: 20px;
            border-top: 1px solid #e5e7eb;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        .form-group select {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            font-size: 14px;
        }
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #6b7280;
        }
        .empty-icon {
            width: 48px;
            height: 48px;
            margin-bottom: 16px;
            opacity: 0.5;
        }
        .btn-icon.danger {
            color: #dc2626;
        }
        .btn-icon.danger:hover {
            background-color: #fef2f2;
        }
    </style>
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
                    <a href="./certificates.php" class="active">
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
                    <h1>Certificates Management</h1>
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
                    <span>Certificates</span>
                </div>
                
                <?php ErrorHandler::displayMessages(); ?>

                <!-- Action Bar -->
                <div class="action-bar">
                    <button class="btn btn-primary" onclick="showBulkGenerateModal()">
                        <i data-lucide="plus"></i>
                        Generate Certificates
                    </button>
                    <button class="btn btn-secondary">
                        <i data-lucide="download"></i>
                        Export List
                    </button>
                    <button class="btn btn-secondary">
                        <i data-lucide="settings"></i>
                        Certificate Templates
                    </button>
                </div>
                
                <!-- Filters -->
                <div class="filters-card">
                    <form method="GET" class="filters-form">
                        <div class="filter-group">
                            <label for="status">Status:</label>
                            <select name="status" id="status">
                                <option value="">All Statuses</option>
                                <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="issued" <?php echo $status_filter === 'issued' ? 'selected' : ''; ?>>Issued</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label for="event">Event:</label>
                            <select name="event" id="event">
                                <option value="">All Events</option>
                                <?php foreach ($events as $event): ?>
                                    <option value="<?php echo $event['event_id']; ?>" <?php echo $event_filter == $event['event_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($event['title']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label for="type">Type:</label>
                            <select name="type" id="type">
                                <option value="">All Types</option>
                                <option value="Participation" <?php echo $type_filter === 'Participation' ? 'selected' : ''; ?>>Participation</option>
                                <option value="Completion" <?php echo $type_filter === 'Completion' ? 'selected' : ''; ?>>Completion</option>
                                <option value="Achievement" <?php echo $type_filter === 'Achievement' ? 'selected' : ''; ?>>Achievement</option>
                                <option value="Winner" <?php echo $type_filter === 'Winner' ? 'selected' : ''; ?>>Winner</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label for="search">Search:</label>
                            <input type="text" name="search" id="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search events, participants...">
                        </div>
                        <button type="submit" class="btn btn-primary">Filter</button>
                        <a href="certificates.php" class="btn btn-secondary">Clear</a>
                    </form>
                </div>

                <!-- Statistics Cards -->
                <div class="stats-grid">
                    <div class="stat-card success">
                        <div class="stat-icon">
                            <i data-lucide="award"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-number"><?php echo $total_certificates; ?></div>
                            <div class="stat-label">Total Certificates</div>
                            <div class="stat-change positive">All time</div>
                        </div>
                    </div>
                    
                    <div class="stat-card info">
                        <div class="stat-icon">
                            <i data-lucide="check-circle"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-number"><?php echo $issued_certificates; ?></div>
                            <div class="stat-label">Issued</div>
                            <div class="stat-change positive">Ready for download</div>
                        </div>
                    </div>
                    
                    <div class="stat-card warning">
                        <div class="stat-icon">
                            <i data-lucide="clock"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-number"><?php echo $pending_certificates; ?></div>
                            <div class="stat-label">Pending</div>
                            <div class="stat-change neutral">Awaiting generation</div>
                        </div>
                    </div>
                    
                    <div class="stat-card primary">
                        <div class="stat-icon">
                            <i data-lucide="users"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-number"><?php echo count($events); ?></div>
                            <div class="stat-label">Events</div>
                            <div class="stat-change positive">With certificates</div>
                        </div>
                    </div>
                </div>

                <!-- Certificates Table -->
                <div class="table-card">
                    <div class="table-header">
                        <h3>Certificate Records</h3>
                        <p>Manage and track certificate generation and distribution</p>
                    </div>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Event</th>
                                    <th>Participant</th>
                                    <th>Type</th>
                                    <th>Issue Date</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($certificates)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4">
                                        <div class="empty-state">
                                            <i data-lucide="award" class="empty-icon"></i>
                                            <p>No certificates found</p>
                                            <small class="text-muted">Certificates will appear here once generated</small>
                                        </div>
                                    </td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach ($certificates as $cert): ?>
                                    <tr>
                                        <td>
                                            <div class="event-info">
                                                <div class="event-title"><?php echo htmlspecialchars($cert['event_name']); ?></div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="participant-info">
                                                <div class="participant-name"><?php echo htmlspecialchars($cert['participant_name']); ?></div>
                                                <div class="participant-email"><?php echo htmlspecialchars($cert['participant_email']); ?></div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="certificate-type <?php echo strtolower($cert['certificate_type']); ?>">
                                                <?php echo $cert['certificate_type']; ?>
                                            </span>
                                        </td>
                                        <td><?php echo $cert['issued_date'] ? date('M j, Y', strtotime($cert['issued_date'])) : '-'; ?></td>
                                        <td>
                                            <span class="status-badge <?php echo $cert['status']; ?>">
                                                <?php echo ucfirst($cert['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <?php if ($cert['status'] === 'issued'): ?>
                                                    <form method="post" style="display: inline;">
                                                        <?php echo SecurityHelper::generateCSRFToken(); ?>
                                                        <input type="hidden" name="action" value="download">
                                                        <input type="hidden" name="certificate_id" value="<?php echo $cert['certificate_id']; ?>">
                                                        <button type="submit" class="btn-icon" title="Download Certificate">
                                                            <i data-lucide="download"></i>
                                                        </button>
                                                    </form>
                                                    <form method="post" style="display: inline;">
                                                        <?php echo SecurityHelper::generateCSRFToken(); ?>
                                                        <input type="hidden" name="action" value="send_email">
                                                        <input type="hidden" name="certificate_id" value="<?php echo $cert['certificate_id']; ?>">
                                                        <button type="submit" class="btn-icon" title="Send Email">
                                                            <i data-lucide="mail"></i>
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <form method="post" style="display: inline;">
                                                        <?php echo SecurityHelper::generateCSRFToken(); ?>
                                                        <input type="hidden" name="action" value="generate_certificate">
                                                        <input type="hidden" name="certificate_id" value="<?php echo $cert['certificate_id']; ?>">
                                                        <button type="submit" class="btn-icon success" title="Generate Certificate">
                                                            <i data-lucide="file-plus"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                                <button class="btn-icon" title="View Details" onclick="viewCertificate(<?php echo $cert['certificate_id']; ?>)">
                                                    <i data-lucide="eye"></i>
                                                </button>
                                                <form method="post" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this certificate?')">
                                                    <?php echo SecurityHelper::generateCSRFToken(); ?>
                                                    <input type="hidden" name="action" value="delete_certificate">
                                                    <input type="hidden" name="certificate_id" value="<?php echo $cert['certificate_id']; ?>">
                                                    <button type="submit" class="btn-icon danger" title="Delete Certificate">
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

    <!-- Bulk Generate Modal -->
    <div id="bulkGenerateModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Generate Certificates</h3>
                <button class="modal-close" onclick="closeBulkGenerateModal()">&times;</button>
            </div>
            <form method="post">
                <?php echo SecurityHelper::generateCSRFToken(); ?>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="bulk_event_id">Select Event:</label>
                        <select name="event_id" id="bulk_event_id" required>
                            <option value="">Choose an event...</option>
                            <?php foreach ($events as $event): ?>
                                <option value="<?php echo $event['event_id']; ?>">
                                    <?php echo htmlspecialchars($event['title']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <input type="hidden" name="action" value="bulk_generate">
                    <button type="button" class="btn btn-secondary" onclick="closeBulkGenerateModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Generate Certificates</button>
                </div>
            </form>
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

        function showBulkGenerateModal() {
            document.getElementById('bulkGenerateModal').style.display = 'flex';
        }

        function closeBulkGenerateModal() {
            document.getElementById('bulkGenerateModal').style.display = 'none';
        }

        function viewCertificate(certificateId) {
            alert('Certificate details for ID: ' + certificateId);
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('bulkGenerateModal');
            if (event.target === modal) {
                closeBulkGenerateModal();
            }
        }
    </script>
</body>
</html>
