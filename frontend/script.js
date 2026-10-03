function showForm(formName) {
    document.querySelectorAll('.form-box').forEach(function (form) {
        form.classList.toggle('active', form.id === formName + 'Box');
    });
}

document.querySelectorAll('[data-form]').forEach(function (link) {
    link.addEventListener('click', function (event) {
        event.preventDefault();
        showForm(link.dataset.form);
    });
});

document.querySelectorAll('[data-account-menu]').forEach(function (menu) {
    var toggle = menu.querySelector('[data-menu-toggle]');
    var dropdown = menu.querySelector('.account-dropdown');

    if (!toggle || !dropdown) {
        return;
    }

    function setMenuOpen(isOpen) {
        toggle.setAttribute('aria-expanded', String(isOpen));
        dropdown.hidden = !isOpen;
        menu.classList.toggle('is-open', isOpen);
    }

    toggle.addEventListener('click', function () {
        setMenuOpen(toggle.getAttribute('aria-expanded') !== 'true');
    });

    document.addEventListener('click', function (event) {
        if (!menu.contains(event.target)) {
            setMenuOpen(false);
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            setMenuOpen(false);
            toggle.focus();
        }
    });

    dropdown.addEventListener('click', function (event) {
        if (event.target.closest('a')) {
            setMenuOpen(false);
        }
    });
});

document.querySelectorAll('[data-profile-modal]').forEach(function (modal) {
    var closeLink = modal.querySelector('[data-modal-close]');
    modal.focus();

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && closeLink) {
            closeLink.click();
            return;
        }

        if (event.key === 'Tab') {
            var focusableElements = modal.querySelectorAll('a[href], button:not([disabled]), input:not([disabled]), textarea:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])');
            var firstElement = focusableElements[0];
            var lastElement = focusableElements[focusableElements.length - 1];

            if (event.shiftKey && document.activeElement === firstElement) {
                event.preventDefault();
                lastElement.focus();
            } else if (!event.shiftKey && document.activeElement === lastElement) {
                event.preventDefault();
                firstElement.focus();
            }
        }
    });

    modal.addEventListener('click', function (event) {
        if (event.target === modal && closeLink) {
            closeLink.click();
        }
    });
});