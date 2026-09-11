@if ($paginator->hasPages())
    <nav class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between" role="navigation" aria-label="Pagination Navigation">
        <p class="m-0 text-xs font-semibold text-slate-500 sm:text-sm">
            Showing <span class="font-extrabold text-slate-800">{{ $paginator->firstItem() ?? 0 }}</span>
            to <span class="font-extrabold text-slate-800">{{ $paginator->lastItem() ?? 0 }}</span>
            of <span class="font-extrabold text-slate-800">{{ $paginator->total() }}</span> results
        </p>

        <div class="flex items-center justify-between gap-2 sm:justify-end">
            @if ($paginator->onFirstPage())
                <span class="inline-flex h-10 items-center gap-1.5 rounded-xl border border-slate-200 bg-slate-50 px-3 text-xs font-bold text-slate-400" aria-disabled="true">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
                    <span class="hidden sm:inline">Previous</span>
                </span>
            @else
                <a class="inline-flex h-10 items-center gap-1.5 rounded-xl border border-slate-300 bg-white px-3 text-xs font-extrabold text-slate-700 no-underline shadow-sm transition hover:border-villa-500 hover:bg-villa-50 hover:text-villa-800 focus:outline-none focus:ring-4 focus:ring-villa-100" href="{{ $paginator->previousPageUrl() }}" rel="prev">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
                    <span class="hidden sm:inline">Previous</span>
                </a>
            @endif

            <div class="hidden items-center gap-1 sm:flex">
                @foreach ($elements as $element)
                    @if (is_string($element))
                        <span class="grid h-10 min-w-8 place-items-center px-1 text-sm font-bold text-slate-400">{{ $element }}</span>
                    @endif

                    @if (is_array($element))
                        @foreach ($element as $page => $url)
                            @if ($page == $paginator->currentPage())
                                <span class="grid h-10 min-w-10 place-items-center rounded-xl bg-villa-700 px-3 text-sm font-extrabold text-white shadow-sm" aria-current="page">{{ $page }}</span>
                            @else
                                <a class="grid h-10 min-w-10 place-items-center rounded-xl border border-transparent px-3 text-sm font-bold text-slate-600 no-underline transition hover:border-slate-200 hover:bg-white hover:text-villa-800 focus:outline-none focus:ring-4 focus:ring-villa-100" href="{{ $url }}" aria-label="Go to page {{ $page }}">{{ $page }}</a>
                            @endif
                        @endforeach
                    @endif
                @endforeach
            </div>

            <span class="inline-flex h-10 min-w-10 items-center justify-center rounded-xl bg-villa-50 px-3 text-sm font-extrabold text-villa-800 sm:hidden" aria-current="page">{{ $paginator->currentPage() }} / {{ $paginator->lastPage() }}</span>

            @if ($paginator->hasMorePages())
                <a class="inline-flex h-10 items-center gap-1.5 rounded-xl border border-slate-300 bg-white px-3 text-xs font-extrabold text-slate-700 no-underline shadow-sm transition hover:border-villa-500 hover:bg-villa-50 hover:text-villa-800 focus:outline-none focus:ring-4 focus:ring-villa-100" href="{{ $paginator->nextPageUrl() }}" rel="next">
                    <span class="hidden sm:inline">Next</span>
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>
                </a>
            @else
                <span class="inline-flex h-10 items-center gap-1.5 rounded-xl border border-slate-200 bg-slate-50 px-3 text-xs font-bold text-slate-400" aria-disabled="true">
                    <span class="hidden sm:inline">Next</span>
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>
                </span>
            @endif
        </div>
    </nav>
@endif
