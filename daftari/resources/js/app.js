import './bootstrap';
import Alpine from 'alpinejs';

document.addEventListener('alpine:init', () => {
    Alpine.directive('reveal', (el) => {
        el.classList.add('reveal-init');
        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('reveal-in');
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.12 });
        observer.observe(el);
    });
});

window.Alpine = Alpine;
Alpine.start();

// Show/hide toggle for every password field in the app — applied globally
// so login, register, reset-password, settings, and admin forms all get it
// without editing each form individually.
(function () {
    const EYE = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" class="h-[18px] w-[18px]"><path d="M2.5 12S6 5 12 5s9.5 7 9.5 7-3.5 7-9.5 7-9.5-7-9.5-7z"/><circle cx="12" cy="12" r="3"/></svg>';
    const EYE_OFF = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" class="h-[18px] w-[18px]"><path d="M3 3l18 18"/><path d="M10.6 5.1A9.7 9.7 0 0112 5c6 0 9.5 7 9.5 7a15.6 15.6 0 01-3.1 3.9M6.5 6.6C4 8.3 2.5 12 2.5 12s3.5 7 9.5 7a9.6 9.6 0 004.6-1.2"/><path d="M9.9 9.9a3 3 0 004.2 4.2"/></svg>';

    function wrap(input) {
        if (input.dataset.eyeWrapped || input.closest('[data-eye-wrap]')) {
            return;
        }
        input.dataset.eyeWrapped = '1';

        const wrapper = document.createElement('div');
        wrapper.className = 'relative';
        wrapper.setAttribute('data-eye-wrap', '');
        input.parentNode.insertBefore(wrapper, input);
        wrapper.appendChild(input);
        input.style.paddingInlineEnd = '2.75rem';

        const button = document.createElement('button');
        button.type = 'button';
        button.tabIndex = -1;
        button.setAttribute('aria-label', 'Toggle password visibility');
        button.className = 'absolute inset-y-0 end-0 flex items-center pe-3 text-slate-400 transition-colors hover:text-slate-600';
        button.innerHTML = EYE;
        button.addEventListener('click', () => {
            const showing = input.type === 'text';
            input.type = showing ? 'password' : 'text';
            button.innerHTML = showing ? EYE : EYE_OFF;
        });
        wrapper.appendChild(button);
    }

    function scan(root) {
        root.querySelectorAll('input[type="password"]').forEach(wrap);
    }

    document.addEventListener('DOMContentLoaded', () => scan(document));

    new MutationObserver((mutations) => {
        mutations.forEach((m) => {
            m.addedNodes.forEach((node) => {
                if (node.nodeType !== 1) return;
                if (node.matches?.('input[type="password"]')) wrap(node);
                scan(node);
            });
        });
    }).observe(document.documentElement, { childList: true, subtree: true });
})();
