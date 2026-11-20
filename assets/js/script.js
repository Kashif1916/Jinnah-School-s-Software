/**
 * Main JavaScript File
 * School Finance Management System
 */

// Initialize on document ready
document.addEventListener('DOMContentLoaded', function() {
    initializeTooltips();
    initializeDatatable();
    setupFormValidation();
    setupDeleteConfirmation();
    setupFormLoaders();
    checkDeveloperNotifications();
});

/**
 * Initialize Bootstrap tooltips
 */
function initializeTooltips() {
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
}

/**
 * Initialize data table features
 */
function initializeDatatable() {
    const tables = document.querySelectorAll('.table');
    tables.forEach(table => {
        // Add search functionality
        const tableBody = table.querySelector('tbody');
        if (tableBody) {
            // Basic search already works with Bootstrap
            // Can be enhanced with jQuery DataTables if needed
        }
    });
}

/**
 * Setup form validation
 */
function setupFormValidation() {
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            // HTML5 validation will handle most cases
            // Add custom validation here if needed
        });
    });
}

/**
 * Setup delete confirmation
 */
function setupDeleteConfirmation() {
    const deleteButtons = document.querySelectorAll('[data-confirm]');
    deleteButtons.forEach(btn => {
        btn.addEventListener('click', function(e) {
            const message = this.getAttribute('data-confirm');
            if (!confirm(message)) {
                e.preventDefault();
            }
        });
    });
}

/**
 * Setup global loading state on form submissions to prevent double submit
 */
function setupFormLoaders() {
    console.log('setupFormLoaders initialized on page');
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            console.log('Form submit event intercepted', form);
            
            // Skip loader for target="_blank" forms (e.g. print statements) to prevent permanent loading spinner
            if (form.getAttribute('target') === '_blank') {
                console.log('Form has target="_blank", skipping loader');
                return;
            }
            
            // Check if form is valid (for HTML5 validation) before triggering loader
            if (form.checkValidity && !form.checkValidity()) {
                console.log('Form failed HTML5 validation, skipping loader');
                return;
            }
            
            const submitBtn = form.querySelector('[type="submit"]');
            if (submitBtn) {
                // Prevent duplicate submit by disabling the button
                // Use setTimeout to ensure the submit event is processed before disabling
                setTimeout(() => {
                    // If another script called e.preventDefault(), don't disable/show loader
                    if (e.defaultPrevented) {
                        console.log('Submission was prevented by another script, cancelling loader');
                        return;
                    }
                    console.log('Applying loading state to button:', submitBtn);
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Loading...';
                }, 10);
            } else {
                console.log('No submit button found in form');
            }
        });
    });
}

/**
 * Format currency
 */
function formatCurrency(amount) {
    return 'Rs. ' + parseFloat(amount).toFixed(2);
}

/**
 * Format date
 */
function formatDate(dateStr) {
    const date = new Date(dateStr);
    const day = String(date.getDate()).padStart(2, '0');
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const year = date.getFullYear();
    return `${day}-${month}-${year}`;
}

/**
 * Show alert message
 */
function showAlert(type, message) {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    const container = document.querySelector('.content');
    if (container) {
        container.insertBefore(alertDiv, container.firstChild);
    }
    
    // Auto dismiss after 5 seconds
    setTimeout(() => {
        alertDiv.remove();
    }, 5000);
}

/**
 * Validate email
 */
function validateEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

/**
 * Validate phone number
 */
function validatePhone(phone) {
    const re = /^[0-9]{10,15}$/;
    return re.test(phone.replace(/\D/g, ''));
}

/**
 * Load more functionality
 */
function loadMore(url, containerId) {
    fetch(url)
        .then(response => response.text())
        .then(data => {
            const container = document.getElementById(containerId);
            if (container) {
                container.innerHTML += data;
            }
        })
        .catch(error => console.error('Error loading data:', error));
}

/**
 * Export table to CSV
 */
