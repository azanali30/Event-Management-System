    </main>
    
    <footer class="footer">
        <div class="footer-content">
            <div class="footer-top">
                <!-- Company Info -->
                <div class="footer-section">
                    <div class="footer-logo">
                        <?php
                        $footer_logo_path = file_exists('images/logo.png') ? 'images/logo.png' : '../images/logo.png';
                        ?>
                        <img src="<?php echo $footer_logo_path; ?>" alt="<?php echo SITE_NAME; ?>" class="footer-logo-img">
                    </div>
                    <p class="footer-description">
                        Streamline your campus events with our comprehensive management platform.
                        Connect students, organize activities, and build lasting memories.
                    </p>
                    <div class="social-links">
                        <a href="#" class="social-link" aria-label="Facebook"><i data-lucide="facebook"></i></a>
                        <a href="#" class="social-link" aria-label="Twitter"><i data-lucide="twitter"></i></a>
                        <a href="#" class="social-link" aria-label="Instagram"><i data-lucide="instagram"></i></a>
                        <a href="#" class="social-link" aria-label="LinkedIn"><i data-lucide="linkedin"></i></a>
                    </div>
                </div>

                <!-- Quick Links -->
                <div class="footer-section">
                    <h4 class="footer-title">Quick Links</h4>
                    <div class="footer-links">
                        <a href="<?php echo SITE_URL; ?>/">Home</a>
                        <a href="<?php echo SITE_URL; ?>/pages/events.php">Browse Events</a>
                        <a href="<?php echo SITE_URL; ?>/pages/gallery.php">Event Gallery</a>
                        <a href="<?php echo SITE_URL; ?>/pages/about.php">About Us</a>
                    </div>
                </div>

                <!-- Support -->
                <div class="footer-section">
                    <h4 class="footer-title">Support</h4>
                    <div class="footer-links">
                        <a href="<?php echo SITE_URL; ?>/pages/contact.php">Contact Us</a>
                        <a href="<?php echo SITE_URL; ?>/pages/faq.php">FAQ</a>
                        <a href="<?php echo SITE_URL; ?>/pages/help.php">Help Center</a>
                        <a href="<?php echo SITE_URL; ?>/pages/privacy.php">Privacy Policy</a>
                    </div>
                </div>

                <!-- Contact Info -->
                <div class="footer-section">
                    <h4 class="footer-title">Contact Info</h4>
                    <div class="contact-info">
                        <div class="contact-item">
                            <i data-lucide="mail"></i>
                            <div>
                                <strong>Email</strong>
                                <span><?php echo SITE_EMAIL; ?></span>
                            </div>
                        </div>
                        <div class="contact-item">
                            <i data-lucide="phone"></i>
                            <div>
                                <strong>Phone</strong>
                                <span>+1 (555) 123-4567</span>
                            </div>
                        </div>
                        <div class="contact-item">
                            <i data-lucide="map-pin"></i>
                            <div>
                                <strong>Address</strong>
                                <span>123 College Street<br>Campus City, CC 12345</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer Bottom -->
            <div class="footer-bottom">
                <p class="copyright">
                    &copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. All rights reserved.
                </p>
                <div class="footer-links-inline">
                    <a href="<?php echo SITE_URL; ?>/pages/privacy.php">Privacy Policy</a>
                    <span>•</span>
                    <a href="<?php echo SITE_URL; ?>/pages/terms.php">Terms of Service</a>
                    <span>•</span>
                    <a href="<?php echo SITE_URL; ?>/pages/cookies.php">Cookie Policy</a>
                </div>
            </div>
        </div>
    </footer>
    
    <!-- JavaScript -->
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <?php if (!isset($disable_main_js) || !$disable_main_js): ?>
    <script src="<?php echo SITE_URL; ?>/assets/js/main.js"></script>
    <script src="<?php echo SITE_URL; ?>/assets/js/animations.js"></script>
    <?php endif; ?>
    
    <!-- Additional JavaScript -->
    <?php if (isset($additional_js)): ?>
        <?php foreach ($additional_js as $js): ?>
            <script src="<?php echo SITE_URL; ?>/assets/js/<?php echo $js; ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
    
    <script>
        // Initialize Lucide icons
        lucide.createIcons();
        
        // Initialize tooltips and other components
        document.addEventListener('DOMContentLoaded', function() {
            // Add any initialization code here
        });
    </script>
</body>
</html>
