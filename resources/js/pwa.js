// PWA install support.
//
// Chrome/Edge fire `beforeinstallprompt` once the manifest and service
// worker check out. We stash that event and reveal every
// `[data-pwa-install]` button; clicking one replays the prompt. The
// sidebar (where the button lives) is re-rendered on every wire:navigate,
// so the buttons are re-synced on `livewire:navigated` and clicks are
// delegated from `document` rather than bound to the element.
let deferredPrompt = null;

const isStandalone = () =>
    window.matchMedia('(display-mode: standalone)').matches
    || window.navigator.standalone === true;

const syncInstallButtons = () => {
    const show = deferredPrompt !== null && ! isStandalone();

    document.querySelectorAll('[data-pwa-install]').forEach((el) => {
        el.hidden = ! show;
    });
};

const registerServiceWorker = () => {
    if (! ('serviceWorker' in navigator)) {
        return;
    }

    navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch((error) => {
        console.warn('Service worker registration failed', error);
    });
};

window.addEventListener('beforeinstallprompt', (event) => {
    // Suppress the browser's own mini-infobar; we surface our own button.
    event.preventDefault();
    deferredPrompt = event;
    syncInstallButtons();
});

window.addEventListener('appinstalled', () => {
    deferredPrompt = null;
    syncInstallButtons();
});

document.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-pwa-install]');

    if (! button || ! deferredPrompt) {
        return;
    }

    event.preventDefault();

    const prompt = deferredPrompt;
    deferredPrompt = null;
    syncInstallButtons();

    prompt.prompt();

    const { outcome } = await prompt.userChoice;

    if (outcome === 'dismissed') {
        // The event can be replayed until the user accepts, so keep it.
        deferredPrompt = prompt;
        syncInstallButtons();
    }
});

document.addEventListener('livewire:navigated', syncInstallButtons);

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        registerServiceWorker();
        syncInstallButtons();
    });
} else {
    registerServiceWorker();
    syncInstallButtons();
}
