<?php
$page_title = "Dashboard";
$page_description = "Your personal dashboard for managing events and activities";
$additional_css = ['dashboard.css'];

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/qr_code_generator.php';

// Redirect if not logged in
if (!isLoggedIn()) {
    redirect('pages/login.php');
}

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get user ID directly from session
    $user_id = $_SESSION['user_id'] ?? null;

    // Initialize arrays to prevent undefined variable errors
    $user_stats = ['registered_events' => 0, 'attended_events' => 0, 'certificates_earned' => 0, 'upcoming_events' => 0];
    $registered_events = [];
    $recent_activities = [];
    $user_certificates = [];

    if ($user_id) {
        // Get user statistics - Fixed table and column names
        $stats_query = "
            SELECT
                COUNT(*) as registered_events,
                SUM(CASE WHEN r.status IN ('confirmed', 'approved') THEN 1 ELSE 0 END) as attended_events,
                SUM(CASE WHEN r.status IN ('confirmed', 'approved') THEN 1 ELSE 0 END) as certificates_earned,
                SUM(CASE WHEN r.status IN ('confirmed', 'approved', 'pending') AND e.event_date >= CURDATE() THEN 1 ELSE 0 END) as upcoming_events
            FROM registration r
            LEFT JOIN events e ON r.event_id = e.event_id
            WHERE r.student_id = ?
        ";
        $stmt = $pdo->prepare($stats_query);
        $stmt->execute([$user_id]);
        $user_stats = $stmt->fetch(PDO::FETCH_ASSOC);

        // Ensure stats have default values if null
        if (!$user_stats) {
            $user_stats = ['registered_events' => 0, 'attended_events' => 0, 'certificates_earned' => 0, 'upcoming_events' => 0];
        } else {
            $user_stats['registered_events'] = (int)($user_stats['registered_events'] ?? 0);
            $user_stats['attended_events'] = (int)($user_stats['attended_events'] ?? 0);
            $user_stats['certificates_earned'] = (int)($user_stats['certificates_earned'] ?? 0);
            $user_stats['upcoming_events'] = (int)($user_stats['upcoming_events'] ?? 0);
        }
        
        // Get all registered events (not just upcoming) - Fixed query with QR code data
        $registered_events_query = "
            SELECT
                e.event_id as id,
                e.title,
                e.event_date as date,
                e.event_time as time,
                e.venue,
                e.category,
                r.id as registration_id,
                r.status,
                r.registered_on,
                r.qr_code,
                CASE
                    WHEN r.status = 'confirmed' THEN r.registered_on
                    WHEN r.status = 'approved' THEN r.registered_on
                    ELSE NULL
                END as approved_at
            FROM registration r
            JOIN events e ON r.event_id = e.event_id
            WHERE r.student_id = ?
            ORDER BY r.registered_on DESC
            LIMIT 10
        ";
        $stmt = $pdo->prepare($registered_events_query);
        $stmt->execute([$user_id]);
        $registered_events = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get recent activities - Enhanced with status information
        $activities_query = "
            SELECT
                'registration' as type,
                CASE
                    WHEN r.status = 'pending' THEN CONCAT('Registered for ', e.title, ' (Pending Approval)')
                    WHEN r.status = 'confirmed' THEN CONCAT('Registration confirmed for ', e.title)
                    WHEN r.status = 'approved' THEN CONCAT('Registration approved for ', e.title)
                    WHEN r.status = 'cancelled' THEN CONCAT('Registration cancelled for ', e.title)
                    ELSE CONCAT('Registered for ', e.title)
                END as message,
                r.registered_on as date,
                CASE
                    WHEN r.status = 'pending' THEN 'clock'
                    WHEN r.status IN ('confirmed', 'approved') THEN 'check-circle'
                    WHEN r.status = 'cancelled' THEN 'x-circle'
                    ELSE 'user-plus'
                END as icon
            FROM registration r
            JOIN events e ON r.event_id = e.event_id
            WHERE r.student_id = ?
            ORDER BY r.registered_on DESC
            LIMIT 5
        ";
        $stmt = $pdo->prepare($activities_query);
        $stmt->execute([$user_id]);
        $recent_activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get certificates for approved/confirmed events
        $certificates_query = "
            SELECT
                e.event_id,
                e.title as event_title,
                e.event_date,
                r.registered_on as registration_date,
                r.status,
                r.id as registration_id
            FROM registration r
            JOIN events e ON r.event_id = e.event_id
            WHERE r.student_id = ? AND r.status IN ('approved', 'confirmed')
            ORDER BY r.registered_on DESC
        ";
        $stmt = $pdo->prepare($certificates_query);
        $stmt->execute([$user_id]);
        $user_certificates = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Stats loaded successfully

    } else {
        // Fallback if user not found - arrays already initialized above
        error_log('Dashboard - No user_id found in session');
    }
    
} catch (Exception $e) {
    // Log database error - arrays already initialized above
    error_log("Dashboard database error: " . $e->getMessage());
    // Reset stats to zero in case of error
    $user_stats = ['registered_events' => 0, 'attended_events' => 0, 'certificates_earned' => 0, 'upcoming_events' => 0];
}

