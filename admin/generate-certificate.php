<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once './_auth.php';
require_once './_error_handler.php';
admin_require_login();

$db = new Database();
$pdo = $db->getConnection();

$registration_id = (int)($_GET['id'] ?? 0);

if (!$registration_id) {
    header('Location: registration-approvals.php');
    exit;
}

// Get registration details
$stmt = $pdo->prepare("
    SELECT r.*, e.title as event_title, e.event_date, e.venue,
            r.student_name, r.student_email
     FROM registration r
     JOIN events e ON r.event_id = e.event_id
    WHERE r.id = ? AND r.approved_at IS NOT NULL
");
$stmt->execute([$registration_id]);
$registration = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$registration) {
    header('Location: registration-approvals.php');
    exit;
}

// Generate certificate
$certificate_code = 'CERT-' . strtoupper(uniqid());
$certificate_data = [
    'student_name' => $registration['student_name'],
    'event_title' => $registration['event_title'],
    'event_date' => date('F j, Y', strtotime($registration['event_date'])),
    'venue' => $registration['venue'],
    'certificate_code' => $certificate_code,
    'issued_date' => date('F j, Y'),
    'issued_by' => 'EventSphere Administration'
];

// Save certificate to database
$stmt = $pdo->prepare("
    INSERT INTO certificates (event_id, user_id, certificate_code, issued_date, status) 
    VALUES (?, ?, ?, NOW(), 'generated')
");
$stmt->execute([
    $registration['event_id'],
    $registration['student_id'],
    $certificate_code
]);

// Generate HTML certificate
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Certificate of Participation</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 40px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        .certificate {
            background: white;
            border: 8px solid #f4d03f;
            border-radius: 20px;
            padding: 60px;
            text-align: center;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            position: relative;
            max-width: 800px;
            margin: 0 auto;
        }
        .certificate::before {
            content: "";
            position: absolute;
            top: 20px;
            left: 20px;
            right: 20px;
            bottom: 20px;
            border: 2px solid #e8e8e8;
            border-radius: 10px;
        }
        .header {
            margin-bottom: 40px;
        }
        .header h1 {
            color: #2c3e50;
            font-size: 36px;
            margin: 0;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 2px;
        }
        .header p {
            color: #7f8c8d;
            font-size: 18px;
            margin: 10px 0 0 0;
        }
        .main-content {
            margin: 40px 0;
        }
        .main-content h2 {
            color: #2c3e50;
            font-size: 28px;
            margin: 0 0 20px 0;
            font-weight: normal;
        }
        .student-name {
            color: #e74c3c;
            font-size: 32px;
            font-weight: bold;
            margin: 20px 0;
            text-decoration: underline;
            text-decoration-color: #f4d03f;
            text-decoration-thickness: 3px;
        }
        .event-details {
            margin: 30px 0;
            font-size: 18px;
            color: #34495e;
        }
        .event-details p {
            margin: 10px 0;
        }
        .footer {
            margin-top: 50px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .signature {
            text-align: center;
        }
        .signature p {
            margin: 0;
            color: #7f8c8d;
        }
        .signature .signature-line {
            border-top: 2px solid #2c3e50;
            width: 200px;
            margin: 20px auto 10px auto;
        }
        .certificate-code {
            position: absolute;
            bottom: 20px;
            right: 30px;
            font-size: 12px;
            color: #bdc3c7;
        }
        .print-button {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #3498db;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
        }
        .print-button:hover {
            background: #2980b9;
        }
        @media print {
            .print-button {
                display: none;
            }
            body {
                background: white;
                padding: 0;
            }
        }
    </style>
</head>
<body>
    <button class="print-button" onclick="window.print()">Print Certificate</button>
    
    <div class="certificate">
        <div class="header">
            <h1>Certificate of Participation</h1>
            <p>This is to certify that</p>
        </div>
        
        <div class="main-content">
            <h2>has successfully participated in</h2>
            <div class="student-name"><?php echo htmlspecialchars($certificate_data['student_name']); ?></div>
            
            <div class="event-details">
                <p><strong>Event:</strong> <?php echo htmlspecialchars($certificate_data['event_title']); ?></p>
                <p><strong>Date:</strong> <?php echo htmlspecialchars($certificate_data['event_date']); ?></p>
                <p><strong>Venue:</strong> <?php echo htmlspecialchars($certificate_data['venue']); ?></p>
            </div>
        </div>
        
        <div class="footer">
            <div class="signature">
                <div class="signature-line"></div>
                <p>EventSphere Administration</p>
                <p>Date: <?php echo htmlspecialchars($certificate_data['issued_date']); ?></p>
            </div>
        </div>
        
        <div class="certificate-code">
            Certificate ID: <?php echo htmlspecialchars($certificate_data['certificate_code']); ?>
        </div>
    </div>
</body>
</html>