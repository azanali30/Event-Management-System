// Authentication JavaScript Functions

// Password Toggle Functionality
function togglePassword(inputId, iconId) {
    const passwordInput = document.getElementById(inputId);
    const eyeIcon = document.getElementById(iconId);
    
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        eyeIcon.setAttribute('data-lucide', 'eye-off');
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    } else {
        passwordInput.type = 'password';
        eyeIcon.setAttribute('data-lucide', 'eye');
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    }
}

// Password Strength Checker
function checkPasswordStrength(password) {
    let strength = 0;
    const checks = {
        length: password.length >= 8,
        lowercase: /[a-z]/.test(password),
        uppercase: /[A-Z]/.test(password),
        numbers: /\d/.test(password),
        special: /[!@#$%^&*(),.?":{}|<>]/.test(password)
    };
    
    strength = Object.values(checks).filter(Boolean).length;
    
    if (strength < 3) return 'weak';
    if (strength < 5) return 'medium';
    return 'strong';
}

// Update Password Strength Indicator
function updatePasswordStrength(inputId, strengthContainerId) {
    const passwordInput = document.getElementById(inputId);
    const strengthContainer = document.getElementById(strengthContainerId);
    
    if (!passwordInput || !strengthContainer) return;
    
    const password = passwordInput.value;
    const strength = checkPasswordStrength(password);
    
    const bars = strengthContainer.querySelectorAll('.strength-bar');
    const text = strengthContainer.querySelector('.strength-text');
    
    // Reset all bars
    bars.forEach(bar => {
        bar.className = 'strength-bar';
    });
    
    // Update bars based on strength
    if (password.length > 0) {
        if (strength === 'weak') {
            bars[0].classList.add('weak');
            text.textContent = 'Weak password';
            text.className = 'strength-text weak';
        } else if (strength === 'medium') {
            bars[0].classList.add('medium');
            bars[1].classList.add('medium');
            text.textContent = 'Medium strength';
            text.className = 'strength-text medium';
        } else if (strength === 'strong') {
            bars.forEach(bar => bar.classList.add('strong'));
            text.textContent = 'Strong password';
            text.className = 'strength-text strong';
        }
    } else {
        text.textContent = '';
        text.className = 'strength-text';
    }
}

// Form Validation
function validateForm(formId) {
    const form = document.getElementById(formId);
    if (!form) return false;
    
    const inputs = form.querySelectorAll('input[required], select[required]');
    let isValid = true;
    
    inputs.forEach(input => {
        if (!input.value.trim()) {
            input.classList.add('error');
            isValid = false;
        } else {
            input.classList.remove('error');
        }
    });
    
    // Check password match for registration
    if (formId === 'register-form') {
        const password = document.getElementById('password');
        const confirmPassword = document.getElementById('confirm_password');
        
        if (password && confirmPassword && password.value !== confirmPassword.value) {
            confirmPassword.classList.add('error');
            showAlert('Passwords do not match', 'error');
            isValid = false;
        }
    }
    
    return isValid;
}

// Show Alert Message
function showAlert(message, type = 'info') {
    // Remove existing alerts
    const existingAlerts = document.querySelectorAll('.alert');
    existingAlerts.forEach(alert => alert.remove());
    
    // Create new alert
    const alert = document.createElement('div');
    alert.className = `alert alert-${type}`;
    alert.innerHTML = `
        <i data-lucide="${type === 'error' ? 'alert-circle' : type === 'success' ? 'check-circle' : 'info'}"></i>
        <span>${message}</span>
    `;
    
    // Insert alert at the top of the form
    const form = document.querySelector('.auth-form');
    if (form) {
        form.parentNode.insertBefore(alert, form);
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }

        // Auto-remove after 5 seconds
        setTimeout(() => {
            alert.remove();
        }, 5000);
    }
}

// Loading State for Buttons
function setButtonLoading(buttonId, loading = true) {
    const button = document.getElementById(buttonId);
    if (!button) return;
    
    if (loading) {
        button.classList.add('btn-loading');
        button.disabled = true;
    } else {
        button.classList.remove('btn-loading');
        button.disabled = false;
    }
}

// Initialize Authentication Features
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Lucide icons (if available)
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
    
    // Add password strength checker to register form
    const passwordInput = document.getElementById('password');
    if (passwordInput) {
        // Create strength indicator if it doesn't exist
        let strengthContainer = document.getElementById('password-strength');
        if (!strengthContainer) {
            strengthContainer = document.createElement('div');
            strengthContainer.id = 'password-strength';
            strengthContainer.className = 'password-strength';
            strengthContainer.innerHTML = `
                <div class="strength-bar"></div>
                <div class="strength-bar"></div>
                <div class="strength-bar"></div>
                <div class="strength-text"></div>
            `;
            passwordInput.parentNode.parentNode.appendChild(strengthContainer);
        }
        
        passwordInput.addEventListener('input', function() {
            updatePasswordStrength('password', 'password-strength');
        });
    }
    
    // Add form validation on submit
    const forms = document.querySelectorAll('.auth-form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const formId = form.id || 'auth-form';
            if (!validateForm(formId)) {
                e.preventDefault();
            } else {
                // Add loading state to submit button
                const submitButton = form.querySelector('button[type="submit"]');
                if (submitButton) {
                    setButtonLoading(submitButton.id || 'submit-btn', true);
                }
            }
        });
    });
    
    // Add input focus animations
    const inputs = document.querySelectorAll('.form-input, .form-select');
    inputs.forEach(input => {
        input.addEventListener('focus', function() {
            this.parentNode.classList.add('focused');
        });
        
        input.addEventListener('blur', function() {
            this.parentNode.classList.remove('focused');
        });
    });
    
    // Add smooth animations to form elements
    const formElements = document.querySelectorAll('.form-group');
    formElements.forEach((element, index) => {
        element.style.animationDelay = `${index * 0.1}s`;
        element.classList.add('animate-fade-in-up');
    });
});

// Social Login Handlers
function handleGoogleLogin() {
    showAlert('Google login integration would be implemented here', 'info');
}

function handleMicrosoftLogin() {
    showAlert('Microsoft login integration would be implemented here', 'info');
}

// Export functions for global use
window.togglePassword = togglePassword;
window.handleGoogleLogin = handleGoogleLogin;
window.handleMicrosoftLogin = handleMicrosoftLogin;
