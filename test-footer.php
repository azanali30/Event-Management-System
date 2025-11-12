<?php
$page_title = "Footer Test";
$page_description = "Testing the footer implementation";

include 'includes/header.php';
?>

<main class="main-content">
    <section style="padding: 4rem 0; min-height: 60vh;">
        <div class="container">
            <div class="text-center">
                <h1>Footer Test Page</h1>
                <p>This page is used to test the footer implementation.</p>
                
                <div style="margin: 2rem 0; padding: 2rem; background: #f8fafc; border-radius: 12px; border: 1px solid #e2e8f0;">
                    <h2 style="color: #1e293b; margin-bottom: 1.5rem; font-weight: 700;">🏢 Professional Modern Footer Design</h2>
                    <ul style="text-align: left; max-width: 800px; margin: 0 auto; line-height: 1.8; color: #475569;">
                        <li>✨ <strong>Clean Light Gray Background:</strong> Professional #f8fafc background with subtle contrast</li>
                        <li>📐 <strong>Grid Layout:</strong> 4-column responsive grid that stacks on mobile</li>
                        <li>🎯 <strong>Consistent Typography:</strong> Clean sans-serif fonts with proper hierarchy</li>
                        <li>🔵 <strong>Subtle Blue Accents:</strong> Blue used sparingly for links and icons only</li>
                        <li>📱 <strong>Minimal Social Icons:</strong> Clean gray icons that turn blue on hover</li>
                        <li>📧 <strong>Structured Contact Info:</strong> Clear labels with organized contact details</li>
                        <li>⚡ <strong>Smooth Micro-interactions:</strong> Subtle hover effects that feel professional</li>
                        <li>📊 <strong>Perfect Spacing:</strong> Generous whitespace and consistent margins</li>
                        <li>🎪 <strong>Clear Visual Hierarchy:</strong> Proper heading sizes and text contrast</li>
                        <li>📱 <strong>Mobile-First Responsive:</strong> Stacks beautifully on all screen sizes</li>
                        <li>🏢 <strong>Corporate-Ready:</strong> Looks professional enough for any business</li>
                    </ul>
                </div>
                
                <div style="margin: 2rem 0;">
                    <h3>Pages with Footer:</h3>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-top: 1rem;">
                        <a href="index.php" class="btn btn-secondary">Home</a>
                        <a href="simple-index.php" class="btn btn-secondary">Simple Home</a>
                        <a href="pages/about.php" class="btn btn-secondary">About</a>
                        <a href="pages/about-working.php" class="btn btn-secondary">About (Working)</a>
                        <a href="pages/events.php" class="btn btn-secondary">Events</a>
                        <a href="pages/events-working.php" class="btn btn-secondary">Events (Working)</a>
                        <a href="pages/gallery.php" class="btn btn-secondary">Gallery</a>
                        <a href="pages/gallery-working.php" class="btn btn-secondary">Gallery (Working)</a>
                        <a href="pages/contact.php" class="btn btn-secondary">Contact</a>
                        <a href="pages/contact-working.php" class="btn btn-secondary">Contact (Working)</a>
                        <a href="pages/faq.php" class="btn btn-secondary">FAQ</a>
                        <a href="pages/help.php" class="btn btn-secondary">Help</a>
                        <a href="pages/privacy.php" class="btn btn-secondary">Privacy</a>
                        <a href="pages/terms.php" class="btn btn-secondary">Terms</a>
                        <a href="pages/dashboard.php" class="btn btn-secondary">Dashboard</a>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<?php include 'includes/footer.php'; ?>
