<nav class="portal-users-pagination" aria-label="Paginanavigatie">
            <span class="portal-pagination-summary">
                {{ $label ?? 'Resultaten' }} {{ $paginator->firstItem() ?? 0 }}–{{ $paginator->lastItem() ?? 0 }} van {{ $paginator->total() }}
            </span>
            @if($paginator->hasPages())
                <div class="portal-pagination-controls">
                    @if($paginator->onFirstPage())
                        <span class="portal-page-link is-disabled" aria-disabled="true">‹ Vorige</span>
                    @else
                        <a class="portal-page-link" href="{{ $paginator->previousPageUrl() }}" rel="prev">‹ Vorige</a>
                    @endif
                    @foreach($paginator->getUrlRange(max(1, $paginator->currentPage() - 2), min($paginator->lastPage(), $paginator->currentPage() + 2)) as $page => $url)
                        @if($page === $paginator->currentPage())
                            <span class="portal-page-link is-current" aria-current="page">{{ $page }}</span>
                        @else
                            <a class="portal-page-link" href="{{ $url }}" aria-label="Pagina {{ $page }}">{{ $page }}</a>
                        @endif
                    @endforeach
                    @if($paginator->hasMorePages())
                        <a class="portal-page-link" href="{{ $paginator->nextPageUrl() }}" rel="next">Volgende ›</a>
                    @else
                        <span class="portal-page-link is-disabled" aria-disabled="true">Volgende ›</span>
                    @endif
                </div>
            @endif
        </nav>
