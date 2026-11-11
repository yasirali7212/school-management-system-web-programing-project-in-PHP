/**
 * Roots School System - Registration Page Interactions
 */

// Role Selector Logic
function selectRole(role) {
    const buttons = document.querySelectorAll('.role-btn');
    const roleInput = document.getElementById('selectedRoleInput');

    buttons.forEach(btn => {
        if (btn.getAttribute('data-role') === role) {
            btn.classList.add('bg-surface-container-lowest', 'text-primary', 'shadow-sm', 'font-semibold');
            btn.classList.remove('text-on-surface-variant');
        } else {
            btn.classList.remove('bg-surface-container-lowest', 'text-primary', 'shadow-sm', 'font-semibold');
            btn.classList.add('text-on-surface-variant');
        }
    });

    if (roleInput) {
        roleInput.value = role;
    }

    const idLabel = document.getElementById('enrollmentLabel');
    const idInput = document.getElementById('enrollmentId');
    if (!idLabel || !idInput) return;

    if (role === 'student') {
        idLabel.innerHTML = 'Student ID / Roll No <span class="text-red-500">*</span>';
        idInput.placeholder = 'ID-88492';
    } else if (role === 'parent') {
        idLabel.innerHTML = 'Student Roll No (Child) <span class="text-red-500">*</span>';
        idInput.placeholder = 'ID-88492';
    } else if (role === 'faculty') {
        idLabel.innerHTML = 'Faculty / Staff ID <span class="text-red-500">*</span>';
        idInput.placeholder = 'FAC-102';
    }
}

// Password Field Visibility Toggle
function toggleVisibility(inputId, iconId) {
    const input = document.getElementById(inputId);
    const icon = document.getElementById(iconId);
    if (!input || !icon) return;

    if (input.type === 'password') {
        input.type = 'text';
        icon.textContent = 'visibility_off';
    } else {
        input.type = 'password';
        icon.textContent = 'visibility';
    }
}

// Client-side Validation Before Form Submit
function validateRegistrationForm() {
    const pw = document.getElementById('passwordInput').value;
    const confirm = document.getElementById('confirmPasswordInput').value;

    if (pw !== confirm) {
        alert('Password credentials do not match. Please verify and re-enter.');
        return false;
    }

    if (pw.length < 6) {
        alert('Password must be at least 6 characters long.');
        return false;
    }

    return true;
}
