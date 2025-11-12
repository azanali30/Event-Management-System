<?php
$page_title = "Frequently Asked Questions";
$page_description = "Find answers to common questions about our event management platform";
$additional_css = ['faq.css'];

include '../includes/pages-header.php';
?>

<div class="hero-section">
    <div class="container">
        <div class="hero-content text-center">
            <h1 class="hero-title">Frequently Asked Questions</h1>
            <p class="hero-subtitle">Find answers to common questions about our platform</p>
        </div>
    </div>
</div>

<section class="faq-section">
    <div class="container">
        <div class="row">
            <div class="col-md-8 mx-auto">
                <div class="faq-list">
                    <div class="faq-item">
                        <div class="faq-question">
                            <h3>How do I register for an event?</h3>
                            <i data-lucide="chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                            <p>To register for an event, simply browse our events page, click on the event you're interested in, and click the "Register" button. You'll need to create an account if you don't have one already.</p>
                        </div>
                    </div>

                    <div class="faq-item">
                        <div class="faq-question">
                            <h3>Can I cancel my event registration?</h3>
                            <i data-lucide="chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                            <p>Yes, you can cancel your registration up to 24 hours before the event starts. Go to your dashboard and click "Cancel Registration" next to the event.</p>
                        </div>
                    </div>

                    <div class="faq-item">
                        <div class="faq-question">
                            <h3>How do I become an event organizer?</h3>
                            <i data-lucide="chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                            <p>To become an event organizer, contact our admin team through the contact page. You'll need to provide details about your organization and the types of events you plan to host.</p>
                        </div>
                    </div>

                    <div class="faq-item">
                        <div class="faq-question">
                            <h3>Is there a limit to how many events I can register for?</h3>
                            <i data-lucide="chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                            <p>No, there's no limit to the number of events you can register for. However, please be mindful of scheduling conflicts and only register for events you plan to attend.</p>
                        </div>
                    </div>

                    <div class="faq-item">
                        <div class="faq-question">
                            <h3>How do I get notifications about new events?</h3>
                            <i data-lucide="chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                            <p>You can enable email notifications in your account settings. We'll send you updates about new events that match your interests and reminders about upcoming events you've registered for.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const faqQuestions = document.querySelectorAll('.faq-question');

    faqQuestions.forEach(question => {
        question.addEventListener('click', function() {
            const faqItem = this.parentElement;
            const isActive = faqItem.classList.contains('active');

            // Close all FAQ items
            document.querySelectorAll('.faq-item').forEach(item => {
                item.classList.remove('active');
            });

            // Open clicked item if it wasn't active
            if (!isActive) {
                faqItem.classList.add('active');
            }
        });
    });
});
</script>

<?php include '../includes/footer.php'; ?>
