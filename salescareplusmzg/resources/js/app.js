document.addEventListener('DOMContentLoaded', () => {
    const toggle = document.getElementById('nav-toggle');
    const menu = document.getElementById('mobile-menu');
    const openIcon = document.getElementById('nav-icon-open');
    const closeIcon = document.getElementById('nav-icon-close');

    if (toggle && menu) {
        toggle.addEventListener('click', () => {
            const isHidden = menu.classList.toggle('hidden');
            toggle.setAttribute('aria-expanded', String(!isHidden));
            openIcon?.classList.toggle('hidden');
            closeIcon?.classList.toggle('hidden');
        });
    }

    initScrollReveal();
    initCounters();
    initHeaderShadow();
});

function initScrollReveal() {
    const targets = document.querySelectorAll('.reveal, .reveal-scale, .reveal-stagger');

    if (!targets.length) return;

    if (!('IntersectionObserver' in window) || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        targets.forEach((el) => el.classList.add('is-visible'));
        return;
    }

    const observer = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    observer.unobserve(entry.target);
                }
            });
        },
        { threshold: 0.15, rootMargin: '0px 0px -40px 0px' }
    );

    targets.forEach((el) => observer.observe(el));
}

function initCounters() {
    const counters = document.querySelectorAll('[data-counter]');

    if (!counters.length) return;

    const animate = (el) => {
        const target = parseInt(el.dataset.counter, 10) || 0;
        const suffix = el.dataset.counterSuffix || '';
        const duration = 1200;
        const start = performance.now();

        const step = (now) => {
            const progress = Math.min((now - start) / duration, 1);
            const eased = 1 - Math.pow(1 - progress, 3);
            el.textContent = Math.round(eased * target) + suffix;
            if (progress < 1) requestAnimationFrame(step);
        };

        requestAnimationFrame(step);
    };

    if (!('IntersectionObserver' in window) || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        counters.forEach((el) => {
            el.textContent = (el.dataset.counter || '0') + (el.dataset.counterSuffix || '');
        });
        return;
    }

    const observer = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    animate(entry.target);
                    observer.unobserve(entry.target);
                }
            });
        },
        { threshold: 0.5 }
    );

    counters.forEach((el) => observer.observe(el));
}

function initHeaderShadow() {
    const header = document.querySelector('[data-site-header]');

    if (!header) return;

    const onScroll = () => {
        header.classList.toggle('shadow-md', window.scrollY > 8);
    };

    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });
}
