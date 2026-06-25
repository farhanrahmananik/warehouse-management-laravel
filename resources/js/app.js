import './bootstrap';
import 'bootstrap/dist/js/bootstrap.bundle.min.js';

document.addEventListener('DOMContentLoaded', () => {
    const sidebarToggles = document.querySelectorAll('[data-sidebar-toggle]');
    const sidebarCloseTriggers = document.querySelectorAll('[data-sidebar-close]');

    const setSidebarOpen = (isOpen) => {
        document.body.classList.toggle('sidebar-open', isOpen);

        sidebarToggles.forEach((toggle) => {
            toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });
    };

    sidebarToggles.forEach((toggle) => {
        toggle.addEventListener('click', () => {
            setSidebarOpen(! document.body.classList.contains('sidebar-open'));
        });
    });

    sidebarCloseTriggers.forEach((trigger) => {
        trigger.addEventListener('click', () => {
            setSidebarOpen(false);
        });
    });

    document.querySelectorAll('.sidebar-link').forEach((link) => {
        link.addEventListener('click', () => {
            if (window.innerWidth < 992) {
                setSidebarOpen(false);
            }
        });
    });

    window.addEventListener('resize', () => {
        if (window.innerWidth >= 992) {
            setSidebarOpen(false);
        }
    });
});
