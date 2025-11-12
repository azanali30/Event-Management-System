<?php
$page_title = "Gallery";
$page_description = "Explore photos and videos from our amazing events";
$additional_css = ['gallery.css', 'image-placeholders.css'];

require_once '../config/config.php';
require_once '../config/database.php';

// Get database connection
$db = new Database();
$pdo = $db->getConnection();

// Fetch gallery items from database
$gallery_items = [];
if ($pdo) {
    try {
        // Get approved gallery items with event information
        $stmt = $pdo->prepare("
            SELECT
                g.id,
                g.title,
                g.description,
                g.file_path,
                g.file_type,
                g.category,
                g.upload_date,
                e.title as event_title
            FROM gallery g
            LEFT JOIN events e ON g.event_id = e.id
            WHERE g.status = 'approved'
            ORDER BY g.upload_date DESC
        ");
        $stmt->execute();
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Format the data for display
        foreach ($items as $item) {
            $gallery_items[] = [
                'id' => $item['id'],
                'title' => $item['title'],
                'description' => $item['description'],
                'category' => $item['category'] ?: 'general',
                'type' => $item['file_type'],
                'event' => $item['event_title'] ?: 'General',
                'image' => $item['file_path'] ?: 'https://images.unsplash.com/photo-1540575467063-178a50c2df87?w=400&h=300&fit=crop&crop=center'
            ];
        }

    } catch (Exception $e) {
        error_log("Database error in gallery.php: " . $e->getMessage());
    }
}

// Fallback to sample data if no gallery items found
if (empty($gallery_items)) {
    $gallery_items = [
        [
            'id' => 1,
            'title' => 'Tech Symposium Keynote',
            'description' => 'Industry leaders sharing insights on emerging technologies',
            'category' => 'technical',
            'type' => 'image',
            'event' => 'Annual Tech Symposium 2024',
            'image' => 'https://images.unsplash.com/photo-1540575467063-178a50c2df87?w=400&h=300&fit=crop&crop=center'
        ],
        [
            'id' => 2,
            'title' => 'Basketball Championship Finals',
            'description' => 'Intense final match of the inter-college basketball tournament',
            'category' => 'sports',
            'type' => 'image',
            'event' => 'Sports Championship 2024',
            'image' => 'https://images.unsplash.com/photo-1546519638-68e109498ffc?w=400&h=300&fit=crop&crop=center'
        ]
    ];
}

// Filter by category
$category_filter = isset($_GET['category']) ? sanitize($_GET['category']) : '';
if ($category_filter) {
    $gallery_items = array_filter($gallery_items, function($item) use ($category_filter) {
        return $item['category'] === $category_filter;
    });
}

include '../includes/pages-header.php';
?>

    <main class="main-content">
        <!-- Page Header -->
        <section class="page-header">
            <div class="container">
                <div class="header-content">
                    <h1 class="page-title animate-fade-in-up">Event Gallery</h1>
                    <p class="page-subtitle animate-fade-in-up animate-delay-200">
                        Relive the memorable moments from our amazing campus events through photos and videos.
                    </p>
                </div>
            </div>
        </section>

        <!-- Gallery Filter -->
        <section class="gallery-filter">
            <div class="container">
                <div class="filter-tabs">
                    <a href="gallery.php" class="filter-tab <?php echo !$category_filter ? 'active' : ''; ?>">All</a>
                    <a href="?category=technical" class="filter-tab <?php echo $category_filter === 'technical' ? 'active' : ''; ?>">Technical</a>
                    <a href="?category=sports" class="filter-tab <?php echo $category_filter === 'sports' ? 'active' : ''; ?>">Sports</a>
                    <a href="?category=cultural" class="filter-tab <?php echo $category_filter === 'cultural' ? 'active' : ''; ?>">Cultural</a>
                    <a href="?category=workshop" class="filter-tab <?php echo $category_filter === 'workshop' ? 'active' : ''; ?>">Workshop</a>
                </div>
            </div>
        </section>

        <!-- Gallery Grid -->
        <section class="gallery-grid">
            <div class="container">
                <?php if (empty($gallery_items)): ?>
                    <div class="no-items">
                        <div class="no-items-icon">
                            <i data-lucide="image"></i>
                        </div>
                        <h3>No Gallery Items Found</h3>
                        <p>There are no gallery items to display at the moment.</p>
                    </div>
                <?php else: ?>
                    <div class="gallery-masonry">
                        <?php foreach ($gallery_items as $item): ?>
                            <div class="gallery-item" data-category="<?php echo htmlspecialchars($item['category']); ?>">
                                <div class="gallery-card">
                                    <div class="gallery-image">
                                        <img src="<?php echo htmlspecialchars($item['image']); ?>"
                                             alt="<?php echo htmlspecialchars($item['title']); ?>"
                                             loading="lazy">
                                        <?php if ($item['type'] === 'video'): ?>
                                            <div class="video-overlay">
                                                <i data-lucide="play"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="gallery-content">
                                        <h3 class="gallery-title"><?php echo htmlspecialchars($item['title']); ?></h3>
                                        <p class="gallery-description"><?php echo htmlspecialchars($item['description']); ?></p>
                                        <div class="gallery-meta">
                                            <span class="gallery-event"><?php echo htmlspecialchars($item['event']); ?></span>
                                            <span class="gallery-category category-<?php echo $item['category']; ?>">
                                                <?php echo ucfirst($item['category']); ?>
                                            </span>
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



    
    <main class="main-content">
        <!-- Page Header -->
        <section class="page-header">
            <div class="container">
                <div class="header-content">
                    <h1 class="page-title animate-fade-in-up">Event Gallery</h1>
                    <p class="page-subtitle animate-fade-in-up animate-delay-200">
                        Relive the memorable moments from our amazing campus events through photos and videos.
                    </p>
                </div>
            </div>
        </section>

        <!-- Gallery Filter -->
        <section class="gallery-filter">
            <div class="container">
                <div class="filter-tabs">
                    <a href="gallery-working.php" class="filter-tab <?php echo !$category_filter ? 'active' : ''; ?>">All</a>
                    <a href="?category=technical" class="filter-tab <?php echo $category_filter === 'technical' ? 'active' : ''; ?>">Technical</a>
                    <a href="?category=sports" class="filter-tab <?php echo $category_filter === 'sports' ? 'active' : ''; ?>">Sports</a>
                    <a href="?category=cultural" class="filter-tab <?php echo $category_filter === 'cultural' ? 'active' : ''; ?>">Cultural</a>
                    <a href="?category=workshop" class="filter-tab <?php echo $category_filter === 'workshop' ? 'active' : ''; ?>">Workshop</a>
                </div>
            </div>
        </section>

        <!-- Gallery Grid -->
        <section class="gallery-grid">
            <div class="container">
                <?php if (empty($gallery_items)): ?>
                    <div class="no-items">
                        <div class="no-items-icon">
                            <i data-lucide="image"></i>
                        </div>
                        <h3>No Media Found</h3>
                        <p>No photos or videos found for the selected category.</p>
                        <a href="gallery-working.php" class="btn btn-primary">View All</a>
                    </div>
                <?php else: ?>
                    <div class="masonry-gallery">
                        <?php foreach ($gallery_items as $index => $item):
                            // Define different sizes for masonry effect
                            $sizes = ['large', 'medium', 'small', 'wide', 'tall'];
                            $size_class = $sizes[$index % count($sizes)];
                        ?>
                            <div class="gallery-item gallery-item-<?php echo $size_class; ?> ken42-card hover-lift animate-fade-in-up animate-delay-<?php echo ($index % 4 + 1) * 100; ?>" data-category="<?php echo $item['category']; ?>">
                                <div class="gallery-card">
                                    <div class="gallery-media">
                                        <img src="<?php echo $item['image']; ?>" alt="<?php echo htmlspecialchars($item['title']); ?>" class="gallery-img">

                                        <?php if ($item['type'] === 'video'): ?>
                                            <div class="video-overlay">
                                                <div class="play-button">
                                                    <i data-lucide="play"></i>
                                                </div>
                                            </div>
                                        <?php endif; ?>

                                        <div class="gallery-overlay">
                                            <div class="gallery-content">
                                                <h4 class="gallery-title"><?php echo $item['title']; ?></h4>
                                                <p class="gallery-event"><?php echo $item['event']; ?></p>
                                                <div class="gallery-actions">
                                                    <?php if ($item['type'] === 'video'): ?>
                                                        <button class="gallery-action" onclick="alert('Video player would open here')">
                                                            <i data-lucide="play"></i>
                                                        </button>
                                                    <?php else: ?>
                                                        <button class="gallery-action" onclick="alert('Image viewer would open here')">
                                                            <i data-lucide="maximize-2"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                    <button class="gallery-action" onclick="alert('Download feature would work here')">
                                                        <i data-lucide="download"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="text-center mt-5">
                        <button class="btn btn-primary" onclick="alert('Load more images functionality would be implemented here')">
                            <i data-lucide="image"></i>
                            Load More Images
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </main>

<?php include '../includes/footer.php'; ?>
