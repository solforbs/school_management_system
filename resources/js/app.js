function initScrollReveal() {
    const items = document.querySelectorAll('[data-animate]:not([data-animate-in])');

    if (! items.length) {
        return;
    }

    if (! ('IntersectionObserver' in window)) {
        items.forEach((el) => el.setAttribute('data-animate-in', ''));
        return;
    }

    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                entry.target.setAttribute('data-animate-in', '');
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.15, rootMargin: '0px 0px -60px 0px' });

    items.forEach((el) => observer.observe(el));
}

// Vite emits this as a `type="module"` script, which loads deferred - by
// the time it runs, `DOMContentLoaded` may have already fired, so a plain
// listener would never call back. Run immediately if the document is
// already parsed, otherwise wait for it like normal.
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initScrollReveal);
} else {
    initScrollReveal();
}

document.addEventListener('livewire:navigated', initScrollReveal);

// Register Alpine.js components
const registerAlpineComponents = () => {
    if (typeof window.Alpine === 'undefined') return;
    if (window.Alpine._appAlpineComponentsRegistered) return;
    window.Alpine._appAlpineComponentsRegistered = true;

    // Scroll-spy stepper used by every multi-step form (student, teacher,
    // parent, exam, ...). Registered here rather than in an inline
    // `alpine:init` listener because that event only fires on a full page
    // load - after a `wire:navigate` swap Alpine is already running, so the
    // component would be undefined and `activeSection` would throw.
    window.Alpine.data('stepperScrollSpy', (defaultActive) => ({
        activeSection: defaultActive,
        sections: [],

        get activeIndex() {
            return this.sections.indexOf(this.activeSection);
        },

        isCompleted(id) {
            const index = this.sections.indexOf(id);
            return index !== -1 && index <= this.activeIndex;
        },

        initObserver() {
            const observer = new IntersectionObserver((entries) => {
                let maxRatio = 0;
                let activeId = null;

                entries.forEach((entry) => {
                    if (entry.isIntersecting && entry.intersectionRatio > maxRatio) {
                        maxRatio = entry.intersectionRatio;
                        activeId = entry.target.id;
                    }
                });

                if (activeId) {
                    this.activeSection = activeId;
                }
            }, {
                rootMargin: '-10% 0px -70% 0px',
                threshold: [0, 0.25, 0.5, 0.75, 1],
            });

            this.$nextTick(() => {
                const sectionEls = Array.from(this.$root.querySelectorAll('.stepper-section'));
                this.sections = sectionEls.map((el) => el.id);

                sectionEls.forEach((el) => observer.observe(el));

                if (!this.activeSection && this.sections.length > 0) {
                    this.activeSection = this.sections[0];
                }
            });
        },

        scrollTo(id) {
            this.activeSection = id;
            document.getElementById(id).scrollIntoView({ behavior: 'smooth' });
        },
    }));

    window.Alpine.data('animatedCounter', (targetValue = 0, decimals = 0, prefix = '', suffix = '') => ({
        target: parseFloat(targetValue) || 0,
        decimals: parseInt(decimals) || 0,
        prefix: prefix || '',
        suffix: suffix || '',
        displayCount: '',
        format(val) {
            let numStr = this.decimals > 0
                ? Number(val).toLocaleString(undefined, { minimumFractionDigits: this.decimals, maximumFractionDigits: this.decimals })
                : Math.round(val).toLocaleString();
            return this.prefix + numStr + this.suffix;
        },
        init() {
            const finalValue = this.target;
            
            if (finalValue <= 0) {
                this.displayCount = this.format(0);
                return;
            }

            // Anti-jitter: for large numbers, starting at 0 causes rapid width expansion (1 char -> 18 chars)
            // Starting within the same order of magnitude keeps character count constant and eliminates screen shaking
            let startValue = 0;
            if (finalValue >= 1000000) {
                startValue = Math.floor(finalValue * 0.92);
            } else if (finalValue >= 100000) {
                startValue = Math.floor(finalValue * 0.85);
            } else if (finalValue >= 1000) {
                startValue = Math.floor(finalValue * 0.70);
            } else if (finalValue >= 100) {
                startValue = Math.floor(finalValue * 0.50);
            }

            this.displayCount = this.format(startValue);

            const duration = 550; // Snappy 550ms duration
            let start = null;
            
            const step = (timestamp) => {
                if (!start) start = timestamp;
                const progress = Math.min((timestamp - start) / duration, 1);
                
                // Quintic ease out (silky smooth tail resolution)
                const easeOut = 1 - Math.pow(1 - progress, 4);
                const current = startValue + (easeOut * (finalValue - startValue));
                this.displayCount = this.format(current);
                
                if (progress < 1) {
                    window.requestAnimationFrame(step);
                } else {
                    this.displayCount = this.format(finalValue);
                }
            };
            
            window.requestAnimationFrame(step);
        }
    }));
};

if (typeof window.Alpine !== 'undefined') {
    registerAlpineComponents();
}
document.addEventListener('alpine:init', registerAlpineComponents);
document.addEventListener('livewire:navigated', registerAlpineComponents);
