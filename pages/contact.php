<?php
$page_title = "Contact Us";
$page_description = "Get in touch with us for any queries or support";
$additional_css = ['contact.css'];
require_once '../config/config.php';

// Handle form submission
$message_sent = false;
if ($_POST) {
    // In a real application, you would process the form data here
    $message_sent = true;
}
include '../includes/pages-header.php';
?>
    
    <main class="main-content">
        <!-- Page Header -->
        <section class="page-header">
            <div class="container">
                <div class="header-content">
                    <h1 class="page-title animate-fade-in-up">Contact Us</h1>
                    <p class="page-subtitle animate-fade-in-up animate-delay-200">
                        We're here to help! Reach out to us for any questions, support, or feedback.
                    </p>
                </div>
            </div>
        </section>

        <?php if ($message_sent): ?>
        <!-- Success Message -->
        <section class="success-message">
            <div class="container">
                <div class="alert alert-success animate-fade-in-up">
                    <i data-lucide="check-circle"></i>
                    <div>
                        <h4>Message Sent Successfully!</h4>
                        <p>Thank you for contacting us. We'll get back to you within 24 hours.</p>
                    </div>
                </div>
            </div>
        </section>
        <?php endif; ?>

        <!-- Contact Section -->
        <section class="contact-section">
            <div class="container">
                <div class="row">
                    <div class="col-6">
                        <div class="contact-info animate-fade-in-left">
                            <h2 class="section-title">Get in Touch</h2>
                            <p class="section-description">
                                Have questions about events, need technical support, or want to provide feedback? 
                                We'd love to hear from you!
                            </p>
                            
                            <div class="contact-methods">
                                <div class="contact-method">
                                    <div class="method-icon">
                                        <i data-lucide="map-pin"></i>
                                    </div>
                                    <div class="method-content">
                                        <h4 class="method-title">Visit Us</h4>
                                        <p class="method-text">
                                            Student Affairs Office<br>
                                            Main Campus Building, Room 201<br>
                                            University Avenue, City 12345
                                        </p>
                                    </div>
                                </div>
                                
                                <div class="contact-method">
                                    <div class="method-icon">
                                        <i data-lucide="phone"></i>
                                    </div>
                                    <div class="method-content">
                                        <h4 class="method-title">Call Us</h4>
                                        <p class="method-text">
                                            Main Office: +1 (555) 123-4567<br>
                                            Event Support: +1 (555) 123-4568<br>
                                            Emergency: +1 (555) 123-4569
                                        </p>
                                    </div>
                                </div>
                                
                                <div class="contact-method">
                                    <div class="method-icon">
                                        <i data-lucide="mail"></i>
                                    </div>
                                    <div class="method-content">
                                        <h4 class="method-title">Email Us</h4>
                                        <p class="method-text">
                                            General: info@college-events.edu<br>
                                            Support: support@college-events.edu<br>
                                            Events: events@college-events.edu
                                        </p>
                                    </div>
                                </div>
                                
                                <div class="contact-method">
                                    <div class="method-icon">
                                        <i data-lucide="clock"></i>
                                    </div>
                                    <div class="method-content">
                                        <h4 class="method-title">Office Hours</h4>
                                        <p class="method-text">
                                            Monday - Friday: 9:00 AM - 6:00 PM<br>
                                            Saturday: 10:00 AM - 4:00 PM<br>
                                            Sunday: Closed
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-6">
                        <div class="contact-form-container animate-fade-in-right">
                            <h2 class="form-title">Send us a Message</h2>
                            <form class="contact-form" method="POST" action="">
                                <div class="form-group">
                                    <label for="name" class="form-label">Full Name *</label>
                                    <input type="text" id="name" name="name" class="form-input" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="email" class="form-label">Email Address *</label>
                                    <input type="email" id="email" name="email" class="form-input" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="phone" class="form-label">Phone Number</label>
                                    <input type="tel" id="phone" name="phone" class="form-input">
                                </div>
                                
                                <div class="form-group">
                                    <label for="subject" class="form-label">Subject *</label>
                                    <select id="subject" name="subject" class="form-select" required>
                                        <option value="">Select a subject</option>
                                        <option value="general">General Inquiry</option>
                                        <option value="event">Event Related</option>
                                        <option value="technical">Technical Support</option>
                                        <option value="feedback">Feedback</option>
                                        <option value="other">Other</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="message" class="form-label">Message *</label>
                                    <textarea id="message" name="message" class="form-textarea" rows="5" required placeholder="Please describe your inquiry in detail..."></textarea>
                                </div>
                                
                                <button type="submit" class="btn btn-primary btn-full btn-animated">
                                    <i data-lucide="send"></i>
                                    Send Message
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- FAQ Section -->
        <section class="faq-section">
            <div class="container">
                <div class="section-header animate-fade-in-up">
                    <h2 class="section-title">Frequently Asked Questions</h2>
                    <p class="section-subtitle">Quick answers to common questions</p>
                </div>
                
                <div class="faq-grid">
                    <div class="faq-item ken42-card animate-fade-in-up animate-delay-100">
                        <h4 class="faq-question">How do I register for events?</h4>
                        <p class="faq-answer">
                            Simply browse our events page, click on any event you're interested in, and click the "Register" button. 
                            You'll need to be logged in to register.
                        </p>
                    </div>
                    
                    <div class="faq-item ken42-card animate-fade-in-up animate-delay-200">
                        <h4 class="faq-question">Can I cancel my event registration?</h4>
                        <p class="faq-answer">
                            Yes, you can cancel your registration up to 24 hours before the event starts. 
                            Go to your dashboard and click "Cancel Registration" next to the event.
                        </p>
                    </div>
                    
                    <div class="faq-item ken42-card animate-fade-in-up animate-delay-300">
                        <h4 class="faq-question">How do I get my event certificate?</h4>
                        <p class="faq-answer">
                            Certificates are automatically generated after event completion. 
                            You can download them from your dashboard under "My Certificates".
                        </p>
                    </div>
                    
                    <div class="faq-item ken42-card animate-fade-in-up animate-delay-400">
                        <h4 class="faq-question">What if I forget my password?</h4>
                        <p class="faq-answer">
                            Click on "Forgot Password" on the login page and enter your email. 
                            You'll receive a password reset link within a few minutes.
                        </p>
                    </div>
                    
                    <div class="faq-item ken42-card animate-fade-in-up animate-delay-500">
                        <h4 class="faq-question">How do I become an event organizer?</h4>
                        <p class="faq-answer">
                            Contact the admin team through this contact form or visit the Student Affairs Office. 
                            You'll need to provide details about your department and event planning experience.
                        </p>
                    </div>
                    
                    <div class="faq-item ken42-card animate-fade-in-up animate-delay-600">
                        <h4 class="faq-question">Is there a mobile app available?</h4>
                        <p class="faq-answer">
                            Currently, we offer a mobile-responsive website. A dedicated mobile app is in development 
                            and will be available soon.
                        </p>
                    </div>
                </div>
            </div>
        </section>
    </main>

<?php include '../includes/footer.php'; ?>
