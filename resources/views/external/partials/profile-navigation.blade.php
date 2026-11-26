@php
    $externalAccess=app(\App\Services\ExternalPortalAccess::class);
    $externalRole=session('ss_user.principal_type');
    $externalId=(int)session('ss_user.leerling_id');
    $externalCan=fn($permission)=>$externalAccess->has($externalRole,$permission,$externalId);
    $section=request('section','dashboard');
    $personalActive=request()->routeIs('external.dashboard');
@endphp
@if($externalCan('lvs.view') && $externalCan('incidents.view'))
<a class="portal-nav-link {{ $personalActive && $section==='incidents'?'is-current':'' }}" href="{{ route('external.dashboard',['section'=>'incidents']) }}"><span class="portal-nav-icon" aria-hidden="true">◎</span> LVS-meldingen</a>
@endif
@if($externalCan('profile.view'))
<a class="portal-nav-link {{ $personalActive && $section==='profile'?'is-current':'' }}" href="{{ route('external.dashboard',['section'=>'profile']) }}"><span class="portal-nav-icon" aria-hidden="true">◎</span>{{ $externalRole==='leerling'?'Mijn gegevens':'Leerlinggegevens' }}</a>
@endif
