// Responsive navigation toggle
document.addEventListener('DOMContentLoaded', () => {
    const navToggle = document.getElementById('nav-toggle');
    const navMenu = document.getElementById('nav-menu');

    if (navToggle && navMenu) {
        navToggle.addEventListener('click', () => {
            navMenu.classList.toggle('hidden');
        });
    }
});

// Admin Modal Management System
function openAdminModal(modalId) {
    const modal = document.getElementById(modalId);
    if (!modal) {
        console.error('Modal element not found with ID:', modalId);
        return;
    }
    modal.classList.remove('hidden');
    modal.style.display = 'block';
    document.body.classList.add('overflow-hidden');

    // Auto-focus first input if available
    const firstInput = modal.querySelector('input:not([type=hidden]), select, textarea');
    if (firstInput) {
        setTimeout(() => firstInput.focus(), 50);
    }
}

function closeAdminModal(modalId) {
    const modal = document.getElementById(modalId);
    if (!modal) return;
    modal.classList.add('hidden');
    modal.style.display = 'none';

    // Only remove overflow-hidden if no other modals are open
    const openModals = document.querySelectorAll('[role="dialog"]:not(.hidden)');
    if (openModals.length === 0) {
        document.body.classList.remove('overflow-hidden');
    }
}

// Delete Confirmation Modal Helper
function openAdminDeleteModal(config) {
    const modal = document.getElementById('admin-delete-modal');
    if (!modal) return;

    const form = document.getElementById('admin-delete-form');
    const actionInput = document.getElementById('delete-form-action');
    const idInput = document.getElementById('delete-form-id');
    const titleEl = document.getElementById('delete-modal-title');
    const msgEl = document.getElementById('delete-modal-message');
    const warnEl = document.getElementById('delete-modal-warning');
    const submitBtn = document.getElementById('delete-form-submit-btn');

    if (config.url && form) form.action = config.url;
    if (config.action && actionInput) actionInput.value = config.action;
    if (config.idName && idInput) idInput.name = config.idName;
    if (config.idValue !== undefined && idInput) idInput.value = config.idValue;
    if (config.title && titleEl) titleEl.textContent = config.title;
    if (config.message && msgEl) msgEl.textContent = config.message;
    if (config.submitLabel && submitBtn) submitBtn.textContent = config.submitLabel;

    if (warnEl) {
        if (config.warning) {
            warnEl.textContent = config.warning;
            warnEl.classList.remove('hidden');
            warnEl.style.display = 'block';
        } else {
            warnEl.classList.add('hidden');
            warnEl.style.display = 'none';
            warnEl.textContent = '';
        }
    }

    modal.classList.remove('hidden');
    modal.style.display = 'block';
    document.body.classList.add('overflow-hidden');
}

function closeAdminDeleteModal() {
    closeAdminModal('admin-delete-modal');
}

// Global Escape Key Listener for Modals
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        const openModals = document.querySelectorAll('[role="dialog"]');
        openModals.forEach((m) => {
            m.classList.add('hidden');
            m.style.display = 'none';
        });
        document.body.classList.remove('overflow-hidden');
    }
});
