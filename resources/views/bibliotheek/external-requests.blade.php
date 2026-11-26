@extends('layouts.app')
@section('title','Bibliotheekaanvragen')
@section('content')
<main class="library-page">
<h1>Bibliotheekaanvragen</h1>
<p>Beoordeel reserveringen en verlengingsaanvragen. Goedkeuring reserveert een beschikbaar exemplaar gedurende vijf schooldagen of verlengt de uitleentermijn.</p>
@if(session('success'))<p role="status">{{ session('success') }}</p>@endif
@foreach($requests as $item)
<section class="library-form-card">
<h2>{{ $item->voornaam }} {{ $item->naam }} · {{ ['reserve'=>'Reservering','renew'=>'Verlenging','helper'=>'Leerlingmedewerker'][$item->request_type] ?? $item->request_type }}</h2>
<p>{{ $item->principal_type }} · {{ $item->created_at }} · {{ $item->status==='approved' && $item->reserved_until && $item->reserved_until < now('Europe/Brussels')->toDateString() ? 'Verlopen' : (['pending'=>'Open','approved'=>'Goedgekeurd','rejected'=>'Geweigerd','fulfilled'=>'Opgehaald','cancelled'=>'Geannuleerd'][$item->status] ?? $item->status) }}</p>
@if($item->target_id)<p>{{ $item->request_type==='reserve'?'Boek':'Uitlening' }} #{{ $item->target_id }}</p>@endif
@if($item->reserved_copy_id)<p>Gereserveerd exemplaar #{{ $item->reserved_copy_id }} · tot {{ $item->reserved_until }}</p>@endif
@if($item->payload) @php($payload=json_decode($item->payload,true)) <p>{{ $payload['action'] ?? '' }}: {{ $payload['details'] ?? '' }}</p>@endif
@if($item->status==='pending')
<form method="post" action="{{ route('bibliotheek.requests.decide',$item->id) }}">@csrf
<label>Toelichting <input name="note" maxlength="500"></label>
<button class="library-button library-button-primary" name="status" value="approved">Goedkeuren</button>
<button class="library-button library-button-secondary" name="status" value="rejected">Weigeren</button></form>
@else <p>{{ $item->decision_note }}</p>@endif
</section>
@endforeach
@include('partials.portal-pagination',['paginator'=>$requests])
</main>
@endsection
