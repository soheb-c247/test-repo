@if ($paginator->hasPages() || $paginator->total() > 0)
    <nav role="navigation" aria-label="Pagination" class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 px-4 py-3 text-sm">
        <p class="text-slate-500">
            Showing <span class="font-semibold text-slate-700">{{ $paginator->firstItem() ?? 0 }}</span>–<span class="font-semibold text-slate-700">{{ $paginator->lastItem() ?? 0 }}</span>
            of <span class="font-semibold text-slate-700">{{ number_format($paginator->total()) }}</span>
        </p>

        @if ($paginator->hasPages())
            <ul class="flex items-center gap-1">
                <li>
                    @if ($paginator->onFirstPage())
                        <span class="icon-btn cursor-not-allowed opacity-40" aria-disabled="true" aria-label="Previous page"><x-icon name="chevron-left" /></span>
                    @else
                        <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="icon-btn" aria-label="Previous page"><x-icon name="chevron-left" /></a>
                    @endif
                </li>

                @foreach ($elements as $element)
                    @if (is_string($element))
                        <li><span class="px-2 text-slate-400">…</span></li>
                    @endif
                    @if (is_array($element))
                        @foreach ($element as $page => $url)
                            <li class="hidden sm:block">
                                @if ($page == $paginator->currentPage())
                                    <span aria-current="page" class="inline-flex h-8 min-w-8 items-center justify-center rounded-lg bg-navy-900 px-2 text-xs font-semibold text-white">{{ $page }}</span>
                                @else
                                    <a href="{{ $url }}" class="inline-flex h-8 min-w-8 items-center justify-center rounded-lg px-2 text-xs font-semibold text-slate-600 hover:bg-slate-100" aria-label="Page {{ $page }}">{{ $page }}</a>
                                @endif
                            </li>
                        @endforeach
                    @endif
                @endforeach

                <li class="sm:hidden px-2 text-xs text-slate-500">Page {{ $paginator->currentPage() }} / {{ $paginator->lastPage() }}</li>

                <li>
                    @if ($paginator->hasMorePages())
                        <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="icon-btn" aria-label="Next page"><x-icon name="chevron-right" /></a>
                    @else
                        <span class="icon-btn cursor-not-allowed opacity-40" aria-disabled="true" aria-label="Next page"><x-icon name="chevron-right" /></span>
                    @endif
                </li>
            </ul>
        @endif
    </nav>
@endif
