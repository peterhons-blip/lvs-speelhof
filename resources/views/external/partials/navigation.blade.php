@php
    $externalAccess=app(\App\Services\ExternalPortalAccess::class);
    $externalRole=session('ss_user.principal_type');
    $externalId=(int)session('ss_user.leerling_id');
    $externalCan=fn($permission)=>$externalAccess->has($externalRole,$permission,$externalId);
    $section=request('section','dashboard');
    $libraryItems=array_filter([
        'loans'=>$externalCan('library.view')?'Uitleningen':null,
        'history'=>$externalCan('library.history.view')?'Historiek':null,
        'catalog'=>$externalCan('library.catalog.view')?'Catalogus':null,
        'requests'=>($externalCan('library.reserve')||$externalCan('library.renew.request'))?'Aanvragen':null,
    ]);
    asort($libraryItems, SORT_NATURAL | SORT_FLAG_CASE);
    $personalActive=request()->routeIs('external.dashboard');
@endphp
@if($libraryItems)
<div class="portal-nav-module">
    <a class="portal-nav-link {{ $personalActive && in_array($section,array_merge(['library','book'],array_keys($libraryItems)))?'is-current':'' }}" href="{{ route('external.dashboard',['section'=>'library']) }}"><span class="portal-nav-icon" aria-hidden="true">▤</span> Bibliotheek</a>
    @if($personalActive && in_array($section,array_merge(['library'],array_keys($libraryItems))))
    <nav class="portal-nav-submenu" aria-label="Mijn bibliotheek">
        <a class="portal-nav-subitem {{ $section==='library'?'is-active':'' }}" href="{{ route('external.dashboard',['section'=>'library']) }}">Overzicht</a>
        @foreach($libraryItems as $key=>$label)
        <a class="portal-nav-subitem {{ ($section===$key || ($key==='catalog' && $section==='book'))?'is-active':'' }}" href="{{ route('external.dashboard',['section'=>$key]) }}">{{ $label }}</a>
        @endforeach
    </nav>
    @endif
</div>
@endif
