<?php
require_once 'config/database.php';

try {
    $db = new Database();
    $pdo = $db->getConnection();

    // Check registration 15
    $stmt = $pdo->prepare("SELECT registration_id, student_id, status, uid, qr_path FROM registrations WHERE registration_id = 15");
    $stmt->execute();
    $reg = $stmt->fetch();

    if ($reg) {
        echo "Registration 15 exists - Status: " . $reg['status'] . " Student ID: " . $reg['student_id'] . "\n";

        // Update it to confirmed if not already
        if ($reg['status'] !== 'confirmed') {
            $pdo->exec("UPDATE registrations SET status = 'confirmed' WHERE registration_id = 15");
            echo "Updated registration 15 to confirmed status\n";
        }

        // Generate UID and QR code if missing
        if (empty($reg['uid']) || empty($reg['qr_path'])) {
            $uid = 'USER' . strtoupper(substr(md5(uniqid()), 0, 6));
            $qr_path = 'qr_codes/' . $uid . '.png';

            $stmt = $pdo->prepare("UPDATE registrations SET uid = ?, qr_path = ? WHERE registration_id = 15");
            $stmt->execute([$uid, $qr_path]);
            echo "Generated UID: $uid and QR path: $qr_path\n";
        }
    } else {
        echo "Registration 15 does not exist. Creating it...\n";

        // Generate UID for new registration
        $uid = 'USER' . strtoupper(substr(md5(uniqid()), 0, 6));
        $qr_path = 'qr_codes/' . $uid . '.png';

        // Create registration 15
        $stmt = $pdo->prepare("INSERT INTO registrations (registration_id, student_id, event_id, status, registered_on, uid, qr_path) VALUES (15, 2, 1, 'confirmed', NOW(), ?, ?)");
        $stmt->execute([$uid, $qr_path]);
        echo "Created registration 15 for student 2, event 1 with UID: $uid\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
