<?php
$page_title = "Terms of Service";
$page_description = "Terms and conditions for using our event management platform";

include '../includes/header.php';
?>

<div class="hero-section">
    <div class="container">
        <div class="hero-content text-center">
            <h1 class="hero-title">Terms of Service</h1>
            <p class="hero-subtitle">Last updated: <?php echo date('F j, Y'); ?></p>
        </div>
    </div>
</div>

<section class="terms-section">
    <div class="container">
        <div class="row">
            <div class="col-md-8 mx-auto">
                <div class="terms-content">
                    <h2>Acceptance of Terms</h2>
                    <p>By accessing and using this event management platform, you accept and agree to be bound by the terms and provision of this agreement.</p>
                    
                    <h2>Use License</h2>
                    <p>Permission is granted to temporarily use this platform for personal, non-commercial transitory viewing only. This is the grant of a license, not a transfer of title, and under this license you may not:</p>
                    <ul>
                        <li>Modify or copy the materials</li>
                        <li>Use the materials for any commercial purpose or for any public display</li>
                        <li>Attempt to reverse engineer any software contained on the platform</li>
                        <li>Remove any copyright or other proprietary notations from the materials</li>
                    </ul>
                    
                    <h2>User Accounts</h2>
                    <p>When you create an account with us, you must provide information that is accurate, complete, and current at all times. You are responsible for safeguarding the password and for all activities that occur under your account.</p>
                    
                    <h2>Event Registration</h2>
                    <p>By registering for events through our platform:</p>
                    <ul>
                        <li>You commit to attending the events you register for</li>
                        <li>You agree to follow event rules and guidelines</li>
                        <li>You understand that some events may have limited capacity</li>
                        <li>You may cancel registrations according to the cancellation policy</li>
                    </ul>
                    
                    <h2>Prohibited Uses</h2>
                    <p>You may not use our platform:</p>
                    <ul>
                        <li>For any unlawful purpose or to solicit others to perform unlawful acts</li>
                        <li>To violate any international, federal, provincial, or state regulations, rules, laws, or local ordinances</li>
                        <li>To infringe upon or violate our intellectual property rights or the intellectual property rights of others</li>
                        <li>To harass, abuse, insult, harm, defame, slander, disparage, intimidate, or discriminate</li>
                    </ul>
                    
                    <h2>Disclaimer</h2>
                    <p>The information on this platform is provided on an 'as is' basis. To the fullest extent permitted by law, this Company excludes all representations, warranties, conditions and terms.</p>
                    
                    <h2>Contact Information</h2>
                    <p>If you have any questions about these Terms of Service, please contact us at <a href="mailto:<?php echo SITE_EMAIL; ?>"><?php echo SITE_EMAIL; ?></a>.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include '../includes/footer.php'; ?>
