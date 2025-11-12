<?php
$page_title = "Help Center";
$page_description = "Get help and support for using our event management platform";

include '../includes/header.php';
?>

<div class="hero-section">
    <div class="container">
        <div class="hero-content text-center">
            <h1 class="hero-title">Help Center</h1>
            <p class="hero-subtitle">Get the support you need</p>
        </div>
    </div>
</div>

<section class="help-section">
    <div class="container">
        <div class="row">
            <div class="col-md-4">
                <div class="help-card">
                    <div class="help-icon">
                        <i data-lucide="book-open"></i>
                    </div>
                    <h3>User Guide</h3>
                    <p>Learn how to use all features of our platform with our comprehensive user guide.</p>
                    <a href="#" class="btn btn-outline">Read Guide</a>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="help-card">
                    <div class="help-icon">
                        <i data-lucide="video"></i>
                    </div>
                    <h3>Video Tutorials</h3>
                    <p>Watch step-by-step video tutorials to get started quickly.</p>
                    <a href="#" class="btn btn-outline">Watch Videos</a>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="help-card">
                    <div class="help-icon">
                        <i data-lucide="message-circle"></i>
                    </div>
                    <h3>Contact Support</h3>
                    <p>Need personal assistance? Our support team is here to help.</p>
                    <a href="contact.php" class="btn btn-outline">Contact Us</a>
                </div>
            </div>
        </div>
        
        <div class="row mt-4">
            <div class="col-md-8 mx-auto">
                <div class="help-content">
                    <h2>Quick Start Guide</h2>
                    <div class="step-list">
                        <div class="step-item">
                            <div class="step-number">1</div>
                            <div class="step-content">
                                <h4>Create Your Account</h4>
                                <p>Sign up with your email address to get started.</p>
                            </div>
                        </div>
                        
                        <div class="step-item">
                            <div class="step-number">2</div>
                            <div class="step-content">
                                <h4>Browse Events</h4>
                                <p>Explore upcoming events and find ones that interest you.</p>
                            </div>
                        </div>
                        
                        <div class="step-item">
                            <div class="step-number">3</div>
                            <div class="step-content">
                                <h4>Register for Events</h4>
                                <p>Click the register button and secure your spot.</p>
                            </div>
                        </div>
                        
                        <div class="step-item">
                            <div class="step-number">4</div>
                            <div class="step-content">
                                <h4>Attend & Enjoy</h4>
                                <p>Show up to your registered events and have a great time!</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include '../includes/footer.php'; ?>