include '../includes/pages-header.php';
?>

<div class="dashboard-page">
    <div class="container">

        <!-- Dashboard Header -->
        <div class="dashboard-header">
            <div class="welcome-section">
                <h1 class="welcome-title">Welcome back, <?php echo $_SESSION['user_name'] ?? $_SESSION['user_email'] ?? 'User'; ?>!</h1>
                <p class="welcome-subtitle">Here's what's happening with your events</p>
            </div>
            <div class="quick-actions">
                <a href="events.php" class="btn btn-primary">
                    <i data-lucide="calendar"></i>
                    Browse Events
                </a>
                <a href="profile.php" class="btn btn-secondary">
                    <i data-lucide="user"></i>
                    Edit Profile
                </a>
            </div>
        </div>
        
        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon stat-icon-primary">
                    <i data-lucide="calendar-plus"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo $user_stats['registered_events']; ?></div>
                    <div class="stat-label">Registered Events</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon stat-icon-success">
                    <i data-lucide="check-circle"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo $user_stats['attended_events']; ?></div>
                    <div class="stat-label">Attended Events</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon stat-icon-warning">
                    <i data-lucide="award"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo $user_stats['certificates_earned']; ?></div>
                    <div class="stat-label">Certificates Earned</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon stat-icon-info">
                    <i data-lucide="clock"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo $user_stats['upcoming_events']; ?></div>
                    <div class="stat-label">Upcoming Events</div>
                </div>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="dashboard-content">
            <div class="row">
                <!-- Registered Events -->
                <div class="col-8">
                    <div class="dashboard-section">
                        <div class="section-header">
                            <h2 class="section-title">
                                <i data-lucide="calendar-check"></i>
                                My Registered Events
                            </h2>
                            <a href="events.php" class="section-link">Browse More Events</a>
                        </div>
                        
                        <div class="events-list">
                            <?php if (empty($registered_events)): ?>
                                <div class="empty-state">
                                    <div class="empty-icon">
                                        <i data-lucide="calendar-x"></i>
                                    </div>
                                    <h3>No Registered Events</h3>
                                    <p>You haven't registered for any events yet.</p>
                                    <a href="events.php" class="btn btn-primary">Browse Events</a>
                                </div>
                            <?php else: ?>
                                <?php foreach ($registered_events as $event): ?>
                                    <div class="event-item">
                                        <div class="event-date">
                                            <div class="event-day"><?php echo date('d', strtotime($event['date'])); ?></div>
                                            <div class="event-month"><?php echo date('M', strtotime($event['date'])); ?></div>
                                        </div>
                                        <div class="event-details">
                                            <h4 class="event-title"><?php echo htmlspecialchars($event['title']); ?></h4>
                                            <div class="event-meta">
                                                <span class="event-time">
                                                    <i data-lucide="clock"></i>
                                                    <?php echo $event['time'] ? date('g:i A', strtotime($event['time'])) : 'TBD'; ?>
                                                </span>
                                                <span class="event-venue">
                                                    <i data-lucide="map-pin"></i>
                                                    <?php echo htmlspecialchars($event['venue']); ?>
                                                </span>
                                                <span class="event-category">
                                                    <i data-lucide="tag"></i>
                                                    <?php echo ucfirst($event['category']); ?>
                                                </span>
                                            </div>
                                            <div class="event-registration-info">
                                                <small class="text-muted">
                                                    Registered: <?php echo date('M j, Y g:i A', strtotime($event['registered_on'])); ?>
                                                    <?php if ($event['approved_at']): ?>
                                                        | Approved: <?php echo date('M j, Y g:i A', strtotime($event['approved_at'])); ?>
                                                    <?php endif; ?>
                                                </small>
                                            </div>
                                        </div>
                                        <div class="event-status">
                                            <span class="status-badge status-<?php echo $event['status']; ?>">
                                                <?php echo ucfirst($event['status']); ?>
                                            </span>
                                        </div>
                                        <div class="event-actions">
                                            <a href="event-details.php?id=<?php echo $event['id']; ?>" class="btn btn-sm btn-secondary">
                                                View Details
                                            </a>
                                            <?php if ($event['status'] === 'approved' && !empty($event['qr_code'])): ?>
                                                <button onclick="previewQRCode(<?php echo $event['registration_id']; ?>)" class="btn btn-sm btn-info">
                                                    <i data-lucide="qr-code"></i> QR Code
                                                </button>
                                                <a href="download_qr.php?registration_id=<?php echo $event['registration_id']; ?>" class="btn btn-sm btn-success" target="_blank">
                                                    <i data-lucide="download"></i> Download
                                                </a>
                                            <?php elseif ($event['status'] === 'approved'): ?>
                                                <button onclick="generateQRCode(<?php echo $event['registration_id']; ?>)" class="btn btn-sm btn-warning">
                                                    <i data-lucide="qr-code"></i> Generate QR
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Activity -->
                <div class="col-4">
                    <div class="dashboard-section">
                        <div class="section-header">
                            <h2 class="section-title">
                                <i data-lucide="activity"></i>
                                Recent Activity
                            </h2>
                        </div>
                        
                        <div class="activity-list">
                            <?php if (empty($recent_activities)): ?>
                                <div class="empty-state-small">
                                    <p>No recent activities</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($recent_activities as $activity): ?>
                                    <div class="activity-item">
                                        <div class="activity-icon">
                                            <i data-lucide="<?php echo $activity['icon']; ?>"></i>
                                        </div>
                                        <div class="activity-content">
                                            <p class="activity-message"><?php echo $activity['message']; ?></p>
                                            <span class="activity-date"><?php echo date('M j, Y', strtotime($activity['date'])); ?></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- My Certificates -->
                    <div class="dashboard-section">
                        <div class="section-header">
                            <h2 class="section-title">
                                <i data-lucide="award"></i>
                                My Certificates
                            </h2>
                            <a href="certificates.php" class="view-all-link">View All</a>
                        </div>
                        
                        <div class="certificates-grid">
                            <?php if (empty($user_certificates)): ?>
                                <div class="empty-state">
                                    <i data-lucide="award" class="empty-icon"></i>
                                    <h3>No Certificates Yet</h3>
                                    <p>Complete events to earn certificates</p>
                                    <a href="events.php" class="btn btn-primary">Browse Events</a>
                                </div>
                            <?php else: ?>
                                <?php foreach ($user_certificates as $certificate): ?>
                                    <div class="certificate-card">
                                        <div class="certificate-header">
                                            <div class="certificate-icon">
                                                <i data-lucide="award"></i>
                                            </div>
                                            <div class="certificate-info">
                                                <h4 class="certificate-title"><?php echo htmlspecialchars($certificate['event_title']); ?></h4>
                                                <p class="certificate-date">Completed: <?php echo date('M j, Y', strtotime($certificate['registration_date'])); ?></p>
                                            </div>
                                        </div>
                                        <div class="certificate-actions">
                                            <button class="btn btn-outline btn-sm" onclick="previewCertificate(<?php echo $certificate['registration_id']; ?>)">
                                                 <i data-lucide="eye"></i>
                                                 Preview
                                             </button>
                                             <button class="btn btn-primary btn-sm" onclick="downloadCertificate(<?php echo $certificate['registration_id']; ?>)">
                                                 <i data-lucide="download"></i>
                                                 Download
                                             </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Quick Links -->
                    <div class="dashboard-section">
                        <div class="section-header">
                            <h2 class="section-title">
                                <i data-lucide="link"></i>
                                Quick Links
                            </h2>
                        </div>
                        
                        <div class="quick-links">
                            <a href="events.php" class="quick-link">
                                <i data-lucide="calendar"></i>
                                <span>Browse Events</span>
                            </a>
                            <a href="certificates.php" class="quick-link">
                                <i data-lucide="award"></i>
                                <span>My Certificates</span>
                            </a>
                            <a href="gallery.php" class="quick-link">
                                <i data-lucide="image"></i>
                                <span>Event Gallery</span>
                            </a>
                            <a href="profile.php" class="quick-link">
                                <i data-lucide="user"></i>
                                <span>Edit Profile</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

    <!-- Certificate Modal -->
    <div id="certificateModal" class="modal">
        <div class="modal-content certificate-modal">
            <span class="close" onclick="closeCertificateModal()">&times;</span>
            <div id="certificatePreview"></div>
        </div>
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

    <style>
        .certificates-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }

        .certificate-card {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 1.5rem;
            transition: all 0.2s ease;
        }

        .certificate-card:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            transform: translateY(-2px);
        }

        .certificate-header {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .certificate-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #f59e0b, #d97706);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            flex-shrink: 0;
        }

        .certificate-info {
            flex: 1;
        }

        .certificate-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #1f2937;
            margin: 0 0 0.25rem 0;
            line-height: 1.4;
        }

        .certificate-date {
            color: #6b7280;
            font-size: 0.875rem;
            margin: 0;
        }

        .certificate-actions {
            display: flex;
            gap: 0.5rem;
        }

        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
        }

        .btn-outline {
            background: transparent;
            border: 1px solid #d1d5db;
            color: #374151;
        }

        .btn-outline:hover {
            background: #f9fafb;
            border-color: #9ca3af;
        }

        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: #6b7280;
        }

        .empty-state-small {
            text-align: center;
            padding: 2rem 1rem;
            color: #6b7280;
        }

        .empty-icon {
            width: 48px;
            height: 48px;
            margin: 0 auto 1rem;
            opacity: 0.5;
        }

        .empty-state h3 {
            color: #374151;
            margin: 0 0 0.5rem 0;
        }

        .empty-state p {
            margin: 0 0 1.5rem 0;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
        }

        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 2rem;
            border-radius: 8px;
            width: 90%;
            max-width: 800px;
            position: relative;
        }

        .certificate-modal {
            max-width: 900px;
        }

        .close {
            position: absolute;
            right: 1rem;
            top: 1rem;
            font-size: 2rem;
            font-weight: bold;
            cursor: pointer;
            color: #6b7280;
        }

        .close:hover {
            color: #374151;
        }

        #certificatePreview {
            margin-top: 2rem;
            text-align: center;
        }

        .certificate-template {
            border: 3px solid #d97706;
            border-radius: 12px;
            padding: 3rem;
            background: linear-gradient(135deg, #fef3c7, #fde68a);
            margin: 1rem 0;
            position: relative;
            min-height: 400px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }

        .certificate-template::before {
            content: '';
            position: absolute;
            top: 1rem;
            left: 1rem;
            right: 1rem;
            bottom: 1rem;
            border: 2px solid #f59e0b;
            border-radius: 8px;
        }

        .certificate-header-text {
            font-size: 2rem;
            font-weight: bold;
            color: #92400e;
            margin-bottom: 1rem;
            text-transform: uppercase;
            letter-spacing: 2px;
        }

        .certificate-body {
            text-align: center;
            z-index: 1;
        }

        .certificate-recipient {
            font-size: 1.5rem;
            font-weight: 600;
            color: #1f2937;
            margin: 1rem 0;
        }

        .certificate-event {
            font-size: 1.25rem;
            color: #374151;
            margin: 1rem 0;
        }

        .certificate-date {
            color: #6b7280;
            margin-top: 2rem;
        }
        
        .event-registration-info {
            margin-top: 8px;
            padding-top: 8px;
            border-top: 1px solid #e5e7eb;
        }
        
        .text-muted {
            color: #6b7280;
            font-size: 0.875rem;
        }
        
        .event-category {
            display: flex;
            align-items: center;
            gap: 4px;
            color: #6b7280;
            font-size: 0.875rem;
        }
        
        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-pending {
            background-color: #fef3c7;
            color: #92400e;
        }
        
        .status-approved {
            background-color: #d1fae5;
            color: #065f46;
        }
        
        .status-rejected {
            background-color: #fee2e2;
            color: #991b1b;
        }
        
        .status-confirmed {
            background-color: #dbeafe;
            color: #1e40af;
        }

        .status-waitlist {
            background-color: #e0e7ff;
            color: #3730a3;
        }

        .status-cancelled {
            background-color: #fee2e2;
            color: #991b1b;
        }
    </style>

    <script>
        function previewCertificate(registrationId) {
            // Show loading state
            document.getElementById('certificatePreview').innerHTML = '<p>Loading certificate...</p>';
            document.getElementById('certificateModal').style.display = 'block';
            
            // Fetch certificate data
            fetch('generate_certificate.php?registration_id=' + registrationId + '&preview=1')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('certificatePreview').innerHTML = data.html;
                    } else {
                        document.getElementById('certificatePreview').innerHTML = '<p>Error loading certificate: ' + data.message + '</p>';
                    }
                })
                .catch(error => {
                    document.getElementById('certificatePreview').innerHTML = '<p>Error loading certificate</p>';
                });
        }

        function downloadCertificate(registrationId) {
            // Create a temporary link to download the certificate
            const link = document.createElement('a');
            link.href = 'generate_certificate.php?registration_id=' + registrationId + '&download=1';
            link.download = 'certificate.pdf';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        function closeCertificateModal() {
            document.getElementById('certificateModal').style.display = 'none';
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
                            '<strong>Event:</strong> ' + data.event_title + '<br>' +
                            '<strong>Date:</strong> ' + data.event_date + '<br>' +
                            '<strong>Time:</strong> ' + data.event_time +
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

        function generateQRCode(registrationId) {
            // Show loading state
            const button = event.target;
            const originalText = button.innerHTML;
            button.innerHTML = '<i data-lucide="loader"></i> Generating...';
            button.disabled = true;

            // Generate QR code
            fetch('generate_qr.php?registration_id=' + registrationId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Reload the page to show the new QR code
                        location.reload();
                    } else {
                        alert('Error generating QR code: ' + data.error);
                        button.innerHTML = originalText;
                        button.disabled = false;
                    }
                })
                .catch(error => {
                    alert('Error generating QR code');
                    button.innerHTML = originalText;
                    button.disabled = false;
                });
        }

        function closeQRModal() {
            document.getElementById('qrModal').style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const certificateModal = document.getElementById('certificateModal');
            const qrModal = document.getElementById('qrModal');
            if (event.target === certificateModal) {
                certificateModal.style.display = 'none';
            } else if (event.target === qrModal) {
                qrModal.style.display = 'none';
            }
        }
    </script>

    <?php include '../includes/footer.php'; ?>
