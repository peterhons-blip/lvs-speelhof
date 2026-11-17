{{-- Navigatiepad: links zijn aanklikbaar, de huidige pagina niet. --}}
<nav class="portal-breadcrumbs" aria-label="Kruimelpad">
    <ol>
        @foreach($items as $item)
            <li>
                @if(!empty($item['url']))
                    <a href="{{ $item['url'] }}">{{ $item['label'] }}</a>
                @else
                    <span aria-current="page">{{ $item['label'] }}</span>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
