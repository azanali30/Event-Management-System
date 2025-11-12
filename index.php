<?php
$page_title = "Home";
$page_description = "Welcome to College Event Management System - Your gateway to campus events";
$additional_css = ['home.css', 'image-placeholders.css'];

require_once 'config/config.php';
require_once 'config/database.php';

// Get database connection
$db = new Database();
$pdo = $db->getConnection();

// Get featured events from database
$featured_events = [];
if ($pdo) {
    try {
        // Get featured and approved events, limit to 6 for home page
        $stmt = $pdo->prepare("
            SELECT
                id,
                title,
                description,
                event_date,
                start_time,
                venue,
                category,
                max_participants,
                current_participants,
                image,
                registration_fee
            FROM events
            WHERE status = 'approved' AND featured = 1
            ORDER BY event_date ASC
            LIMIT 6
        ");
        $stmt->execute();
        $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Format the data for display
        foreach ($events as $event) {
            $featured_events[] = [
                'id' => $event['id'],
                'title' => $event['title'],
                'description' => $event['description'],
                'date' => $event['event_date'],
                'time' => date('H:i', strtotime($event['start_time'])),
                'venue' => $event['venue'],
                'category' => $event['category'],
                'image' => $event['image'] ?: 'https://images.unsplash.com/photo-1540575467063-178a50c2df87?w=400&h=250&fit=crop&crop=center',
                'seats_available' => $event['max_participants'] - $event['current_participants'],
                'featured' => true
            ];
        }

    } catch (Exception $e) {
        error_log("Database error in index.php: " . $e->getMessage());
    }
}

// Fallback to sample data if no events found
if (empty($featured_events)) {
    $featured_events = [
        [
            'id' => 1,
            'title' => 'Annual Tech Symposium 2024',
            'description' => 'Join industry leaders and tech innovators for cutting-edge workshops, keynote sessions, and networking opportunities.',
            'date' => '2024-03-15',
            'time' => '09:00',
            'venue' => 'Main Auditorium',
            'category' => 'technical',
            'image' => 'https://images.unsplash.com/photo-1540575467063-178a50c2df87?w=400&h=250&fit=crop&crop=center',
            'seats_available' => 150,
            'featured' => true
        ]
    ];
}

$announcements = [
    [
        'title' => 'Registration Open for Spring Events',
        'content' => 'Registration is now open for all spring semester events. Don\'t miss out on exciting opportunities!',
        'date' => '2024-02-15',
        'type' => 'info'
    ],
    [
        'title' => 'New QR Code Check-In System',
        'content' => 'We\'ve introduced a new QR code system for event check-ins. Faster and more convenient!',
        'date' => '2024-02-10',
        'type' => 'success'
    ]
];
include 'includes/header.php';
?>

    <main class="main-content">

<!-- Hero Section -->
<section class="hero animated-gradient">
    <div class="hero-video-background">
        <video autoplay muted loop playsinline class="hero-video" id="heroVideo" preload="auto" style="display: block;">
            <!-- Working test video -->
            <source src="assets/videos/video.mp4" type="video/mp4">
            <!-- Online fallback video -->
            <source src="assets/videos/video.mp4" type="video/mp4">
        </video>
        <div class="hero-video-overlay"></div>
        <button class="video-control-btn hidden" id="videoControlBtn" title="Pause/Play Video">
            <i data-lucide="pause" id="videoControlIcon"></i>
        </button>
    </div>
    <div class="hero-background">
        <div class="hero-overlay"></div>
    </div>
    <div class="container">
        <div class="hero-content">
            <h1 class="hero-title animate-fade-in-up">
                Welcome to College Event Management
            </h1>
            <p class="hero-subtitle animate-fade-in-up animate-delay-200">
                Your centralized platform for discovering, registering, and participating in campus events.
                Never miss an opportunity to learn, connect, and grow.
            </p>
            <div class="hero-actions animate-fade-in-up animate-delay-400">
                <a href="pages/events-working.php" class="btn btn-primary btn-lg btn-animated hover-lift">
                    <i data-lucide="calendar"></i>
                    Explore Events
                </a>
                <?php if (!isLoggedIn()): ?>
                    <a href="pages/login.php" class="btn btn-primary btn-lg btn-animated hover-lift">
                        <i data-lucide="log-in"></i>
                        Get Started
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<!-- Stats Section -->
<section class="stats-section fade-in-section">
    <div class="container">
        <div class="stats-header animate-fade-in-up">
            <h2 class="stats-title">Trusted by Students & Faculty</h2>
            <p class="stats-subtitle">Join thousands who are already part of our vibrant campus community</p>
        </div>

        <div class="stats-grid">
            <div class="stat-card ken42-card animate-fade-in-up animate-delay-100">
                <div class="stat-icon">
                    <i data-lucide="calendar-days"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-number">150+</div>
                    <div class="stat-label">Events Hosted</div>
                    <div class="stat-description">Successfully organized events across all categories</div>
                </div>
            </div>

            <div class="stat-card ken42-card animate-fade-in-up animate-delay-200">
                <div class="stat-icon">
                    <i data-lucide="users"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-number">2,500+</div>
                    <div class="stat-label">Active Students</div>
                    <div class="stat-description">Engaged participants from all departments</div>
                </div>
            </div>

            <div class="stat-card ken42-card animate-fade-in-up animate-delay-300">
                <div class="stat-icon">
                    <i data-lucide="award"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-number">1,200+</div>
                    <div class="stat-label">Certificates Issued</div>
                    <div class="stat-description">Digital certificates for skill recognition</div>
                </div>
            </div>

            <div class="stat-card ken42-card animate-fade-in-up animate-delay-400">
                <div class="stat-icon">
                    <i data-lucide="star"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-number">4.8/5</div>
                    <div class="stat-label">Average Rating</div>
                    <div class="stat-description">Excellent feedback from participants</div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Featured Events Section -->
<section class="featured-events fade-in-section">
    <div class="container">
        <div class="section-header animate-fade-in-up">
            <h2 class="section-title">Featured Events</h2>
            <p class="section-subtitle">Don't miss these upcoming exciting events</p>
        </div>

        <div class="row">
            <?php foreach ($featured_events as $index => $event): ?>
                <div class="col-4">
                    <div class="event-card ken42-card hover-lift animate-fade-in-up animate-delay-<?php echo ($index + 1) * 100; ?>">
                        <div class="event-image">
                            <img src="<?php echo $event['image']; ?>" alt="<?php echo htmlspecialchars($event['title']); ?>" class="event-img">
                            <div class="event-category event-category-<?php echo $event['category']; ?>"><?php echo ucfirst($event['category']); ?></div>
                        </div>
                        <div class="event-content">
                            <h3 class="event-title"><?php echo $event['title']; ?></h3>
                            <p class="event-description"><?php echo $event['description']; ?></p>

                            <div class="event-meta">
                                <div class="event-meta-item">
                                    <i data-lucide="calendar"></i>
                                    <span><?php echo formatDate($event['date']); ?></span>
                                </div>
                                <div class="event-meta-item">
                                    <i data-lucide="clock"></i>
                                    <span><?php echo date('g:i A', strtotime($event['time'])); ?></span>
                                </div>
                                <div class="event-meta-item">
                                    <i data-lucide="map-pin"></i>
                                    <span><?php echo $event['venue']; ?></span>
                                </div>
                                <div class="event-meta-item">
                                    <i data-lucide="users"></i>
                                    <span><?php echo $event['seats_available']; ?> seats available</span>
                                </div>
                            </div>

                            <div class="event-actions">
                                <a href="pages/event-details.php?id=<?php echo $event['id']; ?>" class="btn btn-primary">
                                    View Details
                                </a>
                                <?php if (isLoggedIn()): ?>
                                    <a href="pages/register-event.php?id=<?php echo $event['id']; ?>" class="btn btn-secondary">
                                        Register
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="text-center mt-4">
            <a href="pages/events.php" class="btn btn-primary">
                <i data-lucide="arrow-right"></i>
                View All Events
            </a>
        </div>
    </div>
</section>

<!-- Event Gallery Preview Section -->
<section class="gallery-preview">
    <div class="container">
        <div class="section-header">
            <h2 class="section-title">Event Highlights</h2>
            <p class="section-subtitle">Capturing memorable moments from our amazing events</p>
        </div>

        <div class="gallery-grid">
            <?php
            $gallery_items = [
                ['type' => 'sports', 'title' => 'Basketball Championship Finals', 'event' => 'Sports Week 2024', 'image' => 'https://images.unsplash.com/photo-1546519638-68e109498ffc?w=300&h=200&fit=crop&crop=center'],
                ['type' => 'cultural', 'title' => 'Traditional Dance Performance', 'event' => 'Cultural Fest', 'image' => 'https://images.unsplash.com/photo-1508700115892-45ecd05ae2ad?w=300&h=200&fit=crop&crop=center'],
                ['type' => 'technical', 'title' => 'Coding Competition', 'event' => 'Tech Symposium', 'image' => 'https://images.unsplash.com/photo-1517077304055-6e89abbf09b0?w=300&h=200&fit=crop&crop=center'],
                ['type' => 'workshop', 'title' => 'Leadership Workshop', 'event' => 'Skill Development', 'image' => 'https://images.unsplash.com/photo-1531482615713-2afd69097998?w=300&h=200&fit=crop&crop=center'],
                ['type' => 'cultural', 'title' => 'Music Concert', 'event' => 'Harmony Night', 'image' => 'https://images.unsplash.com/photo-1493225457124-a3eb161ffa5f?w=300&h=200&fit=crop&crop=center'],
                ['type' => 'sports', 'title' => 'Football Tournament', 'event' => 'Inter-College Sports', 'image' => 'https://images.unsplash.com/photo-1431324155629-1a6deb1dec8d?w=300&h=200&fit=crop&crop=center'],
                ['type' => 'technical', 'title' => 'Innovation Showcase', 'event' => 'Tech Exhibition', 'image' => 'https://images.unsplash.com/photo-1559136555-9303baea8ebd?w=300&h=200&fit=crop&crop=center'],
                ['type' => 'cultural', 'title' => 'Art Exhibition', 'event' => 'Creative Arts Fest', 'image' => 'https://images.unsplash.com/photo-1578662996442-48f60103fc96?w=300&h=200&fit=crop&crop=center'],
                ['type' => 'workshop', 'title' => 'Entrepreneurship Seminar', 'event' => 'Business Workshop', 'image' => 'https://images.unsplash.com/photo-1552664730-d307ca884978?w=300&h=200&fit=crop&crop=center'],
                ['type' => 'sports', 'title' => 'Cricket Match', 'event' => 'Sports Championship', 'image' => 'https://images.unsplash.com/photo-1540747913346-19e32dc3e97e?w=300&h=200&fit=crop&crop=center'],
                ['type' => 'cultural', 'title' => 'Drama Performance', 'event' => 'Theatre Festival', 'image' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=300&h=200&fit=crop&crop=center'],
                ['type' => 'technical', 'title' => 'Robotics Competition', 'event' => 'Tech Challenge', 'image' => 'https://images.unsplash.com/photo-1485827404703-89b55fcc595e?w=300&h=200&fit=crop&crop=center']
            ];

            foreach (array_slice($gallery_items, 0, 8) as $index => $item):
            ?>
                <div class="gallery-item gallery-item-<?php echo ($index % 4) + 1; ?>">
                    <div class="gallery-card">
                        <div class="gallery-image">
                            <img src="<?php echo $item['image']; ?>" alt="<?php echo htmlspecialchars($item['title']); ?>" class="gallery-img">
                            <div class="gallery-overlay">
                                <div class="gallery-info">
                                    <h4 class="gallery-title"><?php echo $item['title']; ?></h4>
                                    <p class="gallery-event"><?php echo $item['event']; ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="text-center mt-4">
            <a href="pages/gallery-working.php" class="btn btn-primary">
                <i data-lucide="image"></i>
                View Full Gallery
            </a>
        </div>
    </div>
</section>

<!-- Announcements Section -->
<section class="announcements">
    <div class="container">
        <div class="section-header">
            <h2 class="section-title">Latest Announcements</h2>
            <p class="section-subtitle">Stay updated with important news and updates</p>
        </div>

        <div class="row">
            <?php foreach ($announcements as $announcement): ?>
                <div class="col-6">
                    <div class="announcement-card announcement-<?php echo $announcement['type']; ?>">
                        <div class="announcement-header">
                            <h4 class="announcement-title"><?php echo $announcement['title']; ?></h4>
                            <span class="announcement-date"><?php echo formatDate($announcement['date']); ?></span>
                        </div>
                        <p class="announcement-content"><?php echo $announcement['content']; ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Testimonials Section -->
<section class="testimonials">
    <div class="container">
        <div class="section-header">
            <h2 class="section-title">What Students Say</h2>
            <p class="section-subtitle">Hear from our community about their event experiences</p>
        </div>

        <div class="testimonials-grid">
            <div class="testimonial-card">
                <div class="testimonial-content">
                    <p class="testimonial-text">
                        "The event management system has completely transformed how I discover and participate in campus events.
                        The QR code check-in is so convenient!"
                    </p>
                </div>
                <div class="testimonial-author">
                    <div class="author-avatar">
                        <div class="avatar-placeholder">👩‍🎓</div>
                    </div>
                    <div class="author-info">
                        <h4 class="author-name">Sarah Johnson</h4>
                        <p class="author-role">Computer Science Student</p>
                    </div>
                </div>
            </div>

            <div class="testimonial-card">
                <div class="testimonial-content">
                    <p class="testimonial-text">
                        "As an event organizer, this platform has made my job so much easier. The analytics and
                        participant management features are incredible."
                    </p>
                </div>
                <div class="testimonial-author">
                    <div class="author-avatar">
                        <div class="avatar-placeholder">👨‍🏫</div>
                    </div>
                    <div class="author-info">
                        <h4 class="author-name">Dr. Michael Chen</h4>
                        <p class="author-role">Faculty Coordinator</p>
                    </div>
                </div>
            </div>

            <div class="testimonial-card">
                <div class="testimonial-content">
                    <p class="testimonial-text">
                        "I love how easy it is to find events that match my interests. The notification system
                        ensures I never miss important events."
                    </p>
                </div>
                <div class="testimonial-author">
                    <div class="author-avatar">
                        <div class="avatar-placeholder">👨‍🎓</div>
                    </div>
                    <div class="author-info">
                        <h4 class="author-name">Alex Rodriguez</h4>
                        <p class="author-role">Business Student</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Features Section -->
<section class="features">
    <div class="container">
        <div class="section-header">
            <h2 class="section-title">Why Choose Our Platform?</h2>
            <p class="section-subtitle">Discover the benefits of our comprehensive event management system</p>
        </div>

        <div class="row">
            <div class="col-4">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i data-lucide="smartphone"></i>
                    </div>
                    <h3 class="feature-title">QR Code Check-in</h3>
                    <p class="feature-description">
                        Quick and contactless event check-in using QR codes. No more long queues or manual attendance.
                    </p>
                </div>
            </div>

            <div class="col-4">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i data-lucide="award"></i>
                    </div>
                    <h3 class="feature-title">Digital Certificates</h3>
                    <p class="feature-description">
                        Automatically generated and downloadable e-certificates for all event participants.
                    </p>
                </div>
            </div>

            <div class="col-4">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i data-lucide="bell"></i>
                    </div>
                    <h3 class="feature-title">Smart Notifications</h3>
                    <p class="feature-description">
                        Get timely reminders and updates about your registered events via email and push notifications.
                    </p>
                </div>
            </div>

            <div class="col-4">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i data-lucide="calendar-plus"></i>
                    </div>
                    <h3 class="feature-title">Calendar Integration</h3>
                    <p class="feature-description">
                        Sync events directly to your Google, Outlook, or Apple Calendar with one click.
                    </p>
                </div>
            </div>

            <div class="col-4">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i data-lucide="star"></i>
                    </div>
                    <h3 class="feature-title">Feedback System</h3>
                    <p class="feature-description">
                        Rate and review events to help improve future experiences and help others make informed decisions.
                    </p>
                </div>
            </div>

            <div class="col-4">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i data-lucide="share-2"></i>
                    </div>
                    <h3 class="feature-title">Social Sharing</h3>
                    <p class="feature-description">
                        Share interesting events with friends on social media platforms and spread the word.
                    </p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- CTA Section -->
<section class="cta-section">
    <div class="container">
        <div class="cta-content">
            <h2 class="cta-title">Ready to Get Started?</h2>
            <p class="cta-subtitle">
                Join thousands of students who are already using our platform to discover and participate in amazing events.
            </p>
            <div class="cta-actions">
                <?php if (!isLoggedIn()): ?>
                    <a href="pages/login.php" class="btn btn-primary btn-lg">
                        <i data-lucide="log-in"></i>
                        Login
                    </a>
                    <a href="pages/events.php" class="btn btn-primary btn-lg">
                        <i data-lucide="eye"></i>
                        Browse Events
                    </a>
                <?php else: ?>
                    <a href="pages/dashboard.php" class="btn btn-primary btn-lg">
                        <i data-lucide="layout-dashboard"></i>
                        Go to Dashboard
                    </a>
                    <a href="pages/events.php" class="btn btn-primary btn-lg">
                        <i data-lucide="calendar"></i>
                        Find Events
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<script>
// Hero Video Background Handler
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM loaded, initializing video...');

    const heroVideo = document.getElementById('heroVideo');
    const heroBackground = document.querySelector('.hero-background');
    const videoControlBtn = document.getElementById('videoControlBtn');
    const videoControlIcon = document.getElementById('videoControlIcon');
    let isVideoPlaying = true;

    if (heroVideo) {
        console.log('Hero video element found');

        // Handle video ready to play
        heroVideo.addEventListener('canplay', function() {
            console.log('Video can play - showing video');
            heroVideo.setAttribute('data-loaded', 'true');

            // Slow down video playback speed
            heroVideo.playbackRate = 0.7; // 70% of normal speed

            if (heroBackground) {
                heroBackground.style.opacity = '0.3';
            }
            if (videoControlBtn) {
                videoControlBtn.classList.remove('hidden');
            }
        });

        // Handle video loaded data
        heroVideo.addEventListener('loadeddata', function() {
            console.log('Video data loaded');
            heroVideo.setAttribute('data-loaded', 'true');

            // Slow down video playback speed
            heroVideo.playbackRate = 0.7; // 70% of normal speed

            if (heroBackground) {
                heroBackground.style.opacity = '0.3';
            }
            if (videoControlBtn) {
                videoControlBtn.classList.remove('hidden');
            }
        });

        heroVideo.addEventListener('error', function(e) {
            console.log('Video error:', e);
            if (heroBackground) {
                heroBackground.style.opacity = '1';
            }
        });

        // Force load
        heroVideo.load();

    } else {
        console.log('Hero video element not found');
    }

    // Video control button functionality
    if (videoControlBtn && heroVideo) {
        videoControlBtn.addEventListener('click', function() {
            if (isVideoPlaying) {
                heroVideo.pause();
                videoControlIcon.setAttribute('data-lucide', 'play');
                isVideoPlaying = false;
            } else {
                heroVideo.play();
                videoControlIcon.setAttribute('data-lucide', 'pause');
                isVideoPlaying = true;
            }
            // Refresh lucide icons
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        });
    }

    // Video control button functionality
    if (videoControlBtn && heroVideo) {
        videoControlBtn.addEventListener('click', function() {
            if (isVideoPlaying) {
                heroVideo.pause();
                videoControlIcon.setAttribute('data-lucide', 'play');
                isVideoPlaying = false;
            } else {
                heroVideo.play();
                videoControlIcon.setAttribute('data-lucide', 'pause');
                isVideoPlaying = true;
            }
            // Refresh lucide icons
            lucide.createIcons();
        });
    }
});
</script>

<?php include 'includes/footer.php'; ?>