<?php
// QR Code functionality is now handled by QRCodeGenerator class

require_once '../config/config.php';
require_once '../config/database.php';
require_once './_auth.php';
require_once './_error_handler.php';
require_once '../includes/qr_code_generator.php';
require_once '../includes/EmailNotification.php';

admin_require_login();

$db = new Database();
$pdo = $db->getConnection();

// Initialize email notification system
$emailNotifier = new EmailNotification();

$success = '';
$error = '';

// Handle approval actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $registration_id = (int)$_POST['registration_id'];
    $rejection_reason = $_POST['rejection_reason'] ?? '';
    
    try {
        if ($action === 'approve') {
            // Get registration data for QR code generation
            $stmt = $pdo->prepare("
                SELECT r.*, e.title as event_title, e.event_date, e.event_time, e.venue
                FROM registration r
                JOIN events e ON r.event_id = e.event_id
                WHERE r.id = ?
            ");
            $stmt->execute([$registration_id]);
            $registration_data = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$registration_data) {
                throw new Exception('Registration not found');
            }

            // Generate QR code using our enhanced system
            $qr_result = QRCodeGenerator::generateRegistrationQR($registration_data);

            if ($qr_result['success']) {
                // Update registration with approval and QR code
                $stmt = $pdo->prepare("
                    UPDATE registration
                    SET status = 'approved',
                        approved_at = NOW(),
                        qr_code = :qr_code
                    WHERE id = :registration_id
                ");
                $stmt->execute([
                    ':registration_id' => $registration_id,
                    ':qr_code' => $qr_result['qr_code_data']
                ]);

                $success = 'Registration approved successfully! QR code generated for attendance tracking.';
                error_log("QR Code generated for registration ID: $registration_id");

                // Send email notification for approval using EmailService
                try {
                    require_once '../includes/EmailService.php';
                    $emailService = new EmailService();

                    $userData = [
                        'name' => $registration_data['student_name'],
                        'email' => $registration_data['student_email'],
                        'event_name' => $registration_data['event_title']
                    ];

                    $approval_data = [
                        'approval_date' => date('Y-m-d H:i:s'),
                        'approved_by' => $_SESSION['user_email'] ?? 'Admin',
                        'approval_id' => 'APR-' . $registration_id,
                        'qr_code_status' => 'Generated Successfully'
                    ];

                    $emailService->sendApprovalConfirmation($userData, $approval_data);
                } catch (Exception $email_error) {
                    error_log("Email notification failed for approval: " . $email_error->getMessage());
                }
            } else {
                // Approve without QR code if generation fails
                $stmt = $pdo->prepare("
                    UPDATE registration
                    SET status = 'approved',
                        approved_at = NOW()
                    WHERE id = :registration_id
                ");
                $stmt->execute([':registration_id' => $registration_id]);

                $success = 'Registration approved successfully! (QR code generation failed: ' . $qr_result['error'] . ')';
                error_log("QR Code generation failed for registration ID: $registration_id - " . $qr_result['error']);

                // Send email notification for approval (even if QR failed)
                try {
                    require_once '../includes/EmailService.php';
                    $emailService = new EmailService();

                    $userData = [
                        'name' => $registration_data['student_name'],
                        'email' => $registration_data['student_email'],
                        'event_name' => $registration_data['event_title']
                    ];

                    $approval_data = [
                        'approval_date' => date('Y-m-d H:i:s'),
                        'approved_by' => $_SESSION['user_email'] ?? 'Admin',
                        'approval_id' => 'APR-' . $registration_id,
                        'qr_code_status' => 'Generation Failed: ' . $qr_result['error']
                    ];

                    $emailService->sendApprovalConfirmation($userData, $approval_data);
                } catch (Exception $email_error) {
                    error_log("Email notification failed for approval: " . $email_error->getMessage());
                }
            }
        } elseif ($action === 'reject') {
            $stmt = $pdo->prepare("
                UPDATE registration 
                SET status = 'rejected', 
                    approved_at = NOW(),
                    rejection_reason = :rejection_reason
                WHERE id = :registration_id
            ");
            $stmt->execute([
                ':rejection_reason' => $rejection_reason,
                ':registration_id' => $registration_id
            ]);
            $success = 'Registration rejected.';

            // Send email notification for rejection
            try {
                // Get registration data for rejection notification
                $stmt = $pdo->prepare("
                    SELECT r.*, e.title as event_title, e.event_date, e.event_time, e.venue
                    FROM registration r
                    JOIN events e ON r.event_id = e.event_id
                    WHERE r.id = ?
                ");
                $stmt->execute([$registration_id]);
                $registration_data = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($registration_data) {
                    $rejection_data = [
                        'Registration ID' => $registration_data['id'],
                        'Student Name' => $registration_data['student_name'],
                        'Student ID' => $registration_data['student_id'],
                        'Student Email' => $registration_data['student_email'],
                        'Event Name' => $registration_data['event_title'],
                        'Event Date' => $registration_data['event_date'],
                        'Rejected By' => $_SESSION['user_email'] ?? 'Admin',
                        'Rejection Time' => date('Y-m-d H:i:s'),
                        'Rejection Reason' => $rejection_reason ?: 'No reason provided',
                        'Status' => 'Rejected'
                    ];

                    $subject = 'Registration Rejected';
                    $message = 'A registration has been rejected by admin.';
                    $emailNotifier->sendAdminNotification($subject, $message, $rejection_data);
                }
            } catch (Exception $email_error) {
                error_log("Email notification failed for rejection: " . $email_error->getMessage());
            }
        }
    } catch (Exception $e) {
        $error = 'Error processing request: ' . $e->getMessage();
    }
}

// Get pending registrations
$stmt = $pdo->query("
    SELECT r.*, e.title as event_title, e.event_time, e.venue,
           r.student_name, r.student_email
    FROM registration r
    JOIN events e ON r.event_id = e.event_id
    WHERE r.status IN ('pending', 'waitlist_pending')
    ORDER BY r.registered_on DESC
");
$pending_registrations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get approved registrations with QR codes for download section
$stmt = $pdo->query("
    SELECT r.*, e.title as event_title, e.event_date, e.event_time, e.venue,
           r.student_name, r.student_email
    FROM registration r
    JOIN events e ON r.event_id = e.event_id
    WHERE r.status = 'approved' AND r.qr_code IS NOT NULL
    ORDER BY r.approved_at DESC
    LIMIT 20
");
$approved_registrations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stats = [
    'pending' => count(array_filter($pending_registrations, fn($r) => $r['status'] === 'pending')),
    'waitlist_pending' => count(array_filter($pending_registrations, fn($r) => $r['status'] === 'waitlist_pending')),
    'total_pending' => count($pending_registrations)
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration Approvals</title>
    <link rel="icon" type="image/png" href="../assets/images/dot.png">
    <link rel="stylesheet" href="./admin.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <script src="js/notification_checker.js"></script>
</head>
<body class="registration-approvals">
    <div class="admin-layout">
        <!-- Sidebar -->
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
                    <a href="./registration-approvals.php" class="active">
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
                        <h1>Registration Approvals</h1>
                        <p class="page-subtitle">Review and approve event registrations</p>
                    </div>
                </div>
                <div class="topbar-right">
                    <div class="user-welcome">
                        <div class="welcome-text">
                            <span class="greeting">Welcome back,</span>
                            <span class="user-name">Admin User</span>
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
                    <span>Registration Approvals</span>
                </div>

                <!-- Alerts -->
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-error"><?php echo $error; ?></div>
                <?php endif; ?>

                <!-- Statistics -->
                <div class="cards">
                    <div class="card">
                        <h4>Pending Approvals</h4>
                        <div class="value"><?php echo $stats['pending']; ?></div>
                        <div class="description">Awaiting review</div>
                    </div>
                    <div class="card">
                        <h4>Waitlist Pending</h4>
                        <div class="value"><?php echo $stats['waitlist_pending']; ?></div>
                        <div class="description">Waitlist registrations</div>
                    </div>
                    <div class="card">
                        <h4>Total Pending</h4>
                        <div class="value"><?php echo $stats['total_pending']; ?></div>
                        <div class="description">All pending registrations</div>
                    </div>
                </div>

                <!-- Pending Registrations -->
                <div class="table-container">
                    <div style="padding: 1.5rem; border-bottom: 1px solid var(--border-color);">
                        <h3 style="margin: 0; font-size: 1.125rem; font-weight: 600; color: var(--text-primary);">Pending Registrations</h3>
                        <p style="margin: 0.25rem 0 0; color: var(--text-secondary); font-size: 0.875rem;">Review and approve event registrations</p>
                    </div>

                    <?php if (empty($pending_registrations)): ?>
                        <div style="padding: 3rem; text-align: center;">
                            <i data-lucide="check-circle" style="width: 48px; height: 48px; color: var(--text-muted); margin-bottom: 1rem;"></i>
                            <h3 style="color: var(--text-muted); margin-bottom: 0.5rem;">No pending registrations</h3>
                            <p style="color: var(--text-secondary);">All registrations have been reviewed.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Student</th>
                                        <th>Event</th>
                                        <th>Date & Time</th>
                                        <th>Payment Screenshot</th>
                                        <th>Payment Details</th>
                                        <th>Registered</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pending_registrations as $reg): ?>
                                        <tr>
                                            <td>
                                                <div>
                                                    <div style="font-weight: 500; color: var(--text-primary);">
                                                        <?php echo htmlspecialchars($reg['student_name']); ?>
                                                    </div>
                                                    <div style="font-size: 0.875rem; color: var(--text-secondary);">
                                                        <?php echo htmlspecialchars($reg['student_email']); ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div>
                                                    <div style="font-weight: 500;">
                                                        <?php echo htmlspecialchars($reg['event_title']); ?>
                                                    </div>
                                                    <div style="font-size: 0.875rem; color: var(--text-secondary);">
                                                        <?php echo htmlspecialchars($reg['venue']); ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div>
                                    <div style="font-weight: 500;">
                                        Event Time
                                    </div>
                                    <div style="font-size: 0.875rem; color: var(--text-secondary);">
                                        <?php echo date('g:i A', strtotime($reg['event_time'])); ?>
                                    </div>
                                </div>
                                            </td>
                                            <td>
                                                <?php if ($reg['payment_screenshot']): ?>
                                                    <button onclick="viewPaymentScreenshot('<?php echo htmlspecialchars($reg['payment_screenshot']); ?>', '<?php echo htmlspecialchars($reg['student_name']); ?>')" class="btn btn-info btn-sm">
                                                        <i data-lucide="image"></i> View
                                                    </button>
                                                <?php else: ?>
                                                    <span style="color: var(--text-muted);">No image</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div style="max-width: 200px; overflow: hidden; text-overflow: ellipsis;">
                                                    <?php echo htmlspecialchars($reg['payment_details'] ?: 'No details provided'); ?>
                                                </div>
                                            </td>
                                            <td>
                                                <?php echo date('M j, Y g:i A', strtotime($reg['registered_on'])); ?>
                                            </td>
                                            <td>
                                                <span class="badge <?php echo $reg['status'] === 'pending' ? 'warning' : 'info'; ?>">
                                                    <?php echo ucfirst(str_replace('_', ' ', $reg['status'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div style="display: flex; gap: 0.25rem; align-items: center;">
                                                    <!-- Approve Form -->
                                                    <form method="post" style="display: inline;">
                                                        <input type="hidden" name="action" value="approve">
                                                        <input type="hidden" name="registration_id" value="<?php echo $reg['id']; ?>">
                                                        <button type="submit" class="btn btn-success btn-xs" onclick="return confirm('Approve this registration?')">
                                                            <i data-lucide="check"></i> Approve
                                                        </button>
                                                    </form>

                                                    <!-- Reject Form -->
                                                    <form method="post" style="display: inline;" onsubmit="return showRejectModal(this)">
                                                        <input type="hidden" name="action" value="reject">
                                                        <input type="hidden" name="registration_id" value="<?php echo $reg['id']; ?>">
                                                        <button type="submit" class="btn btn-danger btn-xs">
                                                            <i data-lucide="x"></i> Reject
                                                        </button>
                                                    </form>

                                                    <!-- Generate Certificate -->
                                                    <?php if ($reg['status'] === 'approved'): ?>
                                                        <a href="generate-certificate.php?id=<?php echo $reg['id']; ?>" class="btn btn-info btn-xs" target="_blank">
                                                            <i data-lucide="award"></i> Certificate
                                                        </a>
                                                    <?php endif; ?>
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

            <!-- QR Code Downloads Section -->
            <div class="card">
                <div class="card-header">
                    <h2 style="margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                        <i data-lucide="qr-code"></i>
                        QR Code Downloads
                    </h2>
                    <p style="margin: 0.5rem 0 0 0; color: var(--text-secondary);">Download QR codes for approved registrations</p>
                </div>
                <div class="card-body">
                    <?php if (empty($approved_registrations)): ?>
                        <div style="padding: 3rem; text-align: center;">
                            <i data-lucide="qr-code" style="width: 48px; height: 48px; color: var(--text-muted); margin-bottom: 1rem;"></i>
                            <h3 style="color: var(--text-muted); margin-bottom: 0.5rem;">No QR codes available</h3>
                            <p style="color: var(--text-secondary);">QR codes will appear here after approving registrations.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Student</th>
                                        <th>Event</th>
                                        <th>Date & Time</th>
                                        <th>Approved</th>
                                        <th>QR Code</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($approved_registrations as $reg): ?>
                                        <tr>
                                            <td>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($reg['student_name']); ?></strong><br>
                                                    <small style="color: var(--text-secondary);"><?php echo htmlspecialchars($reg['student_email']); ?></small>
                                                </div>
                                            </td>
                                            <td>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($reg['event_title']); ?></strong><br>
                                                    <small style="color: var(--text-secondary);"><?php echo htmlspecialchars($reg['venue']); ?></small>
                                                </div>
                                            </td>
                                            <td>
                                                <div>
                                                    <?php echo date('M j, Y', strtotime($reg['event_date'])); ?><br>
                                                    <small style="color: var(--text-secondary);">
                                                        <?php echo $reg['event_time'] ? date('g:i A', strtotime($reg['event_time'])) : 'TBD'; ?>
                                                    </small>
                                                </div>
                                            </td>
                                            <td>
                                                <?php echo date('M j, Y g:i A', strtotime($reg['approved_at'])); ?>
                                            </td>
                                            <td>
                                                <div style="display: flex; gap: 0.5rem; align-items: center;">
                                                    <button onclick="previewQRCode(<?php echo $reg['id']; ?>)" class="btn btn-info btn-sm">
                                                        <i data-lucide="eye"></i> Preview
                                                    </button>
                                                    <a href="download_qr.php?registration_id=<?php echo $reg['id']; ?>" class="btn btn-success btn-sm" target="_blank">
                                                        <i data-lucide="download"></i> Download
                                                    </a>
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

    <!-- QR Code Preview Modal -->
    <div id="qrModal" class="modal" style="display: none;">
        <div class="modal-content" style="max-width: 500px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                <h3 id="qrModalTitle">QR Code Preview</h3>
                <button type="button" onclick="closeQRModal()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer;">&times;</button>
            </div>
            <div style="text-align: center;" id="qrCodeContainer">
                <div id="qrCodeLoading">Loading QR code...</div>
            </div>
            <div class="form-actions" style="justify-content: center; margin-top: 1rem;">
                <button type="button" class="btn btn-secondary" onclick="closeQRModal()">Close</button>
            </div>
        </div>
    </div>

    <!-- Rejection Modal -->
    <div id="rejectModal" class="modal" style="display: none;">
        <div class="modal-content">
            <h3>Reject Registration</h3>
            <form id="rejectForm" method="post">
                <input type="hidden" name="action" value="reject">
                <input type="hidden" name="registration_id" id="rejectRegistrationId">
                <div class="form-group">
                    <label for="rejection_reason">Reason for Rejection</label>
                    <textarea id="rejection_reason" name="rejection_reason" class="form-control" rows="4" required></textarea>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-danger">Reject Registration</button>
                    <button type="button" class="btn btn-secondary" onclick="closeRejectModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Payment Screenshot Modal -->
    <div id="paymentModal" class="modal" style="display: none;">
        <div class="modal-content" style="max-width: 800px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                <h3 id="paymentModalTitle">Payment Screenshot</h3>
                <button type="button" onclick="closePaymentModal()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer;">&times;</button>
            </div>
            <div style="text-align: center;">
                <img id="paymentImage" src="" alt="Payment Screenshot" style="max-width: 100%; max-height: 500px; border-radius: 0.5rem; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
            </div>
            <div class="form-actions" style="justify-content: center; margin-top: 1rem;">
                <button type="button" class="btn btn-secondary" onclick="closePaymentModal()">Close</button>
            </div>
        </div>
    </div>

    <script>
        // Initialize Lucide icons
        lucide.createIcons();

        function showRejectModal(form) {
            event.preventDefault();
            const formData = new FormData(form);
            document.getElementById('rejectRegistrationId').value = formData.get('registration_id');
            document.getElementById('rejectModal').style.display = 'block';
            return false;
        }

        function closeRejectModal() {
            document.getElementById('rejectModal').style.display = 'none';
            document.getElementById('rejectForm').reset();
        }

        function viewPaymentScreenshot(imagePath, studentName) {
            document.getElementById('paymentModalTitle').textContent = `Payment Screenshot - ${studentName}`;
            document.getElementById('paymentImage').src = `../${imagePath}`;
            document.getElementById('paymentModal').style.display = 'block';
        }

        function closePaymentModal() {
            document.getElementById('paymentModal').style.display = 'none';
            document.getElementById('paymentImage').src = '';
        }

        function previewQRCode(registrationId) {
            document.getElementById('qrModal').style.display = 'block';
            document.getElementById('qrCodeContainer').innerHTML = '<div id="qrCodeLoading">Loading QR code...</div>';

            // Fetch QR code data
            fetch('get_qr_code.php?registration_id=' + registrationId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('qrCodeContainer').innerHTML =
                            '<img src="' + data.qr_data_uri + '" alt="QR Code" style="max-width: 300px; border: 1px solid #ddd; border-radius: 8px;">' +
                            '<div style="margin-top: 1rem; font-size: 0.875rem; color: var(--text-secondary);">' +
                            '<strong>Student:</strong> ' + data.student_name + '<br>' +
                            '<strong>Event:</strong> ' + data.event_title + '<br>' +
                            '<strong>Date:</strong> ' + data.event_date +
                            '</div>';
                    } else {
                        document.getElementById('qrCodeContainer').innerHTML =
                            '<div style="color: var(--danger); padding: 2rem;">Error loading QR code: ' + data.error + '</div>';
                    }
                })
                .catch(error => {
                    document.getElementById('qrCodeContainer').innerHTML =
                        '<div style="color: var(--danger); padding: 2rem;">Error loading QR code</div>';
                });
        }

        function closeQRModal() {
            document.getElementById('qrModal').style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const rejectModal = document.getElementById('rejectModal');
            const paymentModal = document.getElementById('paymentModal');
            const qrModal = document.getElementById('qrModal');
            if (event.target === rejectModal) {
                closeRejectModal();
            } else if (event.target === paymentModal) {
                closePaymentModal();
            } else if (event.target === qrModal) {
                closeQRModal();
            }
        }
    </script>

    <style>
        .modal {
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }

        .modal-content {
            background-color: white;
            margin: 15% auto;
            padding: 2rem;
            border-radius: 0.5rem;
            width: 80%;
            max-width: 500px;
        }

        .form-actions {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
        }
    </style>
</body>
</html>
