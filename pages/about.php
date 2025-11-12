<?php
$page_title = "About Us";
$page_description = "Learn more about our mission to revolutionize college event management";
$additional_css = ['about.css'];
require_once '../config/config.php';
include '../includes/pages-header.php';
?>
    
    <main class="main-content">
        <!-- Hero Section -->
        <section class="about-hero">
            <div class="about-hero-content">
                <div class="container">
                    <h1 class="about-title animate-fade-in-up">About Our Platform</h1>
                    <p class="about-subtitle animate-fade-in-up animate-delay-200">
                        Revolutionizing college event management through technology and innovation
                    </p>
                </div>
            </div>
        </section>

        <!-- Mission Section -->
        <section class="mission-section">
            <div class="container">
                <div class="row">
                    <div class="col-6">
                        <div class="mission-content animate-fade-in-left">
                            <h2 class="section-title">Our Mission</h2>
                            <p class="mission-text">
                                We believe that every student deserves easy access to campus events and opportunities. 
                                Our platform bridges the gap between event organizers and participants, creating a 
                                seamless experience for everyone involved.
                            </p>
                            <p class="mission-text">
                                By centralizing event management, we eliminate the chaos of scattered announcements 
                                and manual registrations, making campus life more organized and engaging.
                            </p>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="mission-image animate-fade-in-right">
                            <img src="https://images.unsplash.com/photo-1552664730-d307ca884978?ixlib=rb-4.0.3&auto=format&fit=crop&w=600&h=400&q=80"
                                 alt="Team collaboration and mission planning"
                                 class="mission-img">
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Features Section -->
        <section class="features-section">
            <div class="container">
                <div class="section-header animate-fade-in-up">
                    <h2 class="section-title">Why Choose Our Platform?</h2>
                    <p class="section-subtitle">
                        Discover the features that make event management effortless and engaging
                    </p>
                </div>
                
                <div class="features-grid">
                    <div class="feature-item ken42-card animate-fade-in-up animate-delay-100">
                        <div class="feature-icon">
                            <i data-lucide="calendar-plus"></i>
                        </div>
                        <h3 class="feature-title">Easy Event Discovery</h3>
                        <p class="feature-description">
                            Browse and discover events by category, date, or interest. Never miss an opportunity that matters to you.
                        </p>
                    </div>
                    
                    <div class="feature-item ken42-card animate-fade-in-up animate-delay-200">
                        <div class="feature-icon">
                            <i data-lucide="qr-code"></i>
                        </div>
                        <h3 class="feature-title">QR Code Check-in</h3>
                        <p class="feature-description">
                            Quick and contactless event check-in using QR codes. Streamlined attendance tracking for organizers.
                        </p>
                    </div>
                    
                    <div class="feature-item ken42-card animate-fade-in-up animate-delay-300">
                        <div class="feature-icon">
                            <i data-lucide="award"></i>
                        </div>
                        <h3 class="feature-title">Digital Certificates</h3>
                        <p class="feature-description">
                            Automatically generated certificates for event participation. Build your portfolio with verified achievements.
                        </p>
                    </div>
                    
                    <div class="feature-item ken42-card animate-fade-in-up animate-delay-400">
                        <div class="feature-icon">
                            <i data-lucide="bell"></i>
                        </div>
                        <h3 class="feature-title">Smart Notifications</h3>
                        <p class="feature-description">
                            Get timely reminders about upcoming events, registration deadlines, and important updates.
                        </p>
                    </div>
                    
                    <div class="feature-item ken42-card animate-fade-in-up animate-delay-500">
                        <div class="feature-icon">
                            <i data-lucide="users"></i>
                        </div>
                        <h3 class="feature-title">Community Building</h3>
                        <p class="feature-description">
                            Connect with like-minded peers, join interest groups, and build lasting relationships through events.
                        </p>
                    </div>
                    
                    <div class="feature-item ken42-card animate-fade-in-up animate-delay-600">
                        <div class="feature-icon">
                            <i data-lucide="bar-chart"></i>
                        </div>
                        <h3 class="feature-title">Analytics & Insights</h3>
                        <p class="feature-description">
                            Comprehensive analytics for organizers to understand event performance and participant engagement.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Stats Section -->
        <section class="stats-section">
            <div class="container">
                <div class="stats-grid">
                    <div class="stat-item animate-scale-in animate-delay-100">
                        <div class="stat-number">150+</div>
                        <div class="stat-label">Events Hosted</div>
                    </div>
                    <div class="stat-item animate-scale-in animate-delay-200">
                        <div class="stat-number">2,500+</div>
                        <div class="stat-label">Active Students</div>
                    </div>
                    <div class="stat-item animate-scale-in animate-delay-300">
                        <div class="stat-number">1,200+</div>
                        <div class="stat-label">Certificates Issued</div>
                    </div>
                    <div class="stat-item animate-scale-in animate-delay-400">
                        <div class="stat-number">4.8/5</div>
                        <div class="stat-label">User Rating</div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Team Section -->
        <section class="team-section">
            <div class="container">
                <div class="section-header animate-fade-in-up">
                    <h2 class="section-title">Meet Our Team</h2>
                    <p class="section-subtitle">
                        The passionate individuals behind this innovative platform
                    </p>
                </div>
                
                <div class="team-grid">
                    <div class="team-member ken42-card animate-fade-in-up animate-delay-100">
                        <div class="member-avatar">
                            <i data-lucide="user"></i>
                        </div>
                        <h4 class="member-name">Dr. Sarah Johnson</h4>
                        <p class="member-role">Project Director</p>
                        <p class="member-description">
                            Leading the vision for digital transformation in campus event management.
                        </p>
                    </div>
                    
                    <div class="team-member ken42-card animate-fade-in-up animate-delay-200">
                        <div class="member-avatar">
                            <i data-lucide="user"></i>
                        </div>
                        <h4 class="member-name">Michael Chen</h4>
                        <p class="member-role">Lead Developer</p>
                        <p class="member-description">
                            Building robust and scalable solutions for seamless event management.
                        </p>
                    </div>
                    
                    <div class="team-member ken42-card animate-fade-in-up animate-delay-300">
                        <div class="member-avatar">
                            <i data-lucide="user"></i>
                        </div>
                        <h4 class="member-name">Emily Rodriguez</h4>
                        <p class="member-role">UX Designer</p>
                        <p class="member-description">
                            Creating intuitive and engaging user experiences for all platform users.
                        </p>
                    </div>
                    
                    <div class="team-member ken42-card animate-fade-in-up animate-delay-400">
                        <div class="member-avatar">
                            <i data-lucide="user"></i>
                        </div>
                        <h4 class="member-name">David Kim</h4>
                        <p class="member-role">Student Coordinator</p>
                        <p class="member-description">
                            Ensuring the platform meets the real needs of students and organizers.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Contact CTA -->
        <section class="contact-cta">
            <div class="cta-content">
                <div class="container">
                    <h2 class="cta-title animate-fade-in-up">Ready to Get Started?</h2>
                    <p class="cta-description animate-fade-in-up animate-delay-200">
                        Join thousands of students and organizers who are already using our platform to create amazing events.
                    </p>
                    <div class="cta-actions animate-fade-in-up animate-delay-400">
                        <a href="events-working.php" class="btn btn-primary btn-lg">Explore Events</a>
                        <a href="contact-working.php" class="btn btn-secondary btn-lg">Contact Us</a>
                    </div>
                </div>
            </div>
        </section>
    </main>

<?php include '../includes/footer.php'; ?>
