import './bootstrap';
import '../css/app.css';
import './theme-manager.js';

// Alpine.js for simple interactions (optional)
import Alpine from 'alpinejs';

window.Alpine = Alpine;
Alpine.start();

// Custom SSL Dashboard functionality
document.addEventListener('DOMContentLoaded', function() {
    // Auto-hide notifications
    const notifications = document.querySelectorAll('.notification');
    notifications.forEach(notification => {
        setTimeout(() => {
            notification.style.display = 'none';
        }, 5000);
    });

    // Copy to clipboard functionality
    window.copyToClipboard = function(text, buttonId) {
        navigator.clipboard.writeText(text).then(function() {
            const button = document.getElementById(buttonId);
            const originalText = button.textContent;
            button.textContent = 'Copied!';
            button.classList.add('bg-green-500');
            
            setTimeout(() => {
                button.textContent = originalText;
                button.classList.remove('bg-green-500');
            }, 2000);
        });
    };

    // Real-time status updates
    window.updateCertificateStatus = function(certificateId, status) {
        const statusElement = document.querySelector(`[data-certificate-id="${certificateId}"] .certificate-status`);
        if (statusElement) {
            statusElement.className = `status-badge status-badge-${getStatusClass(status)}`;
            statusElement.textContent = status.replace('_', ' ');
        }
    };

    function getStatusClass(status) {
        const statusMap = {
            'issued': 'success',
            'pending_validation': 'warning',
            'processing': 'info',
            'expired': 'danger',
            'revoked': 'danger'
        };
        return statusMap[status] || 'info';
    }

    // Enhanced form validation
    const forms = document.querySelectorAll('form[data-validate]');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const inputs = form.querySelectorAll('input[required]');
            let isValid = true;

            inputs.forEach(input => {
                if (!input.value.trim()) {
                    input.classList.add('form-input-error');
                    isValid = false;
                } else {
                    input.classList.remove('form-input-error');
                }
            });

            if (!isValid) {
                e.preventDefault();
            }
        });
    });
});

