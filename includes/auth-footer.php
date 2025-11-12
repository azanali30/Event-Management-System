    <!-- JavaScript -->
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <script src="../assets/js/main.js"></script>
    
    <script>
        // Initialize Lucide icons
        lucide.createIcons();
        
        // Initialize auth page functionality
        document.addEventListener('DOMContentLoaded', function() {
            // Form validation
            const forms = document.querySelectorAll('[data-validate]');
            forms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    const inputs = form.querySelectorAll('input[required]');
                    let isValid = true;
                    
                    inputs.forEach(input => {
                        if (!input.value.trim()) {
                            isValid = false;
                            input.classList.add('error');
                        } else {
                            input.classList.remove('error');
                        }
                    });
                    
                    if (!isValid) {
                        e.preventDefault();
                    }
                });
            });
        });
    </script>
</body>
</html>
