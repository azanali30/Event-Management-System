<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$registration_id = isset($_GET['registration_id']) ? (int)$_GET['registration_id'] : 0;
$preview = isset($_GET['preview']) ? true : false;
$download = isset($_GET['download']) ? true : false;

if (!$registration_id) {
    if ($preview) {
        echo json_encode(['success' => false, 'message' => 'Invalid registration ID']);
    } else {
        echo 'Invalid registration ID';
    }
    exit;
}

try {
    // Get registration details with event and user information
    $stmt = $pdo->prepare("
        SELECT 
            r.id as registration_id,
            r.registration_date,
            r.status,
            e.title as event_title,
            e.event_date,
            e.venue,
            u.username,
            ud.first_name,
            ud.last_name,
            ud.department
        FROM registration r
        JOIN events e ON r.event_id = e.id
        JOIN users u ON r.user_id = u.id
        LEFT JOIN userdetails ud ON u.id = ud.user_id
        WHERE r.id = ? AND r.user_id = ? AND r.status = 'approved'
    ");
    
    $stmt->execute([$registration_id, $user_id]);
    $registration = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$registration) {
        if ($preview) {
            echo json_encode(['success' => false, 'message' => 'Certificate not found or not approved']);
        } else {
            echo 'Certificate not found or not approved';
        }
        exit;
    }
    
    // Prepare certificate data
    $recipient_name = trim(($registration['first_name'] ?? '') . ' ' . ($registration['last_name'] ?? ''));
    if (empty($recipient_name)) {
        $recipient_name = $registration['username'];
    }
    
    $event_title = $registration['event_title'];
    $event_date = date('F j, Y', strtotime($registration['event_date']));
    $completion_date = date('F j, Y', strtotime($registration['registration_date']));
    $venue = $registration['venue'];
    $department = $registration['department'] ?? 'N/A';
    
    if ($preview) {
        // Return HTML for preview
        $certificate_html = generateCertificateHTML($recipient_name, $event_title, $event_date, $completion_date, $venue, $department);
        echo json_encode(['success' => true, 'html' => $certificate_html]);
    } elseif ($download) {
        // Generate PDF for download
        require_once '../vendor/autoload.php'; // Assuming you have TCPDF or similar installed
        
        // For now, we'll create a simple HTML version that can be printed as PDF
        // In a production environment, you'd want to use a proper PDF library
        
        header('Content-Type: text/html');
        header('Content-Disposition: attachment; filename="certificate_' . $registration_id . '.html"');
        
        echo generatePrintableCertificateHTML($recipient_name, $event_title, $event_date, $completion_date, $venue, $department);
    }
    
} catch (Exception $e) {
    if ($preview) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    } else {
        echo 'Error generating certificate: ' . $e->getMessage();
    }
}

function generateCertificateHTML($recipient_name, $event_title, $event_date, $completion_date, $venue, $department) {
    return '
        <div class="certificate-template">
            <div class="certificate-body">
                <div class="certificate-header-text">Certificate of Completion</div>
                <p style="font-size: 1.1rem; margin: 1rem 0;">This is to certify that</p>
                <div class="certificate-recipient">' . htmlspecialchars($recipient_name) . '</div>
                <p style="font-size: 1rem; margin: 1rem 0;">has successfully completed the event</p>
                <div class="certificate-event">"' . htmlspecialchars($event_title) . '"</div>
                <p style="font-size: 0.9rem; margin: 1rem 0; color: #6b7280;">held on ' . $event_date . ' at ' . htmlspecialchars($venue) . '</p>
                <div class="certificate-date">Issued on: ' . $completion_date . '</div>
                <div style="margin-top: 2rem; font-size: 0.8rem; color: #9ca3af;">Department: ' . htmlspecialchars($department) . '</div>
            </div>
        </div>
    ';
}

function generatePrintableCertificateHTML($recipient_name, $event_title, $event_date, $completion_date, $venue, $department) {
    return '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Certificate - ' . htmlspecialchars($recipient_name) . '</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 0;
        }
        
        body {
            font-family: "Times New Roman", serif;
            margin: 0;
            padding: 2rem;
            background: white;
        }
        
        .certificate-container {
            width: 100%;
            height: 100vh;
            border: 8px solid #d97706;
            border-radius: 20px;
            padding: 3rem;
            box-sizing: border-box;
            background: linear-gradient(135deg, #fef3c7, #fde68a);
            position: relative;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
        }
        
        .certificate-container::before {
            content: "";
            position: absolute;
            top: 2rem;
            left: 2rem;
            right: 2rem;
            bottom: 2rem;
            border: 4px solid #f59e0b;
            border-radius: 12px;
        }
        
        .certificate-header {
            font-size: 3rem;
            font-weight: bold;
            color: #92400e;
            margin-bottom: 2rem;
            text-transform: uppercase;
            letter-spacing: 4px;
            z-index: 1;
        }
        
        .certificate-body {
            z-index: 1;
            max-width: 80%;
        }
        
        .certificate-text {
            font-size: 1.5rem;
            margin: 1rem 0;
            color: #374151;
        }
        
        .recipient-name {
            font-size: 2.5rem;
            font-weight: bold;
            color: #1f2937;
            margin: 2rem 0;
            text-decoration: underline;
            text-decoration-color: #d97706;
        }
        
        .event-title {
            font-size: 2rem;
            color: #374151;
            margin: 2rem 0;
            font-style: italic;
        }
        
        .event-details {
            font-size: 1.2rem;
            color: #6b7280;
            margin: 1.5rem 0;
        }
        
        .certificate-footer {
            margin-top: 3rem;
            font-size: 1rem;
            color: #9ca3af;
        }
        
        .signature-section {
            display: flex;
            justify-content: space-between;
            margin-top: 4rem;
            width: 100%;
            max-width: 600px;
        }
        
        .signature {
            text-align: center;
            border-top: 2px solid #374151;
            padding-top: 0.5rem;
            width: 200px;
        }
    </style>
</head>
<body>
    <div class="certificate-container">
        <div class="certificate-header">Certificate of Completion</div>
        
        <div class="certificate-body">
            <div class="certificate-text">This is to certify that</div>
            
            <div class="recipient-name">' . htmlspecialchars($recipient_name) . '</div>
            
            <div class="certificate-text">has successfully completed the event</div>
            
            <div class="event-title">"' . htmlspecialchars($event_title) . '"</div>
            
            <div class="event-details">
                Held on ' . $event_date . '<br>
                at ' . htmlspecialchars($venue) . '
            </div>
            
            <div class="certificate-footer">
                <div>Department: ' . htmlspecialchars($department) . '</div>
                <div style="margin-top: 1rem;">Issued on: ' . $completion_date . '</div>
            </div>
            
            <div class="signature-section">
                <div class="signature">
                    <div>Event Coordinator</div>
                </div>
                <div class="signature">
                    <div>Director</div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Auto-print when page loads (for download)
        window.onload = function() {
            window.print();
        }
    </script>
</body>
</html>';
}
?>