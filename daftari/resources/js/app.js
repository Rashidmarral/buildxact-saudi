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

// Real-time validation for Saudi VAT/CR/phone fields — mirrors the
// server-side rules in App\Rules\SaudiVatNumber / SaudiCrNumber /
// SaudiPhoneNumber, but enforced as the user types (max length + format)
// instead of only after submit. Applied globally by field `name`, the same
// pattern as the password eye-icon above, so no individual form needs to
// opt in.
(function () {
    const isAr = document.documentElement.lang === 'ar';
    const t = (en, ar) => (isAr ? ar : en);

    const RULES = [
        {
            selector: 'input[name="vat_number"], input[name="party_vat_number"]',
            maxLength: 15,
            inputMode: 'numeric',
            sanitize: (v) => v.replace(/\D/g, '').slice(0, 15),
            check(digits) {
                if (digits.length === 0) return null;
                if (digits.length > 0 && digits[0] !== '3') {
                    return { valid: false, message: t('VAT number must start with 3.', 'يجب أن يبدأ الرقم الضريبي بالرقم 3.') };
                }
                if (digits.length < 15) {
                    return { pending: true, message: t(`${digits.length}/15 digits`, `${digits.length}/15 أرقام`) };
                }
                if (digits[14] !== '3') {
                    return { valid: false, message: t('VAT number must end with 3.', 'يجب أن ينتهي الرقم الضريبي بالرقم 3.') };
                }

                return { valid: true, message: t('Valid VAT number format', 'صيغة الرقم الضريبي صحيحة') };
            },
        },
        {
            selector: 'input[name="cr_number"]',
            maxLength: 10,
            inputMode: 'numeric',
            sanitize: (v) => v.replace(/\D/g, '').slice(0, 10),
            check(digits) {
                if (digits.length === 0) return null;
                if (digits.length < 10) {
                    return { pending: true, message: t(`${digits.length}/10 digits`, `${digits.length}/10 أرقام`) };
                }

                return { valid: true, message: t('Valid CR number format', 'صيغة رقم السجل التجاري صحيحة') };
            },
        },
        {
            selector: 'input[name="phone"], input[name="party_phone"], input[name="bank_phone"]',
            maxLength: 15,
            inputMode: 'tel',
            sanitize: (v) => v.replace(/[^\d+]/g, '').slice(0, 15),
            check(raw) {
                if (raw.length === 0) return null;

                let digits = raw.replace(/\D/g, '');
                if (digits.startsWith('00966')) digits = digits.slice(5);
                else if (digits.startsWith('966')) digits = digits.slice(3);
                else if (digits.startsWith('0')) digits = digits.slice(1);

                if (digits.length < 9) {
                    return { pending: true, message: t(`${digits.length}/9 digits`, `${digits.length}/9 أرقام`) };
                }
                if (!/^[1-9]\d{8}$/.test(digits)) {
                    return { valid: false, message: t('Enter a valid Saudi phone number, e.g. 05XXXXXXXX.', 'أدخل رقم هاتف سعودي صحيح، مثال: 05XXXXXXXX.') };
                }

                return { valid: true, message: t('Valid phone number', 'رقم الهاتف صحيح') };
            },
        },
    ];

    function hint(input) {
        if (input._saudiHint) return input._saudiHint;

        const p = document.createElement('p');
        p.className = 'text-xs mt-1';
        input.insertAdjacentElement('afterend', p);
        input._saudiHint = p;

        return p;
    }

    function applyState(input, result) {
        const p = hint(input);
        input.classList.remove('border-red-400', 'focus:border-red-500', 'focus:ring-red-500', 'border-emerald-400');

        if (! result) {
            p.textContent = '';
            p.className = 'text-xs mt-1';

            return;
        }

        if (result.pending) {
            p.textContent = result.message;
            p.className = 'text-xs mt-1 text-slate-400';

            return;
        }

        if (result.valid) {
            p.textContent = result.message;
            p.className = 'text-xs mt-1 text-emerald-600';
            input.classList.add('border-emerald-400');

            return;
        }

        p.textContent = result.message;
        p.className = 'text-xs mt-1 text-red-600';
        input.classList.add('border-red-400', 'focus:border-red-500', 'focus:ring-red-500');
    }

    function wire(input, rule) {
        if (input.dataset.saudiWired) return;
        input.dataset.saudiWired = '1';
        input.setAttribute('maxlength', String(rule.maxLength));
        input.setAttribute('inputmode', rule.inputMode);

        const run = () => {
            const clean = rule.sanitize(input.value);
            if (clean !== input.value) {
                input.value = clean;
            }
            applyState(input, rule.check(clean));
        };

        input.addEventListener('input', run);
        input.addEventListener('blur', run);

        if (input.value) run();
    }

    function scanAll(root) {
        RULES.forEach((rule) => {
            root.querySelectorAll(rule.selector).forEach((input) => wire(input, rule));
        });
    }

    document.addEventListener('DOMContentLoaded', () => scanAll(document));

    new MutationObserver((mutations) => {
        mutations.forEach((m) => {
            m.addedNodes.forEach((node) => {
                if (node.nodeType !== 1) return;
                RULES.forEach((rule) => {
                    if (node.matches?.(rule.selector)) wire(node, rule);
                });
                scanAll(node);
            });
        });
    }).observe(document.documentElement, { childList: true, subtree: true });
})();

// Registers the PWA service worker (public/sw.js) so the app is installable
// from the browser's "Add to Home Screen" / "Install app" prompt. The
// worker only cache-first's static build assets — it never touches page
// navigations or API calls, so this is safe to register everywhere the
// script loads (marketing site included) without any risk of showing stale
// invoice/balance data.
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => {});
    });
}