function exportTableToCSV(tableId, filename) {
    const table = document.getElementById(tableId);
    if (!table) return;
    
    let csv = [];
    const rows = table.querySelectorAll('tr');
    
    rows.forEach(row => {
        let rowData = [];
        row.querySelectorAll('th, td').forEach(cell => {
            rowData.push('"' + cell.textContent.trim().replace(/"/g, '""') + '"');
        });
        csv.push(rowData.join(','));
    });
    
    downloadCSV(csv.join('\n'), filename);
}

/**
 * Download CSV file
 */
function downloadCSV(csv, filename) {
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    window.URL.revokeObjectURL(url);
    document.body.removeChild(a);
}

/**
 * Print page
 */
function printPage() {
    window.print();
}

/**
 * Setup date inputs with min/max
 */
function setupDateInputs() {
    const today = new Date().toISOString().split('T')[0];
    const dateInputs = document.querySelectorAll('input[type="date"]');
    dateInputs.forEach(input => {
        if (!input.hasAttribute('min')) {
            input.setAttribute('min', '2020-01-01');
        }
        if (!input.hasAttribute('max')) {
            input.setAttribute('max', today);
        }
    });
}

/**
 * Debounce function for search
 */
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

/**
 * Setup live search
 */
function setupLiveSearch(inputSelector, tableSelector) {
    const searchInput = document.querySelector(inputSelector);
    const table = document.querySelector(tableSelector);
    
    if (!searchInput || !table) return;
    
    searchInput.addEventListener('keyup', debounce(function() {
        const searchTerm = this.value.toLowerCase();
        const rows = table.querySelectorAll('tbody tr');
        
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(searchTerm) ? '' : 'none';
        });
    }, 300));
}

/**
 * Calculate total from table column
 */
function calculateTableTotal(tableId, columnIndex) {
    const table = document.getElementById(tableId);
    if (!table) return 0;
    
    let total = 0;
    const rows = table.querySelectorAll('tbody tr');
    
    rows.forEach(row => {
        const cell = row.cells[columnIndex];
        if (cell) {
            const value = parseFloat(cell.textContent.replace(/[^0-9.-]+/g, '')) || 0;
            total += value;
        }
    });
    
    return total;
}

/**
 * Copy text to clipboard
 */
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        showAlert('success', 'Copied to clipboard!');
    }).catch(() => {
        showAlert('danger', 'Failed to copy!');
    });
}

/**
 * Setup form auto-save draft
 */
function setupAutoSaveDraft(formId, storageKey) {
    const form = document.getElementById(formId);
    if (!form) return;
    
    // Load saved draft
    const savedData = localStorage.getItem(storageKey);
    if (savedData) {
        const data = JSON.parse(savedData);
        Object.keys(data).forEach(key => {
            const input = form.querySelector(`[name="${key}"]`);
            if (input) {
                input.value = data[key];
            }
        });
    }
    
    // Save draft on input change
    form.addEventListener('change', function() {
        const formData = new FormData(form);
        const data = Object.fromEntries(formData);
        localStorage.setItem(storageKey, JSON.stringify(data));
    });
    
    // Clear draft on submit
    form.addEventListener('submit', function() {
        localStorage.removeItem(storageKey);
    });
}

/**
 * Developer Notifications System
 * Shows notifications posted by developer until user clicks "Got It"
 */
let devNotificationQueue = [];

function getAppBasePath() {
    const path = window.location.pathname.toLowerCase();
    if (path.includes('/master/') || path.includes('/finance/') || path.includes('/admission/') || path.includes('/teacher/') || path.includes('/developer/')) {
        return '../';
    }
    return '';
}

function formatNotificationText(text) {
    if (!text) return '';
    // Decode any pre-escaped HTML entities from older DB records first, then safely escape HTML
    const textarea = document.createElement('textarea');
    textarea.innerHTML = text.toString();
    const decoded = textarea.value;

    return escapeHtml(decoded)
        .replace(/\\r\\n|\\n|\\r/g, '<br>')
        .replace(/\r\n|\n|\r/g, '<br>');
}

