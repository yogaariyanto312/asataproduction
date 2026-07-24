@if ($paginator->hasPages())
<nav class="flex items-center gap-1" aria-label="Pagination">

    {{-- Previous --}}
    @if ($paginator->onFirstPage())
    <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-slate-300 dark:text-slate-600 cursor-not-allowed">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
        </svg>
    </span>
    @else
    <a href="{{ $paginator->previousPageUrl() }}" rel="prev"
       class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-slate-600 dark:text-slate-300
              hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
        </svg>
    </a>
    @endif

    {{-- Page numbers --}}
    @foreach ($elements as $element)
        @if (is_string($element))
        <span class="inline-flex items-center justify-center w-6 h-8 text-xs text-slate-400 dark:text-slate-500 select-none">…</span>
        @endif
        @if (is_array($element))
            @foreach ($element as $page => $url)
                @if ($page == $paginator->currentPage())
                <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-blue-600 text-white text-xs font-bold shadow-sm">{{ $page }}</span>
                @else
                <a href="{{ $url }}"
                   class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-slate-600 dark:text-slate-300
                          text-xs font-medium hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors">{{ $page }}</a>
                @endif
            @endforeach
        @endif
    @endforeach

    {{-- Next --}}
    @if ($paginator->hasMorePages())
    <a href="{{ $paginator->nextPageUrl() }}" rel="next"
       class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-slate-600 dark:text-slate-300
              hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
        </svg>
    </a>
    @else
    <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-slate-300 dark:text-slate-600 cursor-not-allowed">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
        </svg>
    </span>
    @endif

</nav>
@endif
