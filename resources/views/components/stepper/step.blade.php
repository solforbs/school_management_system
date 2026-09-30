@props(['id', 'icon' => null, 'title', 'description' => null])

{{-- Mobile Pill Presentation (horizontal scroll strip) --}}
<li class="block md:hidden shrink-0">
    <button type="button" @click.prevent="scrollTo('{{ $id }}')"
        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-bold transition-all border whitespace-nowrap shadow-xs"
        :class="activeSection === '{{ $id }}' 
            ? 'bg-zinc-900 text-white border-zinc-900 shadow-xs dark:bg-white dark:text-zinc-900' 
            : (isCompleted('{{ $id }}') 
                ? 'bg-emerald-50 text-emerald-800 border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300' 
                : 'bg-zinc-100 text-zinc-600 border-zinc-200/80 hover:bg-zinc-200/60 dark:bg-zinc-900 dark:text-zinc-400')">
        <span class="size-2 rounded-full transition-colors shrink-0"
              :class="activeSection === '{{ $id }}' 
                  ? 'bg-emerald-400' 
                  : (isCompleted('{{ $id }}') ? 'bg-emerald-600' : 'bg-zinc-400')"></span>
        <span>{{ $title }}</span>
    </button>
</li>

{{-- Desktop Timeline Presentation --}}
<li class="hidden md:block relative pb-10 last:pb-0">
    {{-- Vertical connecting line --}}
    <div class="absolute top-4 left-4 -ml-px h-full w-0.5 bg-zinc-200 dark:bg-zinc-700 transition-colors duration-500" 
         aria-hidden="true"
         :class="isCompleted('{{ $id }}') ? 'bg-blue-600 dark:bg-blue-500' : ''"></div>
         
    <a href="#{{ $id }}" class="relative flex items-start group" @click.prevent="scrollTo('{{ $id }}')">
        <span class="h-9 flex items-center shrink-0">
            <span class="relative z-10 w-8 h-8 flex items-center justify-center bg-white dark:bg-zinc-950 border-2 rounded-full transition-colors duration-300"
                  :class="isCompleted('{{ $id }}') ? 'border-blue-600' : 'border-zinc-300 dark:border-zinc-700 group-hover:border-zinc-400 dark:group-hover:border-zinc-500'">
                <span class="h-2.5 w-2.5 rounded-full transition-colors duration-300" 
                      :class="isCompleted('{{ $id }}') ? 'bg-blue-600 dark:bg-blue-500' : 'bg-transparent group-hover:bg-zinc-300 dark:group-hover:bg-zinc-600'"></span>
            </span>
        </span>
        <span class="ml-4 min-w-0 flex flex-col pt-1.5">
            <span class="text-sm font-semibold tracking-wide transition-colors duration-300" 
                  :class="isCompleted('{{ $id }}') ? 'text-blue-600 dark:text-blue-400' : 'text-zinc-600 dark:text-zinc-400 group-hover:text-zinc-900 dark:group-hover:text-zinc-300'">
                {{ $title }}
            </span>
            @if($description)
                <span class="text-sm text-zinc-500 dark:text-zinc-500 mt-0.5">{{ $description }}</span>
            @endif
        </span>
    </a>
</li>
