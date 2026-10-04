@if ($paginator->hasPages())
    @php
        $current = $paginator->currentPage();
        $last    = $paginator->lastPage();

        // Compact window: 1 2 3 ... 16  |  1 ... 7 8 9 ... 16  |  1 ... 14 15 16
        $show = fn ($p) => $p === 1 || $p === $last || abs($p - $current) <= 1
            || ($current <= 2 && $p <= 3) || ($current >= $last - 1 && $p >= $last - 2);
        $pages = [];
        foreach (range(1, $last) as $p) {
            if ($show($p)) {
                $pages[] = $p;
            } elseif (end($pages) !== '...') {
                $pages[] = '...';
            }
        }

        $box   = 'flex h-8 min-w-[2rem] items-center justify-center rounded-lg border px-2.5 text-sm font-medium transition focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-1';
        $live  = 'border-slate-300 bg-white text-slate-600 hover:bg-slate-50 hover:text-brand-800';
        $off   = 'cursor-not-allowed border-slate-200 bg-slate-50 text-slate-300';
        $left  = '<svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M12.79 5.23a.75.75 0 0 1-.02 1.06L9.06 10l3.71 3.71a.75.75 0 1 1-1.06 1.06l-4.24-4.24a.75.75 0 0 1 0-1.06l4.24-4.24a.75.75 0 0 1 1.08 0Z" clip-rule="evenodd"/></svg>';
        $right = '<svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 0 1 .02-1.06L10.94 10 7.23 6.29a.75.75 0 1 1 1.06-1.06l4.24 4.24a.75.75 0 0 1 0 1.06l-4.24 4.24a.75.75 0 0 1-1.08 0Z" clip-rule="evenodd"/></svg>';
    @endphp

    <nav role="navigation" aria-label="Pagination" class="flex flex-col items-center gap-3 sm:flex-row sm:justify-between">
        <p class="text-sm text-slate-500">
            Showing
            <span class="font-semibold text-slate-700">{{ $paginator->firstItem() }}&ndash;{{ $paginator->lastItem() }}</span>
            of
            <span class="font-semibold text-slate-700">{{ number_format($paginator->total()) }}</span>
        </p>

        <ul class="flex items-center gap-1.5">
            {{-- Previous --}}
            <li>
                @if ($paginator->onFirstPage())
                    <span class="{{ $box }} {{ $off }}" aria-disabled="true" aria-label="Previous page">{!! $left !!}</span>
                @else
                    <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="{{ $box }} {{ $live }}" aria-label="Previous page">{!! $left !!}</a>
                @endif
            </li>

            {{-- Phones: "Page 3 of 16" --}}
            <li class="px-3 text-sm text-slate-600 sm:hidden">Page <span class="font-semibold text-slate-800">{{ $current }}</span> of {{ $last }}</li>

            {{-- Larger screens: numbered pages --}}
            @foreach ($pages as $page)
                @if ($page === '...')
                    <li class="hidden sm:block"><span class="flex h-8 min-w-[1.5rem] items-center justify-center text-sm text-slate-400" aria-hidden="true">&hellip;</span></li>
                @elseif ($page === $current)
                    <li class="hidden sm:block"><span class="{{ $box }} border-brand-800 bg-brand-800 text-white shadow-sm" aria-current="page" aria-label="Page {{ $page }}, current page">{{ $page }}</span></li>
                @else
                    <li class="hidden sm:block"><a href="{{ $paginator->url($page) }}" class="{{ $box }} {{ $live }}" aria-label="Go to page {{ $page }}">{{ $page }}</a></li>
                @endif
            @endforeach

            {{-- Next --}}
            <li>
                @if ($paginator->hasMorePages())
                    <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="{{ $box }} {{ $live }}" aria-label="Next page">{!! $right !!}</a>
                @else
                    <span class="{{ $box }} {{ $off }}" aria-disabled="true" aria-label="Next page">{!! $right !!}</span>
                @endif
            </li>
        </ul>
    </nav>
@endif
