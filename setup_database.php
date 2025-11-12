<?php
/**
 * Database Setup Script
 * Creates all necessary tables for the Event Management System
 */

require_once 'config/database.php';

try {
    $db = new Database();
    $pdo = $db->getConnection();
    
    echo "<h2>Setting up Event Management Database</h2>";
    
    // Create registrations table if it doesn't exist
    echo "<h3>Creating registrations table...</h3>";
    $registrations_sql = "
    CREATE TABLE IF NOT EXISTS `registrations` (
        `registration_id` int(11) NOT NULL AUTO_INCREMENT,
        `event_id` int(11) NOT NULL,
        `student_id` int(11) NOT NULL,
        `registered_on` timestamp NOT NULL DEFAULT current_timestamp(),
        `status` enum('confirmed','cancelled','waitlist') DEFAULT 'confirmed',
        PRIMARY KEY (`registration_id`),
        KEY `event_id` (`event_id`),
        KEY `student_id` (`student_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
    ";
    
    $pdo->exec($registrations_sql);
    echo "✓ Registrations table created/verified<br>";
    
    // Create attendance table if it doesn't exist
    echo "<h3>Creating attendance table...</h3>";
    $attendance_sql = "
    CREATE TABLE IF NOT EXISTS `attendance` (
        `attendance_id` int(11) NOT NULL AUTO_INCREMENT,
        `event_id` int(11) NOT NULL,
        `student_id` int(11) NOT NULL,
        `attended` tinyint(1) DEFAULT 0,
        `marked_on` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`attendance_id`),
        UNIQUE KEY `unique_attendance` (`event_id`, `student_id`),
        KEY `event_id` (`event_id`),
        KEY `student_id` (`student_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
    ";
    
    $pdo->exec($attendance_sql);
    echo "✓ Attendance table created/verified<br>";
    
    // Create events table if it doesn't exist
    echo "<h3>Creating events table...</h3>";
    $events_sql = "
    CREATE TABLE IF NOT EXISTS `events` (
        `event_id` int(11) NOT NULL AUTO_INCREMENT,
        `title` varchar(150) NOT NULL,
        `description` text DEFAULT NULL,
        `category` enum('technical','cultural','sports','workshop','seminar','other') DEFAULT NULL,
        `event_date` date NOT NULL,
        `event_time` time DEFAULT NULL,
        `venue` varchar(100) DEFAULT NULL,
        `organizer_id` int(11) NOT NULL,
        `status` enum('pending','approved','rejected','cancelled') DEFAULT 'pending',
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`event_id`),
        KEY `organizer_id` (`organizer_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
    ";
    
    $pdo->exec($events_sql);
    echo "✓ Events table created/verified<br>";
    
    // Create users table if it doesn't exist
    echo "<h3>Creating users table...</h3>";
    $users_sql = "
    CREATE TABLE IF NOT EXISTS `users` (
        `user_id` int(11) NOT NULL AUTO_INCREMENT,
        `email` varchar(100) NOT NULL,
        `password` varchar(255) NOT NULL,
        `role` enum('visitor','participant','organizer','admin') DEFAULT 'visitor',
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`user_id`),
        UNIQUE KEY `email` (`email`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
    ";
    
    $pdo->exec($users_sql);
    echo "✓ Users table created/verified<br>";
    
    // Create userdetails table if it doesn't exist
    echo "<h3>Creating userdetails table...</h3>";
    $userdetails_sql = "
    CREATE TABLE IF NOT EXISTS `userdetails` (
        `detail_id` int(11) NOT NULL AUTO_INCREMENT,
        `user_id` int(11) NOT NULL,
        `full_name` varchar(100) DEFAULT NULL,
        `mobile` varchar(15) DEFAULT NULL,
        `department` varchar(100) DEFAULT NULL,
        `enrollment_no` varchar(50) DEFAULT NULL,
        `year_of_study` int(11) DEFAULT NULL,
        `profile_picture` varchar(255) DEFAULT NULL,
        PRIMARY KEY (`detail_id`),
        UNIQUE KEY `user_id` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
    ";
    
    $pdo->exec($userdetails_sql);
    echo "✓ User details table created/verified<br>";
    
    // Add foreign key constraints if they don't exist
    echo "<h3>Adding foreign key constraints...</h3>";
    
    try {
        $pdo->exec("ALTER TABLE `registrations` ADD CONSTRAINT `registrations_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`event_id`) ON DELETE CASCADE");
        echo "✓ Added registrations -> events foreign key<br>";
    } catch (Exception $e) {
        echo "- Foreign key registrations -> events already exists<br>";
    }
    
    try {
        $pdo->exec("ALTER TABLE `registrations` ADD CONSTRAINT `registrations_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE");
        echo "✓ Added registrations -> users foreign key<br>";
    } catch (Exception $e) {
        echo "- Foreign key registrations -> users already exists<br>";
    }
    
    try {
        $pdo->exec("ALTER TABLE `attendance` ADD CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`event_id`) ON DELETE CASCADE");
        echo "✓ Added attendance -> events foreign key<br>";
    } catch (Exception $e) {
        echo "- Foreign key attendance -> events already exists<br>";
    }
    
    try {
        $pdo->exec("ALTER TABLE `attendance` ADD CONSTRAINT `attendance_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE");
        echo "✓ Added attendance -> users foreign key<br>";
    } catch (Exception $e) {
        echo "- Foreign key attendance -> users already exists<br>";
    }
    
    try {
        $pdo->exec("ALTER TABLE `userdetails` ADD CONSTRAINT `userdetails_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE");
        echo "✓ Added userdetails -> users foreign key<br>";
    } catch (Exception $e) {
        echo "- Foreign key userdetails -> users already exists<br>";
    }
    
    // Check table status
    echo "<h3>Database Status</h3>";
    $tables = ['users', 'userdetails', 'events', 'registrations', 'attendance'];
    
    foreach ($tables as $table) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM $table");
        $stmt->execute();
        $count = $stmt->fetchColumn();
        echo "📊 $table: $count records<br>";
    }
    
    echo "<div style='background: #d4edda; padding: 20px; border-radius: 10px; margin: 20px 0; color: #155724;'>";
    echo "<strong>✅ Database setup complete!</strong><br>";
    echo "All required tables have been created and verified.<br>";
    echo "You can now run the test data script: <a href='add_test_data.php'>Add Test Data</a>";
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
    <title>Database Setup</title>
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
