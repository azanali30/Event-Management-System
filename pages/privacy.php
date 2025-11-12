<?php
$page_title = "Privacy Policy";
$page_description = "Learn how we protect and handle your personal information";

include '../includes/header.php';
?>

<div class="hero-section">
    <div class="container">
        <div class="hero-content text-center">
            <h1 class="hero-title">Privacy Policy</h1>
            <p class="hero-subtitle">Last updated: <?php echo date('F j, Y'); ?></p>
        </div>
    </div>
</div>

<section class="privacy-section">
    <div class="container">
        <div class="row">
            <div class="col-md-8 mx-auto">
                <div class="privacy-content">
                    <h2>Information We Collect</h2>
                    <p>We collect information you provide directly to us, such as when you create an account, register for events, or contact us for support.</p>
                    
                    <h3>Personal Information</h3>
                    <ul>
                        <li>Name and email address</li>
                        <li>Student ID (if applicable)</li>
                        <li>Phone number (optional)</li>
                        <li>Event preferences and registration history</li>
                    </ul>
                    
                    <h2>How We Use Your Information</h2>
                    <p>We use the information we collect to:</p>
                    <ul>
                        <li>Provide and maintain our services</li>
                        <li>Process event registrations</li>
                        <li>Send you event notifications and updates</li>
                        <li>Improve our platform and user experience</li>
                        <li>Respond to your comments and questions</li>
                    </ul>
                    
                    <h2>Information Sharing</h2>
                    <p>We do not sell, trade, or otherwise transfer your personal information to third parties without your consent, except as described in this policy.</p>
                    
                    <h2>Data Security</h2>
                    <p>We implement appropriate security measures to protect your personal information against unauthorized access, alteration, disclosure, or destruction.</p>
                    
                    <h2>Your Rights</h2>
                    <p>You have the right to:</p>
                    <ul>
                        <li>Access and update your personal information</li>
                        <li>Delete your account and associated data</li>
                        <li>Opt out of marketing communications</li>
                        <li>Request a copy of your data</li>
                    </ul>
                    
                    <h2>Contact Us</h2>
                    <p>If you have any questions about this Privacy Policy, please contact us at <a href="mailto:<?php echo SITE_EMAIL; ?>"><?php echo SITE_EMAIL; ?></a>.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include '../includes/footer.php'; ?>
