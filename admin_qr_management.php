<?php
/**
 * Admin QR Code Management
 * Allows admins to view and download QR codes for all registrations
 */

session_start();

// Check admin authentication
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

require_once '../config/config.php';
require_once '../config/database.php';

try {
    $db = new Database();
    $pdo = $db->getConnection();
    
    // Get all registrations with user and event details
    $stmt = $pdo->prepare("
        SELECT 
            r.id as registration_id,
            r.user_id,
            r.event_id,
            r.status,
            r.registration_date,
            r.qr_code,
            r.payment_status,
            u.first_name,
            u.last_name,
            u.email,
            u.student_id,
            e.title as event_name,
            e.event_date,
            e.start_time,
            e.venue,
            e.category
        FROM registration r
        JOIN users u ON r.user_id = u.id
        JOIN events e ON r.event_id = e.id
        ORDER BY r.registration_date DESC
        LIMIT 100
    ");
    
    $stmt->execute();
    $registrations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get statistics
    $stats_stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_registrations,
            COUNT(CASE WHEN status = 'confirmed' THEN 1 END) as confirmed_registrations,
            COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_registrations,
            COUNT(CASE WHEN qr_code IS NOT NULL THEN 1 END) as qr_codes_generated
        FROM registration
    ");
    $stats_stmt->execute();
    $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    die('Database error: ' . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - QR Code Management</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; background-color: #f5f5f5; }
        .header { background: #343a40; color: white; padding: 20px; }
        .container { padding: 20px; }
        .stats { display: flex; gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); flex: 1; text-align: center; }
        .stat-number { font-size: 2em; font-weight: bold; color: #007bff; }
        .stat-label { color: #666; margin-top: 5px; }
        .table-container { background: white; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); overflow: hidden; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f8f9fa; font-weight: bold; }
        .status { padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: bold; }
        .status-confirmed { background: #d4edda; color: #155724; }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-cancelled { background: #f8d7da; color: #721c24; }
        .btn { padding: 6px 12px; text-decoration: none; border-radius: 4px; font-size: 12px; margin: 2px; display: inline-block; }
        .btn-primary { background: #007bff; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn-info { background: #17a2b8; color: white; }
        .btn:hover { opacity: 0.8; }
        .search-box { margin-bottom: 20px; }
        .search-box input { padding: 10px; border: 1px solid #ddd; border-radius: 4px; width: 300px; }
        .qr-indicator { width: 20px; height: 20px; border-radius: 50%; display: inline-block; }
        .qr-yes { background: #28a745; }
        .qr-no { background: #dc3545; }
    </style>
</head>
<body>
    <div class="header">
        <h1>👑 Admin - QR Code Management</h1>
        <p>Manage QR codes for all event registrations</p>
    </div>
    
    <div class="container">
        <!-- Statistics -->
        <div class="stats">
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total_registrations']; ?></div>
                <div class="stat-label">Total Registrations</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['confirmed_registrations']; ?></div>
                <div class="stat-label">Confirmed</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['pending_registrations']; ?></div>
                <div class="stat-label">Pending</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['qr_codes_generated']; ?></div>
                <div class="stat-label">QR Codes Generated</div>
            </div>
        </div>
        
        <!-- Search -->
        <div class="search-box">
            <input type="text" id="searchInput" placeholder="Search by name, email, or event..." onkeyup="filterTable()">
        </div>
        
        <!-- Registrations Table -->
        <div class="table-container">
            <table id="registrationsTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Participant</th>
                        <th>Event</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>QR</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($registrations as $reg): ?>
                    <tr>
                        <td><?php echo $reg['registration_id']; ?></td>
                        <td>
                            <strong><?php echo htmlspecialchars($reg['first_name'] . ' ' . $reg['last_name']); ?></strong><br>
                            <small><?php echo htmlspecialchars($reg['email']); ?></small>
                            <?php if ($reg['student_id']): ?>
                                <br><small>ID: <?php echo htmlspecialchars($reg['student_id']); ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong><?php echo htmlspecialchars($reg['event_name']); ?></strong><br>
                            <small><?php echo ucfirst($reg['category']); ?></small>
                        </td>
                        <td>
                            <?php echo date('M j, Y', strtotime($reg['event_date'])); ?><br>
                            <?php if ($reg['start_time']): ?>
                                <small><?php echo date('g:i A', strtotime($reg['start_time'])); ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="status status-<?php echo strtolower($reg['status']); ?>">
                                <?php echo strtoupper($reg['status']); ?>
                            </span>
                        </td>
                        <td>
                            <span class="qr-indicator <?php echo $reg['qr_code'] ? 'qr-yes' : 'qr-no'; ?>" 
                                  title="<?php echo $reg['qr_code'] ? 'QR Code Available' : 'No QR Code'; ?>"></span>
                        </td>
                        <td>
                            <a href="../qr_preview.php?registration_id=<?php echo $reg['registration_id']; ?>" 
                               class="btn btn-info" target="_blank">
                                👁️ Preview
                            </a>
                            
                            <a href="../download_qr.php?registration_id=<?php echo $reg['registration_id']; ?>" 
                               class="btn btn-success" target="_blank">
                                💾 Download
                            </a>
                            
                            <?php if ($reg['status'] === 'confirmed'): ?>
                                <button onclick="generateBulkQR([<?php echo $reg['registration_id']; ?>])" 
                                        class="btn btn-primary">
                                    📱 Generate
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Bulk Actions -->
        <div style="margin-top: 20px; text-align: center;">
            <button onclick="generateAllQR()" class="btn btn-primary" style="padding: 10px 20px; font-size: 14px;">
                📱 Generate All Missing QR Codes
            </button>
            
            <button onclick="downloadAllQR()" class="btn btn-success" style="padding: 10px 20px; font-size: 14px;">
                💾 Download All QR Codes (ZIP)
            </button>
        </div>
    </div>
    
    <script>
    function filterTable() {
        const input = document.getElementById('searchInput');
        const filter = input.value.toLowerCase();
        const table = document.getElementById('registrationsTable');
        const rows = table.getElementsByTagName('tr');
        
        for (let i = 1; i < rows.length; i++) {
            const row = rows[i];
            const text = row.textContent.toLowerCase();
            
            if (text.includes(filter)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        }
    }
    
    function generateBulkQR(registrationIds) {
        registrationIds.forEach(id => {
            // Pre-generate QR codes by making requests
            fetch(`../download_qr.php?registration_id=${id}`)
                .then(response => {
                    if (response.ok) {
                        console.log(`QR generated for registration ${id}`);
                        // Update UI to show QR is available
                        location.reload();
                    }
                })
                .catch(error => console.error('Error generating QR:', error));
        });
        
        alert('QR code generation started. Page will refresh when complete.');
    }
    
    function generateAllQR() {
        if (confirm('Generate QR codes for all confirmed registrations?')) {
            const rows = document.querySelectorAll('#registrationsTable tbody tr');
            const registrationIds = [];
            
            rows.forEach(row => {
                const statusCell = row.querySelector('.status');
                if (statusCell && statusCell.textContent.trim() === 'CONFIRMED') {
                    const idCell = row.querySelector('td:first-child');
                    if (idCell) {
                        registrationIds.push(parseInt(idCell.textContent));
                    }
                }
            });
            
            generateBulkQR(registrationIds);
        }
    }
    
    function downloadAllQR() {
        alert('Bulk download feature coming soon! For now, download individual QR codes.');
    }
    </script>
</body>
</html>
