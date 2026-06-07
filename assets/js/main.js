/**
 * Main JavaScript File
 * Online Voting System - Wollo University
 */

// DOM Content Loaded
document.addEventListener('DOMContentLoaded', function() {
    // Initialize all components
    initializeNavigation();
    initializeForms();
    initializeAlerts();
    initializeCountdowns();
});

// Navigation functionality
function initializeNavigation() {
    const navToggle = document.querySelector('.nav-toggle');
    const navMenu = document.querySelector('.nav-menu');
    
    if (navToggle && navMenu) {
        navToggle.addEventListener('click', function() {
            navMenu.classList.toggle('active');
        });
    }
}

// Form validation and enhancement
function initializeForms() {
    // Password strength indicator
    const passwordInput = document.getElementById('password');
    if (passwordInput) {
        passwordInput.addEventListener('input', function() {
            checkPasswordStrength(this.value);
        });
    }
    
    // Confirm password validation
    const confirmPasswordInput = document.getElementById('confirm_password');
    if (confirmPasswordInput) {
        confirmPasswordInput.addEventListener('input', function() {
            validatePasswordMatch();
        });
    }
    
    // Form submission loading state
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function() {
            const submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.innerHTML = '<span class="loading"></span> Processing...';
                submitBtn.disabled = true;
            }
        });
    });
}

// Password strength checker
function checkPasswordStrength(password) {
    const strengthIndicator = document.getElementById('password-strength');
    if (!strengthIndicator) return;
    
    let strength = 0;
    let feedback = [];
    
    // Length check
    if (password.length >= 8) strength++;
    else feedback.push('At least 8 characters');
    
    // Uppercase check
    if (/[A-Z]/.test(password)) strength++;
    else feedback.push('One uppercase letter');
    
    // Lowercase check
    if (/[a-z]/.test(password)) strength++;
    else feedback.push('One lowercase letter');
    
    // Number check
    if (/\d/.test(password)) strength++;
    else feedback.push('One number');
    
    // Special character check
    if (/[!@#$%^&*(),.?":{}|<>]/.test(password)) strength++;
    else feedback.push('One special character');
    
    // Update indicator
    const strengthLevels = ['Very Weak', 'Weak', 'Fair', 'Good', 'Strong'];
    const strengthColors = ['#ff4757', '#ff6b7a', '#ffa502', '#2ed573', '#1e90ff'];
    
    strengthIndicator.textContent = strengthLevels[strength - 1] || 'Very Weak';
    strengthIndicator.style.color = strengthColors[strength - 1] || '#ff4757';
    
    if (feedback.length > 0) {
        strengthIndicator.title = 'Missing: ' + feedback.join(', ');
    }
}

// Password match validation
function validatePasswordMatch() {
    const password = document.getElementById('password');
    const confirmPassword = document.getElementById('confirm_password');
    const matchIndicator = document.getElementById('password-match');
    
    if (!password || !confirmPassword) return;
    
    if (confirmPassword.value === '') {
        if (matchIndicator) matchIndicator.textContent = '';
        return;
    }
    
    if (password.value === confirmPassword.value) {
        confirmPassword.setCustomValidity('');
        if (matchIndicator) {
            matchIndicator.textContent = '✓ Passwords match';
            matchIndicator.style.color = '#2ed573';
        }
    } else {
        confirmPassword.setCustomValidity('Passwords do not match');
        if (matchIndicator) {
            matchIndicator.textContent = '✗ Passwords do not match';
            matchIndicator.style.color = '#ff4757';
        }
    }
}

// Alert auto-dismiss
function initializeAlerts() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        // Add close button
        const closeBtn = document.createElement('button');
        closeBtn.innerHTML = '×';
        closeBtn.className = 'alert-close';
        closeBtn.style.cssText = 'float: right; background: none; border: none; font-size: 1.5rem; cursor: pointer; padding: 0; margin-left: 1rem;';
        
        closeBtn.addEventListener('click', function() {
            alert.style.display = 'none';
        });
        
        alert.insertBefore(closeBtn, alert.firstChild);
        
        // Auto-dismiss after 5 seconds for success messages
        if (alert.classList.contains('alert-success')) {
            setTimeout(() => {
                alert.style.opacity = '0';
                setTimeout(() => alert.style.display = 'none', 300);
            }, 5000);
        }
    });
}

