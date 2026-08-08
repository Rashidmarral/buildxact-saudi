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
});
