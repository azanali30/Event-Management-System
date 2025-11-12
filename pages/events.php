<?php
$page_title = "Events";
$page_description = "Discover and register for exciting college events";
$additional_css = ['events.css', 'image-placeholders.css'];

require_once '../config/config.php';
require_once '../config/database.php';

// Get database connection
$db = new Database();
$pdo = $db->getConnection();

// Fetch events from database
$events = [];
if ($pdo) {
    try {
        // Get events, adapt to varying column names and include pending
        $stmt = $pdo->prepare("
            SELECT
                event_id as id,
                title,
                description,
                event_date,
                event_time as start_time,
                venue,
                category
            FROM events
            WHERE status IN ('approved','pending')
            ORDER BY event_date ASC
        ");
        $stmt->execute();
        $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Format the data for display
        foreach ($events as &$event) {
            $event['date'] = $event['event_date'];
            $event['time'] = $event['start_time'] ? date('H:i', strtotime($event['start_time'])) : '';
            // No participant counts or images in this schema; handle defaults where used later
            if (empty($event['image'])) {
                $event['image'] = 'https://images.unsplash.com/photo-1540575467063-178a50c2df87?w=400&h=250&fit=crop&crop=center';
            }
        }

    } catch (Exception $e) {
        // Fallback to sample data if database fails
        error_log("Database error in events.php: " . $e->getMessage());
        $events = [
            [
                'id' => 1,
                'title' => 'Annual Tech Symposium 2024',
                'description' => 'Join industry leaders and tech innovators for cutting-edge workshops, keynote sessions, and networking opportunities.',
                'date' => '2024-03-15',
                'time' => '09:00',
                'venue' => 'Main Auditorium',
                'category' => 'technical',
                'seats_available' => 150,
                'featured' => true,
                'image' => 'https://images.unsplash.com/photo-1540575467063-178a50c2df87?w=400&h=250&fit=crop&crop=center'
            ]
        ];
    }
}

// Filter events
$category_filter = isset($_GET['category']) ? sanitize($_GET['category']) : '';
$search_query = isset($_GET['search']) ? sanitize($_GET['search']) : '';

if ($category_filter) {
    $events = array_filter($events, function($event) use ($category_filter) {
        return $event['category'] === $category_filter;
    });
}

if ($search_query) {
    $events = array_filter($events, function($event) use ($search_query) {
        return stripos($event['title'], $search_query) !== false || 
               stripos($event['description'], $search_query) !== false;
    });
}
include '../includes/pages-header.php';
?>
    
    <main class="main-content">
        <!-- Page Header -->
        <section class="page-header">
            <div class="container">
                <div class="header-content">
                    <h1 class="page-title animate-fade-in-up">Discover Amazing Events</h1>
                    <p class="page-subtitle animate-fade-in-up animate-delay-200">
                        Explore a wide range of events happening on campus. From technical workshops to cultural festivals.
                    </p>
                </div>
            </div>
        </section>
<br><br>
        <!-- Events Filter -->
        <section class="events-filter">
            <div class="container">
                <div class="filter-bar">
                    <div class="search-box">
                        <form method="GET" action="">
                            <div class="search-input-group">
                                <input type="text" name="search" placeholder="Search events..." value="<?php echo $search_query; ?>" class="search-input">
                                <button type="submit" class="search-btn">
                                    <i data-lucide="search"></i>
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <div class="filter-categories">
                        <a href="events.php" class="filter-btn <?php echo !$category_filter ? 'active' : ''; ?>">
                            <i data-lucide="grid-3x3"></i>
                            All
                        </a>
                        <a href="?category=technical" class="filter-btn <?php echo $category_filter === 'technical' ? 'active' : ''; ?>">
                            <i data-lucide="cpu"></i>
                            Technical
                        </a>
                        <a href="?category=sports" class="filter-btn <?php echo $category_filter === 'sports' ? 'active' : ''; ?>">
                            <i data-lucide="trophy"></i>
                            Sports
                        </a>
                        <a href="?category=cultural" class="filter-btn <?php echo $category_filter === 'cultural' ? 'active' : ''; ?>">
                            <i data-lucide="music"></i>
                            Cultural
                        </a>
                        <a href="?category=workshop" class="filter-btn <?php echo $category_filter === 'workshop' ? 'active' : ''; ?>">
                            <i data-lucide="users"></i>
                            Workshop
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <!-- Events Grid -->
        <section class="events-grid">
            <div class="container">
                <?php if (empty($events)): ?>
                    <div class="no-events">
                        <div class="no-events-icon">
                            <i data-lucide="calendar-x"></i>
                        </div>
                        <h3>No Events Found</h3>
                        <p>No events match your current search criteria. Try adjusting your filters.</p>
                        <a href="events.php" class="btn btn-primary">View All Events</a>
                    </div>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($events as $index => $event): ?>
                            <div class="col-4">
                                <div class="event-card ken42-card hover-lift animate-fade-in-up animate-delay-<?php echo ($index % 3 + 1) * 100; ?>">
                                    <div class="event-image">
                                        <img src="<?php echo $event['image']; ?>" alt="<?php echo htmlspecialchars($event['title']); ?>" class="event-img">
                                        <div class="event-category event-category-<?php echo $event['category']; ?>">
                                            <?php echo ucfirst($event['category']); ?>
                                        </div>
                                    </div>
                                    
                                    <div class="event-content">
                                        <h3 class="event-title"><?php echo $event['title']; ?></h3>
                                        <p class="event-description"><?php echo $event['description']; ?></p>
                                        
                                        <div class="event-meta">
                                            <div class="event-date">
                                                <i data-lucide="calendar"></i>
                                                <span><?php echo date('M d, Y', strtotime($event['date'])); ?></span>
                                            </div>
                                            <div class="event-time">
                                                <i data-lucide="clock"></i>
                                                <span><?php echo !empty($event['time']) ? date('g:i A', strtotime($event['time'])) : 'TBD'; ?></span>
                                            </div>
                                            <div class="event-location">
                                                <i data-lucide="map-pin"></i>
                                                <span><?php echo $event['venue']; ?></span>
                                            </div>
                                            <div class="event-organizer">
                                                <i data-lucide="users"></i>
                                                <span><?php echo isset($event['organizer']) && $event['organizer'] !== '' ? htmlspecialchars($event['organizer']) : 'Organizing Team'; ?></span>
                                            </div>
                                        </div>
                                        
                                        <div class="event-footer">
                                            <?php if (isset($event['seats_available'])): ?>
                                                <div class="event-seats">
                                                    <i data-lucide="user-check"></i>
                                                    <span><?php echo (int)$event['seats_available']; ?> seats available</span>
                                                </div>
                                            <?php endif; ?>
                                            <div class="event-actions">
                                                <a href="register-event.php?event_id=<?php echo $event['id']; ?>" class="btn btn-primary">Register Now</a>
                                                <a href="#" class="btn btn-secondary">Learn More</a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </main>

<?php include '../includes/footer.php'; ?>
