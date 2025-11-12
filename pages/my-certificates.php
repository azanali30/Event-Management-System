<?php
$page_title = "My Certificates";
$page_description = "View and download your event certificates";
$additional_css = ['events.css'];

require_once '../config/config.php';
require_once '../config/database.php';

// Check if user is logged in
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

$db = new Database();
$pdo = $db->getConnection();

// Get user's certificates
$stmt = $pdo->prepare("
    SELECT c.*, e.title as event_title, e.event_date, e.venue
    FROM certificates c
    JOIN events e ON c.event_id = e.event_id
    WHERE c.user_id = ?
    ORDER BY c.issued_date DESC
");
$stmt->execute([$_SESSION['user_id']]);
$certificates = $stmt->fetchAll(PDO::FETCH_ASSOC);

include '../includes/pages-header.php';
?>

<main class="main-content">
    <!-- Page Header -->
    <section class="page-header">
        <div class="container">
            <div class="page-header-content">
                <h1>My Certificates</h1>
                <p>View and download your event participation certificates</p>
            </div>
        </div>
    </section>

    <!-- Certificates Section -->
    <section class="certificates-section">
        <div class="container">
            <?php if (empty($certificates)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">
                        <i data-lucide="award" style="width: 64px; height: 64px;"></i>
                    </div>
                    <h3>No Certificates Yet</h3>
                    <p>You haven't received any certificates yet. Participate in events to earn certificates!</p>
                    <a href="events.php" class="btn btn-primary">Browse Events</a>
                </div>
            <?php else: ?>
                <div class="certificates-grid">
                    <?php foreach ($certificates as $cert): ?>
                        <div class="certificate-card">
                            <div class="certificate-header">
                                <div class="certificate-icon">
                                    <i data-lucide="award"></i>
                                </div>
                                <div class="certificate-status">
                                    <span class="badge success">Generated</span>
                                </div>
                            </div>
                            
                            <div class="certificate-content">
                                <h3><?php echo htmlspecialchars($cert['event_title']); ?></h3>
                                <div class="certificate-details">
                                    <div class="detail-item">
                                        <i data-lucide="calendar"></i>
                                        <span><?php echo date('M j, Y', strtotime($cert['event_date'])); ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <i data-lucide="map-pin"></i>
                                        <span><?php echo htmlspecialchars($cert['venue']); ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <i data-lucide="hash"></i>
                                        <span>ID: <?php echo htmlspecialchars($cert['certificate_code']); ?></span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="certificate-actions">
                                <a href="view-certificate.php?id=<?php echo $cert['certificate_id']; ?>" class="btn btn-primary" target="_blank">
                                    <i data-lucide="eye"></i> View Certificate
                                </a>
                                <a href="download-certificate.php?id=<?php echo $cert['certificate_id']; ?>" class="btn btn-secondary">
                                    <i data-lucide="download"></i> Download
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>
</main>

<style>
.certificates-section {
    padding: 3rem 0;
}

.certificates-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 2rem;
    margin-top: 2rem;
}

.certificate-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    overflow: hidden;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.certificate-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
}

.certificate-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 1.5rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.certificate-icon {
    font-size: 2rem;
}

.certificate-content {
    padding: 1.5rem;
}

.certificate-content h3 {
    margin: 0 0 1rem 0;
    color: #2c3e50;
    font-size: 1.25rem;
}

.certificate-details {
    margin: 1rem 0;
}

.detail-item {
    display: flex;
    align-items: center;
    margin: 0.5rem 0;
    color: #7f8c8d;
    font-size: 0.9rem;
}

.detail-item i {
    width: 16px;
    height: 16px;
    margin-right: 0.5rem;
}

.certificate-actions {
    padding: 1rem 1.5rem;
    background: #f8f9fa;
    display: flex;
    gap: 0.5rem;
}

.certificate-actions .btn {
    flex: 1;
    text-align: center;
    padding: 0.5rem 1rem;
    font-size: 0.9rem;
}

.empty-state {
    text-align: center;
    padding: 4rem 2rem;
}

.empty-state-icon {
    color: #bdc3c7;
    margin-bottom: 1rem;
}

.empty-state h3 {
    color: #2c3e50;
    margin-bottom: 0.5rem;
}

.empty-state p {
    color: #7f8c8d;
    margin-bottom: 2rem;
}
</style>

<?php include '../includes/footer.php'; ?>
