<?php
require_once '../config/config.php';
require_once '../config/database.php';

// Simple admin guard: require logged-in admin if your app has sessions
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo 'Forbidden: login required';
    exit;
}

try {
    $db = new Database();
    $pdo = $db->getConnection();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $dbName = $pdo->query('SELECT DATABASE()')->fetchColumn();

    // Helper to check if column exists
    $hasColumn = function(string $table, string $column) use ($pdo, $dbName): bool {
        $sql = 'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$dbName, $table, $column]);
        return (int)$stmt->fetchColumn() > 0;
    };

    // 1) Ensure events capacity columns
    $added = [];
    if (!$hasColumn('events', 'max_participants')) {
        $pdo->exec("ALTER TABLE `events` ADD COLUMN `max_participants` INT(11) DEFAULT 50 AFTER `venue`");
        $added[] = 'events.max_participants';
    }
    if (!$hasColumn('events', 'current_participants')) {
        $pdo->exec("ALTER TABLE `events` ADD COLUMN `current_participants` INT(11) DEFAULT 0 AFTER `max_participants`");
        $added[] = 'events.current_participants';
    }
    if (!$hasColumn('events', 'waitlist_enabled')) {
        $pdo->exec("ALTER TABLE `events` ADD COLUMN `waitlist_enabled` TINYINT(1) DEFAULT 1 AFTER `current_participants`");
        $added[] = 'events.waitlist_enabled';
    }

    // 2) Ensure registrations table
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS `registrations` (
            `registration_id` INT(11) NOT NULL AUTO_INCREMENT,
            `event_id` INT(11) NOT NULL,
            `student_id` INT(11) NOT NULL,
            `status` ENUM('confirmed','waitlist','cancelled') DEFAULT 'confirmed',
            `registered_on` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
            PRIMARY KEY (`registration_id`),
            UNIQUE KEY `unique_registration` (`event_id`, `student_id`),
            KEY `event_id` (`event_id`),
            KEY `student_id` (`student_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;"
    );

    // Try to add FKs (ignore if users/events differ)
    try { $pdo->exec("ALTER TABLE `registrations` ADD CONSTRAINT `registrations_event_fk` FOREIGN KEY (`event_id`) REFERENCES `events`(`event_id`) ON DELETE CASCADE"); } catch (Throwable $e) {}
    try { $pdo->exec("ALTER TABLE `registrations` ADD CONSTRAINT `registrations_user_fk` FOREIGN KEY (`student_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE"); } catch (Throwable $e) {}

    header('Content-Type: text/plain');
    echo "Migration complete.\n";
    if ($added) {
        echo "Added columns: " . implode(', ', $added) . "\n";
    } else {
        echo "No new columns added.\n";
    }
    echo "Registrations table ensured.";
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Migration failed: ' . $e->getMessage();
}