// Countdown timers for elections
function initializeCountdowns() {
    const countdownElements = document.querySelectorAll('[data-countdown]');
    
    countdownElements.forEach(element => {
        const endTime = new Date(element.dataset.countdown).getTime();
        
        const timer = setInterval(() => {
            const now = new Date().getTime();
            const distance = endTime - now;
            
            if (distance < 0) {
                clearInterval(timer);
                element.innerHTML = 'EXPIRED';
                return;
            }
            
            const days = Math.floor(distance / (1000 * 60 * 60 * 24));
            const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((distance % (1000 * 60)) / 1000);
            
            element.innerHTML = `${days}d ${hours}h ${minutes}m ${seconds}s`;
        }, 1000);
    });
}

// Utility functions
function showLoading(element) {
    if (element) {
        element.innerHTML = '<span class="loading"></span> Loading...';
        element.disabled = true;
    }
}

function hideLoading(element, originalText) {
    if (element) {
        element.innerHTML = originalText;
        element.disabled = false;
    }
}

function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.innerHTML = `
        <span>${message}</span>
        <button onclick="this.parentElement.remove()">×</button>
    `;
    
    // Add styles
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 1rem;
        border-radius: 5px;
        color: white;
        z-index: 1000;
        max-width: 300px;
        box-shadow: 0 5px 15px rgba(0,0,0,0.2);
    `;
    
    // Set background color based on type
    const colors = {
        success: '#28a745',
        error: '#dc3545',
        warning: '#ffc107',
        info: '#17a2b8'
    };
    notification.style.backgroundColor = colors[type] || colors.info;
    
    document.body.appendChild(notification);
    
    // Auto-remove after 5 seconds
    setTimeout(() => {
        if (notification.parentElement) {
            notification.remove();
        }
    }, 5000);
}

// AJAX helper function
function makeRequest(url, method = 'GET', data = null) {
    return new Promise((resolve, reject) => {
        const xhr = new XMLHttpRequest();
        xhr.open(method, url);
        xhr.setRequestHeader('Content-Type', 'application/json');
        
        xhr.onload = function() {
            if (xhr.status >= 200 && xhr.status < 300) {
                try {
                    const response = JSON.parse(xhr.responseText);
                    resolve(response);
                } catch (e) {
                    resolve(xhr.responseText);
                }
            } else {
                reject(new Error(`HTTP ${xhr.status}: ${xhr.statusText}`));
            }
        };
        
        xhr.onerror = function() {
            reject(new Error('Network error'));
        };
        
        xhr.send(data ? JSON.stringify(data) : null);
    });
}

// Form data helper
function getFormData(form) {
    const formData = new FormData(form);
    const data = {};
    
    for (let [key, value] of formData.entries()) {
        data[key] = value;
    }
    
    return data;
}

// Local storage helpers
function saveToStorage(key, data) {
    try {
        localStorage.setItem(key, JSON.stringify(data));
        return true;
    } catch (e) {
        console.error('Failed to save to localStorage:', e);
        return false;
    }
}

function getFromStorage(key) {
    try {
        const data = localStorage.getItem(key);
        return data ? JSON.parse(data) : null;
    } catch (e) {
        console.error('Failed to read from localStorage:', e);
        return null;
    }
}

// Session timeout warning
function initializeSessionTimeout() {
    const SESSION_TIMEOUT = 30 * 60 * 1000; // 30 minutes
    const WARNING_TIME = 5 * 60 * 1000; // 5 minutes before timeout
    
    let lastActivity = Date.now();
    let warningShown = false;
    
    // Track user activity
    document.addEventListener('click', updateActivity);
    document.addEventListener('keypress', updateActivity);
    document.addEventListener('scroll', updateActivity);
    
    function updateActivity() {
        lastActivity = Date.now();
        warningShown = false;
    }
    
    // Check session timeout
    setInterval(() => {
        const timeSinceActivity = Date.now() - lastActivity;
        
        if (timeSinceActivity > SESSION_TIMEOUT) {
            showNotification('Session expired. Please log in again.', 'warning');
            setTimeout(() => {
                window.location.href = 'login.php';
            }, 3000);
        } else if (timeSinceActivity > WARNING_TIME && !warningShown) {
            showNotification('Your session will expire in 5 minutes. Click anywhere to extend.', 'warning');
            warningShown = true;
        }
    }, 60000); // Check every minute
}

// Initialize session timeout if user is logged in
if (document.body.dataset.loggedIn === 'true') {
    initializeSessionTimeout();
}

// Theme switching functionality
function initializeThemeToggle() {
    // Create theme toggle button
    const themeToggle = document.createElement('div');
    themeToggle.className = 'theme-toggle';
    themeToggle.innerHTML = `
        <span class="icon">🌙</span>
        <span class="text">Dark</span>
    `;
    
    // Add to body
    document.body.appendChild(themeToggle);
    
    // Get saved theme or default to light
    const savedTheme = localStorage.getItem('theme') || 'light';
    setTheme(savedTheme);
    
    // Theme toggle click handler
    themeToggle.addEventListener('click', function() {
        const currentTheme = document.documentElement.getAttribute('data-theme') || 'light';
        const newTheme = currentTheme === 'light' ? 'dark' : 'light';
        setTheme(newTheme);
    });
}

function setTheme(theme) {
    const themeToggle = document.querySelector('.theme-toggle');
    const icon = themeToggle?.querySelector('.icon');
    const text = themeToggle?.querySelector('.text');
    
    if (theme === 'dark') {
        document.documentElement.setAttribute('data-theme', 'dark');
        if (icon) icon.textContent = '☀️';
        if (text) text.textContent = 'Light';
        localStorage.setItem('theme', 'dark');
    } else {
        document.documentElement.setAttribute('data-theme', 'light');
        if (icon) icon.textContent = '🌙';
        if (text) text.textContent = 'Dark';
        localStorage.setItem('theme', 'light');
    }
}

// Initialize theme toggle when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    initializeThemeToggle();
    initializePhoneValidation();
});

// Phone number validation utility
function initializePhoneValidation() {
    const phoneInputs = document.querySelectorAll('input[type="tel"], input[name="phone_number"]');
    
    phoneInputs.forEach(input => {
        setupPhoneValidation(input);
    });
}

function setupPhoneValidation(phoneInput) {
    if (!phoneInput) return;
    
    // Add validation attributes if not already present
    if (!phoneInput.hasAttribute('pattern')) {
        phoneInput.setAttribute('pattern', '[0-9]{1,10}');
    }
    if (!phoneInput.hasAttribute('maxlength')) {
        phoneInput.setAttribute('maxlength', '10');
    }
    if (!phoneInput.hasAttribute('title')) {
        phoneInput.setAttribute('title', 'Phone number must contain only numbers and be maximum 10 digits');
    }
    
    // Input event listener for real-time validation
    phoneInput.addEventListener('input', function() {
        validatePhoneInput(this);
    });
    
    // Keypress event listener to prevent non-numeric input
    phoneInput.addEventListener('keypress', function(e) {
        preventNonNumericInput(e, this);
    });
}

function validatePhoneInput(input) {
    const phoneNumber = input.value;
    
    // Remove any non-digit characters
    const cleanedNumber = phoneNumber.replace(/\D/g, '');
    
    // Update the input value with cleaned number
    if (cleanedNumber !== phoneNumber) {
        input.value = cleanedNumber;
    }
    
    // Validate length and format
    if (cleanedNumber.length > 10) {
        input.value = cleanedNumber.substring(0, 10);
        showPhoneError(input, 'Phone number cannot exceed 10 digits');
    } else if (cleanedNumber.length > 0 && !/^[0-9]+$/.test(cleanedNumber)) {
        showPhoneError(input, 'Phone number must contain only numbers');
    } else {
        clearPhoneError(input);
        input.setCustomValidity('');
        input.style.borderColor = cleanedNumber.length > 0 ? '#27ae60' : '';
    }
}

function preventNonNumericInput(e, input) {
    // Allow backspace, delete, tab, escape, enter
    if ([8, 9, 27, 13, 46].indexOf(e.keyCode) !== -1 ||
        // Allow Ctrl+A, Ctrl+C, Ctrl+V, Ctrl+X
        (e.keyCode === 65 && e.ctrlKey === true) ||
        (e.keyCode === 67 && e.ctrlKey === true) ||
        (e.keyCode === 86 && e.ctrlKey === true) ||
        (e.keyCode === 88 && e.ctrlKey === true)) {
        return;
    }
    
    // Ensure that it is a number and stop the keypress
    if ((e.shiftKey || (e.keyCode < 48 || e.keyCode > 57)) && (e.keyCode < 96 || e.keyCode > 105)) {
        e.preventDefault();
    }
    
    // Check if adding this digit would exceed 10 characters
    if (input.value.length >= 10) {
        e.preventDefault();
    }
}

function showPhoneError(input, message) {
    input.setCustomValidity(message);
    input.style.borderColor = '#e74c3c';
    
    // Show error message
    let phoneMessage = input.parentNode.querySelector('.phone-error-message');
    if (!phoneMessage) {
        phoneMessage = document.createElement('div');
        phoneMessage.className = 'phone-error-message';
        phoneMessage.style.color = '#e74c3c';
        phoneMessage.style.fontSize = '0.8rem';
        phoneMessage.style.marginTop = '0.25rem';
        input.parentNode.appendChild(phoneMessage);
    }
    phoneMessage.textContent = message;
}

function clearPhoneError(input) {
    const phoneMessage = input.parentNode.querySelector('.phone-error-message');
    if (phoneMessage) {
        phoneMessage.remove();
    }
}