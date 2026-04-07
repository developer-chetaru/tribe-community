@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Pagination Navigation" class="flex justify-center mt-6">
        <ul class="flex items-center space-x-2">

            {{-- Previous Arrow --}}
            @if ($paginator->onFirstPage())
                <li>
                    <span class="px-3 py-2 text-gray-400 bg-gray-100 rounded-lg cursor-not-allowed">‹</span>
                </li>
            @else
                <li>
                    <button type="button" wire:click="previousPage('{{ $paginator->getPageName() }}')" wire:loading.attr="disabled" class="px-3 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg transition">‹</button>
                </li>
            @endif

            {{-- Page Numbers (Limited to 5 pages) --}}
            @php
                $currentPage = $paginator->currentPage();
                $lastPage = $paginator->lastPage();
                $maxPagesToShow = 5;
                
                // Calculate start and end page numbers
                if ($lastPage <= $maxPagesToShow) {
                    // Show all pages if total pages <= 5
                    $startPage = 1;
                    $endPage = $lastPage;
                } else {
                    // Calculate sliding window
                    $half = floor($maxPagesToShow / 2);
                    
                    if ($currentPage <= $half + 1) {
                        // Near the beginning
                        $startPage = 1;
                        $endPage = $maxPagesToShow;
                    } elseif ($currentPage >= $lastPage - $half) {
                        // Near the end
                        $startPage = $lastPage - $maxPagesToShow + 1;
                        $endPage = $lastPage;
                    } else {
                        // In the middle
                        $startPage = $currentPage - $half;
                        $endPage = $currentPage + $half;
                    }
                }
            @endphp

            {{-- Show first page and ellipsis if needed --}}
            @if ($startPage > 1)
                <li>
                    <button type="button" wire:click="gotoPage(1, '{{ $paginator->getPageName() }}')" wire:loading.attr="disabled" class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg transition">1</button>
                </li>
                @if ($startPage > 2)
                    <li><span class="px-3 py-2 text-gray-500">...</span></li>
                @endif
            @endif

            {{-- Show page numbers --}}
            @for ($page = $startPage; $page <= $endPage; $page++)
                @if ($page == $currentPage)
                    <li><span class="px-4 py-2 bg-red-500 text-white rounded-lg shadow font-medium">{{ $page }}</span></li>
                @else
                    <li>
                        <button type="button" wire:click="gotoPage({{ $page }}, '{{ $paginator->getPageName() }}')" wire:loading.attr="disabled" class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg transition">
                            {{ $page }}
                        </button>
                    </li>
                @endif
            @endfor

            {{-- Show last page and ellipsis if needed --}}
            @if ($endPage < $lastPage)
                @if ($endPage < $lastPage - 1)
                    <li><span class="px-3 py-2 text-gray-500">...</span></li>
                @endif
                <li>
                    <button type="button" wire:click="gotoPage({{ $lastPage }}, '{{ $paginator->getPageName() }}')" wire:loading.attr="disabled" class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg transition">{{ $lastPage }}</button>
                </li>
            @endif

            {{-- Next Arrow --}}
            @if ($paginator->hasMorePages())
                <li>
                    <button type="button" wire:click="nextPage('{{ $paginator->getPageName() }}')" wire:loading.attr="disabled" class="px-3 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg transition">›</button>
                </li>
            @else
                <li><span class="px-3 py-2 text-gray-400 bg-gray-100 rounded-lg cursor-not-allowed">›</span></li>
            @endif

        </ul>
    </nav>
@endif
