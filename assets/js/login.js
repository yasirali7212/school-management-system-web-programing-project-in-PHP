/**
 * Roots School System - Login Page Interactions
 */

document.addEventListener('DOMContentLoaded', () => {
    // 1. Password Visibility Toggle
    const passInput = document.getElementById('password-input');
    const toggleBtn = document.getElementById('toggle-password');
    const eyeIcon = document.getElementById('eye-icon');

    if (toggleBtn && passInput && eyeIcon) {
        toggleBtn.addEventListener('click', () => {
            const isPassword = passInput.getAttribute('type') === 'password';
            passInput.setAttribute('type', isPassword ? 'text' : 'password');
            eyeIcon.textContent = isPassword ? 'visibility_off' : 'visibility';
        });
    }

    // 2. Role Selector Tabs & Hidden Input Binding
    const roleTabs = document.querySelectorAll('.role-tab');
    const roleInput = document.getElementById('selected-role');
    const identityLabel = document.getElementById('identity-label');
    const identityInput = document.getElementById('identity-input');

    const placeholders = {
        student: { label: 'School Email or Student ID', ph: 'student.id@roots.edu' },
        teacher: { label: 'Faculty Email or Staff ID', ph: 'faculty.first@roots.edu' },
        parent:  { label: 'Registered Guardian Email / Phone', ph: 'guardian@domain.com' },
        admin:   { label: 'Administrator Identity Code', ph: 'admin.super@roots.edu' }
    };

    roleTabs.forEach((tab) => {
        tab.addEventListener('click', () => {
            // Remove active styles from all tabs
            roleTabs.forEach(t => {
                t.classList.remove('bg-surface-container-lowest', 'text-primary', 'font-bold', 'shadow-sm');
                t.classList.add('text-on-surface-variant');
            });

            // Add active styles to clicked tab
            tab.classList.add('bg-surface-container-lowest', 'text-primary', 'font-bold', 'shadow-sm');
            tab.classList.remove('text-on-surface-variant');

            // Update role value for PHP backend submission
            const role = tab.getAttribute('data-role');
            if (roleInput) {
                roleInput.value = role;
            }

            // Dynamically change label and placeholder
            if (placeholders[role] && identityLabel && identityInput) {
                identityLabel.textContent = placeholders[role].label;
                identityInput.setAttribute('placeholder', placeholders[role].ph);
            }
        });
    });
});
