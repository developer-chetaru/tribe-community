@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Pagination Navigation" class="flex justify-center mt-6">
        <ul class="flex items-center space-x-2">

            {{-- Previous --}}
            @if ($paginator->onFirstPage())
                <li>
                    <span class="px-3 py-2 text-gray-400 bg-gray-100 rounded-lg cursor-not-allowed">‹</span>
                </li>
            @else
                <li>
                    <button type="button" wire:click="previousPage('{{ $paginator->getPageName() }}')" wire:loading.attr="disabled" class="px-3 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg transition">‹</button>
                </li>
            @endif

            {{-- Numbers --}}
            @foreach ($elements as $element)
                @if (is_string($element))
                    <li><span class="px-3 py-2 text-gray-500">{{ $element }}</span></li>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <li><span class="px-4 py-2 bg-red-500 text-white rounded-lg shadow font-medium">{{ $page }}</span></li>
                        @else
                            <li>
                                <button type="button" wire:click="gotoPage({{ $page }}, '{{ $paginator->getPageName() }}')" wire:loading.attr="disabled" class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg transition">
                                    {{ $page }}
                                </button>
                            </li>
                        @endif
                    @endforeach
                @endif
            @endforeach

            {{-- Next --}}
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
