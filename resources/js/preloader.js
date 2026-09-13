// Full-screen preloader (resources/views/components/preloader.blade.php).
//
// The overlay ships visible in the HTML so it covers the first paint, and
// is faded out here once the page is ready. It comes back only for real
// page transitions: wire:navigate swaps and plain (non-Livewire) form
// posts such as login and logout. Livewire AJAX updates never touch it.
// Every layout renders its own copy, so the element is looked up on each
// call rather than cached - a wire:navigate swap replaces it.
const FADE_MS = 300;

// A form post that turns into a file download, or a navigation the
// browser cancels, never unloads the page - so nothing would ever hide
// the overlay again. Drop it after this long if we are still here.
const STUCK_MS = 8000;

let hideTimer = null;
let stuckTimer = null;

const overlay = () => document.querySelector('[data-preloader]');

const show = ({ guard = false } = {}) => {
    const el = overlay();

    if (! el) {
        return;
    }

    clearTimeout(hideTimer);
    clearTimeout(stuckTimer);
    el.hidden = false;
    el.setAttribute('aria-busy', 'true');
    requestAnimationFrame(() => el.classList.remove('opacity-0', 'pointer-events-none'));

    if (guard) {
        stuckTimer = setTimeout(hide, STUCK_MS);
    }
};

const hide = () => {
    const el = overlay();

    clearTimeout(stuckTimer);

    if (! el || el.hidden) {
        return;
    }

    el.classList.add('opacity-0', 'pointer-events-none');
    el.setAttribute('aria-busy', 'false');
    clearTimeout(hideTimer);
    hideTimer = setTimeout(() => {
        el.hidden = true;
    }, FADE_MS);
};

document.addEventListener('livewire:navigating', () => show());
document.addEventListener('livewire:navigated', hide);

// Plain form posts (login, logout, impersonation, ...). Livewire handles
// wire:submit on the form itself and calls preventDefault() before the
// event bubbles this far, so its requests are skipped.
document.addEventListener('submit', (event) => {
    const form = event.target;

    if (event.defaultPrevented || ! (form instanceof HTMLFormElement)) {
        return;
    }

    if (form.target && form.target !== '_self') {
        return;
    }

    show({ guard: true });
});

// A back/forward restore from the bfcache runs no load event.
window.addEventListener('pageshow', hide);

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', hide);
} else {
    hide();
}
