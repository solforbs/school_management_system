// Full-screen preloader (resources/views/components/preloader.blade.php).
//
// The overlay ships visible in the HTML so it covers the first paint, and
// is faded out here once the page is ready. It comes back for wire:navigate
// swaps and for full-page loads (form posts, logout, hard links). Every
// layout renders its own copy, so the element is looked up on each call
// rather than cached - a wire:navigate swap replaces it.
const FADE_MS = 300;

let hideTimer = null;

const overlay = () => document.querySelector('[data-preloader]');

const show = () => {
    const el = overlay();

    if (! el) {
        return;
    }

    clearTimeout(hideTimer);
    el.hidden = false;
    el.setAttribute('aria-busy', 'true');
    requestAnimationFrame(() => el.classList.remove('opacity-0', 'pointer-events-none'));
};

const hide = () => {
    const el = overlay();

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

document.addEventListener('livewire:navigating', show);
document.addEventListener('livewire:navigated', hide);

// Full-page navigations. `beforeunload` does not fire for file downloads,
// so export links never leave the overlay stuck; `pageshow` covers a
// back/forward restore from the bfcache, where no load event runs.
window.addEventListener('beforeunload', show);
window.addEventListener('pageshow', hide);

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', hide);
} else {
    hide();
}
