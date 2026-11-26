<!doctype html>
<html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Mijn Atheneum Portaal</title><link rel="icon" href="{{ asset('atheneum-portaal-favicon.svg') }}">
<link rel="stylesheet" href="{{ asset('css/lvs.css') }}"><link rel="stylesheet" href="{{ asset('css/portal.css') }}?v=41">
</head><body class="portal-body">
<header class="portal-topbar"><a class="portal-brand" href="{{ route('external.dashboard') }}"><img class="portal-brand-logo" src="{{ asset('images/logo.jpg') }}" alt="Atheneum Sint-Truiden"><span class="portal-brand-title">Atheneum Portaal</span></a>
<div class="portal-account"><span class="portal-account-copy"><strong>{{ session('ss_user.voornaam') }} {{ session('ss_user.naam') }}</strong><small>{{ $role==='leerling'?'Leerling':'Co-account' }}</small></span><a class="portal-logout" href="{{ route('logout') }}">Uitloggen</a></div></header>
@if(session('portal_preview'))<div style="padding:12px;background:#fff1d5;color:#604000"><strong>Testmodus · {{ $role }} · {{ $student->voornaam }} {{ $student->naam }}</strong><form method="post" action="{{ route('portal-preview.stop') }}">@csrf<button class="library-button library-button-secondary">Terug naar ICT</button></form></div>@endif
<main class="library-page" style="max-width:1100px;margin:24px auto;padding:0 16px">
<div class="library-page-head"><div><p class="portal-eyebrow">MIJN PORTAAL</p><h1>Welkom, {{ session('ss_user.voornaam') }}!</h1>@if($role==='coaccount')<p>Portaal van {{ $student->voornaam }} {{ $student->naam }}</p>@endif</div></div>
@if($can('profile.view'))
<section class="library-form-card"><h2>Leerlinggegevens</h2><dl class="lvs-details-grid"><div><dt>Naam</dt><dd>{{ $student->voornaam }} {{ $student->naam }}</dd></div><div><dt>Klas</dt><dd>{{ $profile ?? '—' }}</dd></div></dl></section>
@endif
@if(session('success'))<p role="status">{{ session('success') }}</p>@endif
@if($errors->any())<div role="alert">{{ $errors->first() }}</div>@endif
@if($incidents!==null)
<section class="library-form-card"><h2>LVS-meldingen</h2>
@forelse($incidents as $incident)<article><strong>{{ $incident->soort }} · {{ $incident->gebeurdop }}</strong><p>{{ $incident->comment }}</p></article>@empty<p>Geen vrijgegeven meldingen.</p>@endforelse
@include('partials.portal-pagination',['paginator'=>$incidents])
</section>
@endif
@if($loans!==null)
<section class="library-form-card"><h2>Actuele uitleningen</h2><div class="library-table-wrap"><table class="library-table"><thead><tr><th>Boek</th><th>Inleverdatum</th>@if($can('library.renew.request'))<th>Actie</th>@endif</tr></thead><tbody>@forelse($loans as $loan)<tr><td>{{ $loan->title }}</td><td>{{ \Illuminate\Support\Carbon::parse($loan->due_date)->format('d/m/Y') }} @if($loan->due_date < now('Europe/Brussels')->toDateString())<strong> · Te laat</strong>@endif</td>@if($can('library.renew.request'))<td><form method="post" action="{{ route('external.requests.submit') }}">@csrf<input type="hidden" name="type" value="renew"><input type="hidden" name="target_id" value="{{ $loan->id }}"><button class="library-button library-button-secondary" @disabled(session('portal_preview'))>Verlenging aanvragen</button></form></td>@endif</tr>@empty<tr><td colspan="2">Geen openstaande uitleningen.</td></tr>@endforelse</tbody></table></div></section>
@endif
@if($history!==null)
<section class="library-form-card"><h2>Uitleenhistoriek</h2><div class="library-table-wrap"><table class="library-table"><thead><tr><th>Boek</th><th>Ingeleverd</th></tr></thead><tbody>@forelse($history as $loan)<tr><td>{{ $loan->title }}</td><td>{{ \Illuminate\Support\Carbon::parse($loan->returned_at)->format('d/m/Y') }}</td></tr>@empty<tr><td colspan="2">Geen eerdere uitleningen.</td></tr>@endforelse</tbody></table></div>@include('partials.portal-pagination',['paginator'=>$history])</section>
@endif
@if($books!==null)
<section class="library-form-card"><h2>Bibliotheekcatalogus</h2><form method="get" action="{{ route('external.dashboard') }}" class="library-search"><input type="search" name="q" value="{{ request('q') }}" placeholder="Titel, auteur of ISBN" maxlength="100"><button class="library-button library-button-primary" type="submit">Zoeken</button></form>
<div class="library-table-wrap"><table class="library-table"><thead><tr><th>Titel</th><th>Auteur</th>@if($can('library.reserve'))<th>Actie</th>@endif@if($can('library.availability.view'))<th>Beschikbaar</th>@endif</tr></thead><tbody>@forelse($books as $book)<tr><td>{{ $book->title }}</td><td>{{ $book->authors }}</td>@if($can('library.reserve'))<td><form method="post" action="{{ route('external.requests.submit') }}">@csrf<input type="hidden" name="type" value="reserve"><input type="hidden" name="target_id" value="{{ $book->id }}"><button class="library-button library-button-secondary" @disabled(session('portal_preview'))>Reserveren</button></form></td>@endif@if($can('library.availability.view'))<td>{{ $book->available }}</td>@endif</tr>@empty<tr><td colspan="{{ $can('library.availability.view') ? 3 : 2 }}">Geen boeken gevonden.</td></tr>@endforelse</tbody></table></div>@include('partials.portal-pagination',['paginator'=>$books])</section>
@endif
@if(!$can('profile.view') && $books===null && $loans===null && $history===null)<section class="library-form-card"><p>Er zijn nog geen functies voor je account geactiveerd.</p></section>@endif
@if(app(\App\Services\LibraryAccess::class)->has(request(),'bibliotheek.view'))
<section class="library-form-card"><h2>Bibliotheekmedewerker</h2><p>Open de bibliotheekmodule om boeken en uitleningen rechtstreeks te beheren.</p><a class="library-button library-button-primary" href="{{ route('bibliotheek.index') }}">Bibliotheekbeheer openen</a></section>
@endif
@if($requests->isNotEmpty())<section class="library-form-card"><h2>Mijn aanvragen</h2>@foreach($requests as $item)<p>{{ ['reserve'=>'Reservering','renew'=>'Verlenging','helper'=>'Bibliotheektaak'][$item->request_type] ?? '' }} · {{ $item->created_at }} · {{ ['pending'=>'Open','approved'=>'Goedgekeurd','rejected'=>'Geweigerd','fulfilled'=>'Opgehaald'][$item->status] ?? '' }} @if($item->reserved_until) · Ophalen uiterlijk {{ $item->reserved_until }} @endif @if($item->decision_note) · {{ $item->decision_note }} @endif</p>@endforeach</section>@endif
</main></body></html>
