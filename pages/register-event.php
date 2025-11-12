<?php
// Simple Event Registration Form
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Database connection
$host = 'localhost';
$dbname = 'event';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

$event_id = isset($_GET['event_id']) ? (int)$_GET['event_id'] : 0;
$message = '';
$message_type = '';

// Get event details
$event = null;
if ($event_id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM events WHERE event_id = ?");
    $stmt->execute([$event_id]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);
}



// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    error_log('Form submission detected!');
    echo "<!-- FORM PROCESSING STARTED -->";
    try {
        // Get form data
        $student_name = trim($_POST['student_name']);
        $student_email = trim($_POST['student_email']);
        $payment_details = trim($_POST['payment_details']);
        
        // Validate required fields
        if (empty($student_name)) {
            throw new Exception('Student name is required.');
        }
        
        if (empty($student_email) || !filter_var($student_email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Valid email address is required.');
        }
        
        if (!isset($_POST['terms_accepted'])) {
            throw new Exception('Please accept the terms and conditions.');
        }
        
        // Check if already registered
        $stmt = $pdo->prepare("SELECT id FROM registration WHERE event_id = ? AND student_id = ?");
        $stmt->execute([$event_id, $_SESSION['user_id']]);
        if ($stmt->fetch()) {
            throw new Exception('You are already registered for this event.');
        }
        
        // Handle file upload
        $payment_screenshot = '';
        if (isset($_FILES['payment_screenshot']) && $_FILES['payment_screenshot']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['payment_screenshot'];
            
            // Validate file type
            $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
            if (!in_array($file['type'], $allowed_types)) {
                throw new Exception('Please upload a valid image file (JPG, PNG, GIF).');
            }
            
            // Validate file size (5MB max)
            if ($file['size'] > 5 * 1024 * 1024) {
                throw new Exception('File size must be less than 5MB.');
            }
            
            // Create upload directory
            $upload_dir = '../uploads/payments/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            // Generate unique filename
            $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'payment_' . $event_id . '_' . $_SESSION['user_id'] . '_' . time() . '.' . $file_extension;
            $file_path = $upload_dir . $filename;
            
            if (move_uploaded_file($file['tmp_name'], $file_path)) {
                $payment_screenshot = 'uploads/payments/' . $filename;
            } else {
                throw new Exception('Failed to upload payment screenshot.');
            }
        } else {
            throw new Exception('Please upload a payment screenshot.');
        }
        
        // Insert registration
        $stmt = $pdo->prepare("
            INSERT INTO registration (student_id, event_id, student_name, student_email, payment_screenshot, payment_details, status, qr_code) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $result = $stmt->execute([
            $_SESSION['user_id'],
            $event_id,
            $student_name,
            $student_email,
            $payment_screenshot,
            $payment_details,
            'pending',
            ''
        ]);
        
        if ($result) {
            $registration_id = $pdo->lastInsertId();
            $message = 'SUCCESS! Registration submitted successfully! Your registration is pending approval. Registration ID: ' . $registration_id;
            $message_type = 'success';

            // Send email notification for new registration using EmailService
            try {
                require_once '../includes/EmailService.php';
                $emailService = new EmailService();

                $registration_data = [
                    'name' => $student_name,
                    'email' => $student_email,
                    'phone' => $_SESSION['user_phone'] ?? 'Not provided',
                    'event_name' => $event['title'],
                    'registration_date' => date('Y-m-d H:i:s'),
                    'registration_id' => $registration_id,
                    'student_id' => $_SESSION['user_id'],
                    'event_date' => $event['event_date'],
                    'event_time' => $event['event_time'] ?? 'TBD',
                    'venue' => $event['venue'],
                    'payment_details' => $payment_details,
                    'payment_screenshot' => $payment_screenshot,
                    'status' => 'Pending Approval',
                    'user_ip' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown'
                ];

                // Send admin notification using new EmailService
                $emailService->sendNewRegistrationNotification($registration_data);
            } catch (Exception $email_error) {
                error_log("Email notification failed for new registration: " . $email_error->getMessage());
            }
        } else {
            throw new Exception('Failed to save registration. Please try again.');
        }
        
    } catch (Exception $e) {
        $message = $e->getMessage();
        $message_type = 'error';
    }
}

// Check if user is already registered and get registration status
$is_registered = false;
$registration_status = '';
$registration_data = null;
if ($event_id > 0) {
    $stmt = $pdo->prepare("SELECT id, status FROM registration WHERE event_id = ? AND student_id = ?");
    $stmt->execute([$event_id, $_SESSION['user_id']]);
    $registration_data = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($registration_data) {
        $is_registered = true;
        $registration_status = $registration_data['status'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register for Event - EVENTSPHERE</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
        }
        
        .header p {
            font-size: 1.1rem;
            opacity: 0.9;
        }
        
        .content {
            padding: 40px;
        }
        
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            font-weight: 500;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .event-info {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            border-left: 5px solid #007bff;
        }
        
        .event-info h2 {
            color: #333;
            margin-bottom: 15px;
        }
        
        .event-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        
        .event-detail {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .event-detail strong {
            color: #007bff;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }
        
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s ease;
        }
        
        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #007bff;
        }
        
        .form-group input[type="file"] {
            padding: 8px;
        }
        
        .form-group small {
            color: #666;
            font-size: 14px;
            margin-top: 5px;
            display: block;
        }
        
        .checkbox-group {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            margin-bottom: 25px;
        }
        
        .checkbox-group input[type="checkbox"] {
            width: auto;
            margin: 0;
        }
        
        .checkbox-group label {
            margin: 0;
            font-weight: normal;
        }
        
        .btn {
            padding: 15px 30px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0,123,255,0.3);
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
            margin-left: 15px;
        }
        
        .btn-secondary:hover {
            background: #545b62;
        }
        
        .form-actions {
            text-align: center;
            margin-top: 30px;
        }
        
        .registered-status {
            text-align: center;
            padding: 40px;
        }

        .registered-status h3 {
            color: #28a745;
            margin-bottom: 20px;
        }

        .registered-status .btn {
            margin-top: 20px;
        }

        .status-message {
            font-size: 1.1rem;
            margin: 20px 0;
            padding: 15px;
            border-radius: 8px;
            font-weight: 500;
        }

        .status-pending {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
        }

        .status-confirmed {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }

        .status-waitlist {
            background-color: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
        }

        .status-cancelled {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        
        .required {
            color: #dc3545;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>EVENTSPHERE</h1>
            <p>Event Registration System</p>
        </div>
        
        <div class="content">

            
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($event): ?>
                <div class="event-info">
                    <h2><?php echo htmlspecialchars($event['title']); ?></h2>
                    <p style="margin-bottom: 15px;"><?php echo htmlspecialchars($event['description']); ?></p>
                    <div class="event-details">
                        <div class="event-detail">
                            <strong>Date:</strong> <?php echo date('M d, Y', strtotime($event['event_date'])); ?>
                        </div>
                        <div class="event-detail">
                            <strong>Time:</strong> <?php echo $event['event_time'] ? date('g:i A', strtotime($event['event_time'])) : 'TBD'; ?>
                        </div>
                        <div class="event-detail">
                            <strong>Venue:</strong> <?php echo htmlspecialchars($event['venue']); ?>
                        </div>
                        <div class="event-detail">
                            <strong>Category:</strong> <?php echo ucfirst($event['category']); ?>
                        </div>
                    </div>
                </div>
                
                <?php if ($is_registered): ?>
                    <div class="registered-status">
                        <h3>✅ You are already registered for this event!</h3>
                        <?php
                        // Display dynamic status message based on registration status
                        switch ($registration_status) {
                            case 'pending':
                                echo '<div class="status-message status-pending">Your registration is <strong>pending approval</strong>. ⏳<br><small>We will review your payment and notify you once approved.</small></div>';
                                break;
                            case 'confirmed':
                                echo '<div class="status-message status-confirmed">Your registration has been <strong>confirmed</strong>! 🎉<br><small>You\'re all set for the event. Check your email for details.</small></div>';
                                break;
                            case 'waitlist':
                                echo '<div class="status-message status-waitlist">You are on the <strong>waitlist</strong> for this event. 📋<br><small>We\'ll notify you if a spot becomes available.</small></div>';
                                break;
                            case 'waitlist_pending':
                                echo '<div class="status-message status-pending">Your waitlist registration is <strong>pending approval</strong>. ⏳<br><small>We will review your request and notify you soon.</small></div>';
                                break;
                            case 'cancelled':
                                echo '<div class="status-message status-cancelled">Your registration has been <strong>cancelled</strong>. ❌<br><small>Contact support if you believe this is an error.</small></div>';
                                break;
                            default:
                                echo '<div class="status-message">Registration status: <strong>' . htmlspecialchars(ucfirst($registration_status)) . '</strong></div>';
                                break;
                        }
                        ?>
                        <a href="events.php" class="btn btn-secondary">Back to Events</a>
                    </div>
                <?php else: ?>
                    <form method="POST" action="" enctype="multipart/form-data">
                        <input type="hidden" name="event_id" value="<?php echo $event_id; ?>">
                        
                        <div class="form-group">
                            <label for="student_name">Student Name <span class="required">*</span></label>
                            <input type="text" id="student_name" name="student_name" required 
                                   value="<?php echo htmlspecialchars($_SESSION['user_name'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="student_email">Student Email <span class="required">*</span></label>
                            <input type="email" id="student_email" name="student_email" required 
                                   value="<?php echo htmlspecialchars($_SESSION['user_email'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="payment_screenshot">Payment Screenshot <span class="required">*</span></label>
                            <input type="file" id="payment_screenshot" name="payment_screenshot" accept="image/*" required>
                            <small>Upload a screenshot of your payment confirmation (JPG, PNG, GIF - Max 5MB)</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="payment_details">Payment Details</label>
                            <textarea id="payment_details" name="payment_details" rows="4" 
                                      placeholder="Enter payment method, transaction ID, or any additional details..."><?php echo htmlspecialchars($_POST['payment_details'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="checkbox-group">
                            <input type="checkbox" id="terms_accepted" name="terms_accepted" required>
                            <label for="terms_accepted">I confirm that I want to register for this event and understand the terms.</label>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" name="register" class="btn btn-primary">
                                🎯 Register Now
                            </button>
                            <a href="events.php" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                <?php endif; ?>
            <?php else: ?>
                <div class="alert alert-error">
                    Event not found or not available for registration.
                </div>
                <div class="form-actions">
                    <a href="events.php" class="btn btn-primary">Back to Events</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        // Simple form validation - REMOVED TO DEBUG
        console.log('Form loaded, no validation blocking');
    </script>
</body>
</html>