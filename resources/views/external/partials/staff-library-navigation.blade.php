@php
    $personalAccess=app(\App\Services\LibraryAccess::class);
    $personalSection=request('section','library');
    $personalActive=request()->routeIs('personal-library.*');
    $personalItems=array_filter([
        'loans'=>$personalAccess->personalLibraryCan(request(),'library.view')?'Mijn uitleningen':null,
        'history'=>$personalAccess->personalLibraryCan(request(),'library.history.view')?'Historiek':null,
        'catalog'=>$personalAccess->personalLibraryCan(request(),'library.catalog.view')?'Catalogus':null,
        'reservations'=>$personalAccess->personalLibraryCan(request(),'library.reserve')?'Reserveringen':null,
        'renewals'=>$personalAccess->personalLibraryCan(request(),'library.renew.request')?'Verlengingsaanvragen':null,
    ]);
    asort($personalItems, SORT_NATURAL | SORT_FLAG_CASE);
@endphp
<div class="portal-nav-module">
    <a class="portal-nav-link {{ $personalActive?'is-current':'' }}" href="{{ route('personal-library.index') }}"><span class="portal-nav-icon" aria-hidden="true">▤</span> Bibliotheek</a>
    @if($personalActive)
    <nav class="portal-nav-submenu" aria-label="Mijn bibliotheek">
        <a class="portal-nav-subitem {{ $personalSection==='library'?'is-active':'' }}" href="{{ route('personal-library.index') }}">Overzicht</a>
        @foreach($personalItems as $key=>$label)
        <a class="portal-nav-subitem {{ ($personalSection===$key || ($key==='catalog' && $personalSection==='book'))?'is-active':'' }}" href="{{ route('personal-library.index',['section'=>$key]) }}">{{ $label }}</a>
        @endforeach
    </nav>
    @endif
</div>
