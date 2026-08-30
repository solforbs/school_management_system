@props(['defaultActive' => null])

<div class="flex flex-col md:flex-row gap-8" 
    x-data="stepperScrollSpy('{{ $defaultActive }}')"
    x-init="initObserver()"
>
    
    {{-- Sticky Sidebar Navigation --}}
    <div class="w-full md:w-1/4">
        <div class="sticky top-8 flex flex-col gap-6">
            <nav class="relative">
                
                <ol role="list" class="overflow-hidden">
                    {{ $sidebar }}
                </ol>
            </nav>
        </div>
    </div>

    {{-- Scrollable Form Content --}}
    <div class="w-full md:w-3/4 flex flex-col gap-8 pb-32">
        {{ $slot }}

        {{-- Action Footer (Sticky to bottom of screen for easy access) --}}
        @isset($actions)
            <div class="fixed bottom-0 left-0 right-0 z-50 bg-white/90 dark:bg-zinc-950/90 backdrop-blur-md border-t border-zinc-200 dark:border-zinc-800 px-6 py-4 flex justify-end gap-3 md:left-64 lg:left-64 shadow-[0_-4px_6px_-1px_rgba(0,0,0,0.05)]">
                {{ $actions }}
            </div>
        @endisset
    </div>
</div>

@once
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('stepperScrollSpy', (defaultActive) => ({
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
                    
                    entries.forEach(entry => {
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
                    threshold: [0, 0.25, 0.5, 0.75, 1]
                });

                this.$nextTick(() => {
                    const sectionEls = Array.from(this.$root.querySelectorAll('.stepper-section'));
                    this.sections = sectionEls.map(el => el.id);
                    
                    sectionEls.forEach((el) => {
                        observer.observe(el);
                    });
                    
                    if (!this.activeSection && this.sections.length > 0) {
                        this.activeSection = this.sections[0];
                    }
                });
            },
            
            scrollTo(id) {
                this.activeSection = id;
                document.getElementById(id).scrollIntoView({ behavior: 'smooth' });
            }
        }))
    });
</script>
@endonce
