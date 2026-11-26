@extends('layouts.app')
@section('title','Bibliotheekaanvragen')
@section('breadcrumbs')
@include('partials.breadcrumbs',['items'=>[['label'=>'Dashboard','url'=>route('home')],['label'=>'Bibliotheekbeheer','url'=>route('bibliotheek.index')],['label'=>'Aanvragen']]])
@endsection
@section('content')
<style>
.request-tabs{display:flex;gap:10px;flex-wrap:wrap;margin:0 0 20px}
.request-filters{display:flex;gap:12px;align-items:end;flex-wrap:wrap;margin:0 0 20px}
.request-filters .library-field{margin:0;flex:1 1 160px}
.request-grid{display:grid;gap:12px}
.request-card{border:1px solid #dce5ec;border-radius:14px;background:#fff;padding:20px;display:grid;grid-template-columns:minmax(0,1fr) minmax(250px,1fr);gap:20px}
.request-card h2{font-size:18px;line-height:1.4;margin:8px 0;color:#12365b}
.request-card p{margin:6px 0;line-height:1.5;color:#526b83}
.request-status{display:inline-flex;border-radius:6px;padding:5px 9px;font-size:12px;font-weight:700;background:#edf2f7;color:#526b83}
.request-status.ready{background:#e5f4ef;color:#21694e}
.request-status.wait{background:#fff3d8;color:#805800}
.request-action{border-left:1px solid #e2e9ef;padding-left:20px;align-self:center}
.request-action form{margin:0}
.request-action .library-field{margin:0 0 10px}
.request-buttons{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
.request-card .request-meta{font-size:13px}
@media(max-width:900px){.request-card{grid-template-columns:1fr}.request-action{border-left:0;border-top:1px solid #e2e9ef;padding:14px 0 0}}
</style>
<main class="library-page">
<div class="library-page-head"><div><p class="portal-eyebrow">BIBLIOTHEEKBEHEER</p><h1>Bibliotheekaanvragen</h1><p>Beheer reserveringen, wachtlijsten en verlengingen.</p></div></div>
@if(session('success'))
<div class="library-message" role="status">{{ session('success') }}</div>
@endif
@if($errors->any())
<div class="library-errors" role="alert">{{ $errors->first() }}</div>
@endif
<nav class="request-tabs" aria-label="Aanvraagstatus">
@foreach(['pending'=>'Te behandelen ('.$counts['pending'].')','pickup'=>'Af te halen ('.$counts['pickup'].')','history'=>'Historiek','all'=>'Alle ('.$counts['all'].')'] as $key=>$label)
<a class="library-button {{ $status===$key?'library-button-primary':'library-button-secondary' }}" href="{{ route('bibliotheek.requests.index',array_merge(request()->only(['q','type','principal']),['status'=>$key])) }}">{{ $label }}</a>
@endforeach
</nav>
<section class="library-form-card">
<form class="request-filters" method="get" action="{{ route('bibliotheek.requests.index') }}">
<input type="hidden" name="status" value="{{ $status }}">
<div class="library-field"><label for="request-q">Zoeken</label><input id="request-q" name="q" type="search" value="{{ $search }}" placeholder="Naam of boektitel"></div>
<div class="library-field"><label for="request-type">Type aanvraag</label><select id="request-type" name="type"><option value="">Alle types</option><option value="reserve" @selected($type==='reserve')>Reservering</option><option value="renew" @selected($type==='renew')>Verlenging</option></select></div>
<div class="library-field"><label for="request-principal">Aanvrager</label><select id="request-principal" name="principal"><option value="">Iedereen</option>
@foreach(['leerling'=>'Leerling','staff'=>'Personeel','coaccount'=>'Co-account'] as $key=>$label)
<option value="{{ $key }}" @selected($principal===$key)>{{ $label }}</option>
@endforeach
</select></div>
<button class="library-button library-button-primary" type="submit">Filteren</button>
<a class="library-button library-button-secondary" href="{{ route('bibliotheek.requests.index',['status'=>$status]) }}">Wissen</a>
</form>
<div class="request-grid">
@forelse($requests as $item)
@php
    $reserve=$item->request_type==='reserve';
    $pending=$item->status==='pending';
    $pickup=$reserve && $item->status==='approved' && $item->reserved_until >= $today;
    $expired=$reserve && $item->status==='approved' && $item->reserved_until < $today;
    $waiting=$pending && $reserve && ($item->available<1 || $item->ahead>0);
    $label=$waiting?'Wachtlijst':($expired?'Verlopen':($pickup?'Af te halen':(['pending'=>'Te behandelen','approved'=>'Goedgekeurd','rejected'=>'Geweigerd','fulfilled'=>'Opgehaald','cancelled'=>'Geannuleerd'][$item->status]??$item->status)));
@endphp
<article class="request-card">
<div>
<span class="request-status {{ $waiting?'wait':($pickup||($pending&&!$waiting)?'ready':'') }}">{{ $label }}</span>
<span class="request-meta"> · {{ $reserve?'Reservering':'Verlenging' }}</span>
<h2>{{ ($reserve?$item->requested_book:$item->renew_book) ?? 'Boek niet beschikbaar' }}</h2>
<p><strong>{{ $item->voornaam }} {{ $item->naam }}</strong> · {{ ['staff'=>'Personeel','leerling'=>'Leerling','coaccount'=>'Co-account'][$item->principal_type]??$item->principal_type }}</p>
<p class="request-meta">Aangevraagd op {{ \Illuminate\Support\Carbon::parse($item->created_at)->format('d/m/Y H:i') }}</p>
@if($pending && $reserve)
<p>{{ $item->available }} beschikbaar · {{ $item->ahead }} eerdere aanvragen</p>
@endif
@if($pickup)
<p><strong>Ophalen uiterlijk {{ \Illuminate\Support\Carbon::parse($item->reserved_until)->format('d/m/Y') }}</strong></p>
<p>Exemplaar: {{ $item->reserved_barcode ?? $item->reserved_copy_id }}</p>
@endif
</div>
<div class="request-action">
@if($pending)
    @if($waiting)
    <p>De aanvraag blijft open tot er een vrij exemplaar is en eerdere aanvragen behandeld zijn.</p>
    @endif
    <form method="post" action="{{ route('bibliotheek.requests.decide',$item->id) }}">
        @csrf
        <div class="library-field"><label for="note-{{ $item->id }}">Toelichting (optioneel)</label><input id="note-{{ $item->id }}" name="note" maxlength="500" placeholder="Bericht bij de beslissing"></div>
        <div class="request-buttons">
        @unless($waiting)
        <button class="library-button library-button-primary" type="submit" name="status" value="approved">{{ $reserve?'Reservering bevestigen':'Verlenging goedkeuren' }}</button>
        @endunless
        <button class="library-button library-button-secondary" type="submit" name="status" value="rejected">Weigeren</button>
        </div>
    </form>
@else
    <p><strong>Beslissing</strong></p>
    <p>{{ $item->decision_note ?: 'Geen toelichting.' }}</p>
    @if($item->decided_by)
    <p class="request-meta">Verwerkt door {{ $item->decided_by }}@if($item->decided_at) · {{ \Illuminate\Support\Carbon::parse($item->decided_at)->format('d/m/Y H:i') }} @endif</p>
    @endif
    @if($pickup)
    <form method="post" action="{{ route('bibliotheek.requests.cancel',$item->id) }}" onsubmit="return confirm('Deze reservering annuleren en het exemplaar vrijgeven?')">
    @csrf
    <button class="library-button library-button-secondary" type="submit">Reservering annuleren</button>
    </form>
    @endif
@endif
</div>
</article>
@empty
<p>Geen aanvragen gevonden in dit overzicht.</p>
@endforelse
</div>
@include('partials.portal-pagination',['paginator'=>$requests])
</section>
</main>
@endsection
