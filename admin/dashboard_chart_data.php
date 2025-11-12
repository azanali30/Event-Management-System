<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once './_auth.php';
admin_require_login();

header('Content-Type: application/json');

$db = new Database();
$pdo = $db->getConnection();

$eventId = $_GET['event_id'] ?? 'all';

// Weekly participation data
$weeklyParticipation = [];
$weekDays = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

// Initialize with zeros
foreach ($weekDays as $day) {
    $weeklyParticipation[$day] = 0;
}

try {
    if ($eventId === 'all') {
        // Get data for all events
        $participationQuery = $pdo->query("
            SELECT 
                DAYNAME(e.event_date) as day_name,
                COUNT(DISTINCT r.id) as participation_count
            FROM events e
            LEFT JOIN registration r ON e.event_id = r.event_id
            WHERE e.event_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                AND e.event_date <= CURDATE()
                AND e.status = 'approved'
            GROUP BY DAYNAME(e.event_date), e.event_date
            ORDER BY e.event_date
        ");
    } else {
        // Get data for specific event
        $stmt = $pdo->prepare("
            SELECT 
                DAYNAME(e.event_date) as day_name,
                COUNT(DISTINCT r.id) as participation_count
            FROM events e
            LEFT JOIN registration r ON e.event_id = r.event_id
            WHERE e.event_id = ?
                AND e.event_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                AND e.event_date <= CURDATE()
                AND e.status = 'approved'
            GROUP BY DAYNAME(e.event_date), e.event_date
            ORDER BY e.event_date
        ");
        $stmt->execute([$eventId]);
        $participationQuery = $stmt;
    }
    
    $participationData = $participationQuery->fetchAll(PDO::FETCH_ASSOC);
    
    // Map the data to our week structure
    foreach ($participationData as $data) {
        $dayName = substr($data['day_name'], 0, 3); // Convert to 3-letter format
        if (in_array($dayName, $weekDays)) {
            $weeklyParticipation[$dayName] += (int)$data['participation_count'];
        }
    }
    
    echo json_encode([
        'success' => true,
        'participation' => array_values($weeklyParticipation),
        'event_id' => $eventId
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Failed to fetch chart data',
        'message' => $e->getMessage()
    ]);
}
?>