<?php
/**
 * Add Test Data for QR System
 * Creates sample events, users, and registrations for testing
 */

require_once 'config/database.php';

try {
    $db = new Database();
    $pdo = $db->getConnection();
    
    echo "<h2>Adding Test Data for QR System</h2>";
    
    // Add test users
    echo "<h3>Adding Test Users...</h3>";
    
    $test_users = [
        ['email' => 'john.doe@example.com', 'password' => password_hash('password123', PASSWORD_DEFAULT), 'role' => 'participant', 'full_name' => 'John Doe', 'mobile' => '1234567890', 'department' => 'Computer Science', 'enrollment_no' => 'CS001'],
        ['email' => 'jane.smith@example.com', 'password' => password_hash('password123', PASSWORD_DEFAULT), 'role' => 'participant', 'full_name' => 'Jane Smith', 'mobile' => '1234567891', 'department' => 'Information Technology', 'enrollment_no' => 'IT001'],
        ['email' => 'mike.johnson@example.com', 'password' => password_hash('password123', PASSWORD_DEFAULT), 'role' => 'participant', 'full_name' => 'Mike Johnson', 'mobile' => '1234567892', 'department' => 'Electronics', 'enrollment_no' => 'EC001'],
        ['email' => 'admin@example.com', 'password' => password_hash('admin123', PASSWORD_DEFAULT), 'role' => 'admin', 'full_name' => 'Admin User', 'mobile' => '9999999999', 'department' => 'Administration', 'enrollment_no' => 'ADM001'],
    ];
    
    foreach ($test_users as $user) {
        // Check if user already exists
        $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
        $stmt->execute([$user['email']]);
        
        if (!$stmt->fetch()) {
            // Insert user
            $stmt = $pdo->prepare("INSERT INTO users (email, password, role) VALUES (?, ?, ?)");
            $stmt->execute([$user['email'], $user['password'], $user['role']]);
            $user_id = $pdo->lastInsertId();
            
            // Insert user details
            $stmt = $pdo->prepare("INSERT INTO userdetails (user_id, full_name, mobile, department, enrollment_no) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$user_id, $user['full_name'], $user['mobile'], $user['department'], $user['enrollment_no']]);
            
            echo "✓ Added user: {$user['email']} (ID: $user_id)<br>";
        } else {
            echo "- User already exists: {$user['email']}<br>";
        }
    }
    
    // Add test events
    echo "<h3>Adding Test Events...</h3>";
    
    $test_events = [
        [
            'title' => 'Tech Conference 2024',
            'description' => 'Annual technology conference featuring latest trends in AI and Machine Learning',
            'category' => 'technical',
            'event_date' => '2024-12-15',
            'event_time' => '09:00:00',
            'venue' => 'Main Auditorium',
            'organizer_id' => 1,
            'status' => 'approved'
        ],
        [
            'title' => 'Cultural Fest',
            'description' => 'Celebrate diversity with music, dance, and art performances',
            'category' => 'cultural',
            'event_date' => '2024-12-20',
            'event_time' => '18:00:00',
            'venue' => 'Open Ground',
            'organizer_id' => 1,
            'status' => 'approved'
        ],
        [
            'title' => 'Web Development Workshop',
            'description' => 'Hands-on workshop on modern web development technologies',
            'category' => 'workshop',
            'event_date' => '2024-12-10',
            'event_time' => '14:00:00',
            'venue' => 'Computer Lab 1',
            'organizer_id' => 1,
            'status' => 'approved'
        ],
        [
            'title' => 'Sports Day',
            'description' => 'Annual sports competition with various indoor and outdoor games',
            'category' => 'sports',
            'event_date' => '2024-12-25',
            'event_time' => '08:00:00',
            'venue' => 'Sports Complex',
            'organizer_id' => 1,
            'status' => 'approved'
        ]
    ];
    
    foreach ($test_events as $event) {
        // Check if event already exists
        $stmt = $pdo->prepare("SELECT event_id FROM events WHERE title = ?");
        $stmt->execute([$event['title']]);
        
        if (!$stmt->fetch()) {
            $stmt = $pdo->prepare("
                INSERT INTO events (title, description, category, event_date, event_time, venue, organizer_id, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $event['title'], $event['description'], $event['category'], 
                $event['event_date'], $event['event_time'], $event['venue'], 
                $event['organizer_id'], $event['status']
            ]);
            $event_id = $pdo->lastInsertId();
            echo "✓ Added event: {$event['title']} (ID: $event_id)<br>";
        } else {
            echo "- Event already exists: {$event['title']}<br>";
        }
    }
    
    // Add test registrations
    echo "<h3>Adding Test Registrations...</h3>";
    
    // Get user and event IDs
    $stmt = $pdo->prepare("SELECT user_id FROM users WHERE role = 'participant' ORDER BY user_id LIMIT 3");
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $stmt = $pdo->prepare("SELECT event_id FROM events ORDER BY event_id LIMIT 4");
    $stmt->execute();
    $events = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (!empty($users) && !empty($events)) {
        $registration_count = 0;
        
        foreach ($users as $user_id) {
            foreach ($events as $event_id) {
                // Check if registration already exists
                $stmt = $pdo->prepare("SELECT registration_id FROM registrations WHERE student_id = ? AND event_id = ?");
                $stmt->execute([$user_id, $event_id]);
                
                if (!$stmt->fetch()) {
                    $status = ($registration_count % 3 == 0) ? 'waitlist' : 'confirmed';
                    
                    $stmt = $pdo->prepare("INSERT INTO registrations (event_id, student_id, status) VALUES (?, ?, ?)");
                    $stmt->execute([$event_id, $user_id, $status]);
                    $registration_id = $pdo->lastInsertId();
                    
                    echo "✓ Added registration: User $user_id → Event $event_id (ID: $registration_id, Status: $status)<br>";
                    $registration_count++;
                } else {
                    echo "- Registration already exists: User $user_id → Event $event_id<br>";
                }
            }
        }
    } else {
        echo "❌ No users or events found to create registrations<br>";
    }
    
    // Summary
    echo "<h3>Summary</h3>";
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users");
    $stmt->execute();
    $user_count = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM events");
    $stmt->execute();
    $event_count = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM registrations");
    $stmt->execute();
    $registration_count = $stmt->fetchColumn();
    
    echo "<div style='background: #f8f9fa; padding: 20px; border-radius: 10px; margin: 20px 0;'>";
    echo "<strong>Database Summary:</strong><br>";
    echo "👥 Total Users: $user_count<br>";
    echo "📅 Total Events: $event_count<br>";
    echo "📝 Total Registrations: $registration_count<br>";
    echo "</div>";
    
    echo "<h3>Next Steps</h3>";
    echo "<div style='background: #e7f3ff; padding: 20px; border-radius: 10px; margin: 20px 0;'>";
    echo "1. <a href='test_qr_system.php'>Test QR System</a> - View and generate QR codes<br>";
    echo "2. <a href='admin/qr_scanner.php'>QR Scanner</a> - Scan QR codes for attendance<br>";
    echo "3. <a href='canva_qr_demo.php'>Canva QR Demo</a> - Generate styled QR codes<br>";
    echo "</div>";
    
    echo "<h3>Test Login Credentials</h3>";
    echo "<div style='background: #fff3cd; padding: 20px; border-radius: 10px; margin: 20px 0;'>";
    echo "<strong>Admin:</strong> admin@example.com / admin123<br>";
    echo "<strong>Student:</strong> john.doe@example.com / password123<br>";
    echo "<strong>Student:</strong> jane.smith@example.com / password123<br>";
    echo "<strong>Student:</strong> mike.johnson@example.com / password123<br>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div style='color: red; background: #f8d7da; padding: 20px; border-radius: 10px; margin: 20px 0;'>";
    echo "❌ Error: " . $e->getMessage();
    echo "</div>";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Data Setup Complete</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
            margin: 0;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            padding: 40px;
        }
        
        h2 {
            color: #667eea;
            text-align: center;
            margin-bottom: 30px;
            font-size: 2rem;
        }
        
        h3 {
            color: #495057;
            margin: 25px 0 15px 0;
            padding-bottom: 10px;
            border-bottom: 2px solid #e9ecef;
        }
        
        a {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
        }
        
        a:hover {
            text-decoration: underline;
        }
        
        .success {
            color: #28a745;
        }
        
        .info {
            color: #17a2b8;
        }
        
        .warning {
            color: #ffc107;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- PHP output will be displayed here -->
    </div>
</body>
</html>