function checkDeveloperNotifications() {
    // Only check if logged in (avoid running on public login page if not needed)
    if (document.body.classList.contains('login-body')) return;

    const basePath = getAppBasePath();
    fetch(`${basePath}get_notifications.php`)
        .then(res => {
            if (!res.ok) return [];
            return res.json();
        })
        .then(notifications => {
            if (Array.isArray(notifications) && notifications.length > 0) {
                devNotificationQueue = notifications;
                showNextDeveloperNotification();
            }
        })
        .catch(err => {
            // Silently ignore network/auth errors
            console.log('Notification check skipped:', err);
        });
}

function showNextDeveloperNotification() {
    if (devNotificationQueue.length === 0) {
        const existingModal = document.getElementById('devNotificationModal');
        if (existingModal) {
            const modalInstance = bootstrap.Modal.getInstance(existingModal);
            if (modalInstance) modalInstance.hide();
        }
        return;
    }

    const currentNotif = devNotificationQueue[0];
    let modalEl = document.getElementById('devNotificationModal');
    
    const typeIcons = {
        'info': 'fa-info-circle text-info',
        'warning': 'fa-exclamation-triangle text-warning',
        'success': 'fa-check-circle text-success',
        'danger': 'fa-radiation text-danger'
    };
    const iconClass = typeIcons[currentNotif.type] || 'fa-bell text-warning';

    const modalHtml = `
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content shadow-lg border-0" style="border-radius: 14px; overflow: hidden;">
                <div class="modal-header bg-dark text-white py-3 px-4">
                    <h5 class="modal-title d-flex align-items-center gap-2 mb-0" style="font-size: 1.1rem; font-weight: 600;">
                        <i class="fas ${iconClass}"></i>
                        <span>${formatNotificationText(currentNotif.title)}</span>
                    </h5>
                </div>
                <div class="modal-body p-4" style="font-size: 1rem; color: #2d3748; line-height: 1.6; background: #fafbfc;">
                    <div class="p-3 bg-white rounded border mb-3">
                        ${formatNotificationText(currentNotif.message)}
                    </div>
                    <div class="d-flex justify-content-between align-items-center text-muted small">
                        <span><i class="fas fa-code me-1"></i> Developer Notice</span>
                        <span><i class="far fa-clock me-1"></i> ${escapeHtml(currentNotif.created_at || '')}</span>
                    </div>
                </div>
                <div class="modal-footer justify-content-center bg-light py-3 border-top-0">
                    <button type="button" class="btn btn-primary px-5 py-2 fw-bold" style="border-radius: 8px; font-size: 1rem;" onclick="dismissDevNotification(${currentNotif.id}, this)">
                        <i class="fas fa-check me-2"></i> Got It
                    </button>
                </div>
            </div>
        </div>
    `;

    if (!modalEl) {
        modalEl = document.createElement('div');
        modalEl.id = 'devNotificationModal';
        modalEl.className = 'modal fade';
        modalEl.setAttribute('tabindex', '-1');
        modalEl.setAttribute('data-bs-backdrop', 'static');
        modalEl.setAttribute('data-bs-keyboard', 'false');
        modalEl.innerHTML = modalHtml;
        document.body.appendChild(modalEl);
    } else {
        modalEl.innerHTML = modalHtml;
    }

    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    modal.show();
}

function dismissDevNotification(notifId, btn) {
    const basePath = getAppBasePath();
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Please wait...';
    }

    fetch(`${basePath}dismiss_notification.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `notif_id=${encodeURIComponent(notifId)}`
    })
    .then(res => res.json())
    .then(data => {
        // Remove dismissed notification from queue
        devNotificationQueue = devNotificationQueue.filter(n => n.id != notifId);
        showNextDeveloperNotification();
    })
    .catch(err => {
        console.error('Error dismissing notification:', err);
        // Fallback: still advance queue
        devNotificationQueue = devNotificationQueue.filter(n => n.id != notifId);
        showNextDeveloperNotification();
    });
}

function escapeHtml(text) {
    if (!text) return '';
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.toString().replace(/[&<>"']/g, m => map[m]);
}

console.log('School Finance Management System - JavaScript loaded successfully');