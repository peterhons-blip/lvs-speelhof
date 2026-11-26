@extends('layouts.app')
@section('title',$titles[$section])
@section('breadcrumbs')
@include('partials.breadcrumbs',['items'=>[['label'=>'Dashboard','url'=>route('home')],['label'=>$titles[$section]]]])
@endsection
@section('content')
<style>
.external-card-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(225px,1fr));gap:18px;margin:20px 0}
.external-card{display:flex;flex-direction:column;gap:8px;min-height:165px;background:#fff;border:1px solid #99d5de;border-radius:15px;padding:22px;text-decoration:none;color:inherit}
.external-card:hover{border-color:#008599;box-shadow:0 4px 15px #12334a12}
.external-card h2{font-size:17px;margin:0;color:#12365b}
.external-card p{margin:0;color:#526b83;font-size:14px}
.external-card-icon{display:grid;place-items:center;width:42px;height:42px;background:#e2f4f5;border-radius:12px;color:#007e90;font-size:22px}
.external-number{font-size:30px;color:#12365b}
</style>
<main class="library-page">
<div class="library-page-head"><div><p class="portal-eyebrow">{{ $role==='staff'?'MIJN BIBLIOTHEEK':'MIJN PORTAAL' }}</p><h1>{{ $titles[$section] }}</h1>
@if($role==='coaccount')
<p>Portaal van {{ $student->voornaam }} {{ $student->naam }}</p>
@endif
</div></div>

@if(in_array($section,['dashboard','library']))
<div class="external-card-grid">
@if($section==='dashboard' && $can('profile.view'))
<a class="external-card" href="{{ route($portalRoute,['section'=>'profile']) }}"><span class="external-card-icon">◎</span><h2>{{ $role==='leerling'?'Mijn gegevens':'Leerlinggegevens' }}</h2><p>{{ $profile ?? 'Bekijk je profiel' }}</p></a>
@endif
@if($can('library.view'))
<a class="external-card" href="{{ route($portalRoute,['section'=>'loans']) }}"><span class="external-card-icon">▤</span><strong class="external-number">{{ $loans->count() }}</strong><h2>Openstaande uitleningen</h2><p>{{ $loans->filter(fn($loan)=>$loan->due_date < now('Europe/Brussels')->toDateString())->count() }} te laat</p></a>
@endif
@if($can('library.catalog.view'))
<a class="external-card" href="{{ route($portalRoute,['section'=>'catalog']) }}"><span class="external-card-icon">▥</span><h2>Boekencatalogus</h2><p>Zoek boeken op titel, auteur of ISBN.</p></a>
@endif
@if($can('library.history.view'))
<a class="external-card" href="{{ route($portalRoute,['section'=>'history']) }}"><span class="external-card-icon">↶</span><h2>Uitleenhistoriek</h2><p>Bekijk eerder ingeleverde boeken.</p></a>
@endif
@if($can('library.reserve') || $can('library.renew.request'))
<a class="external-card" href="{{ route($portalRoute,['section'=>'requests']) }}"><span class="external-card-icon">☷</span><strong class="external-number">{{ $requests->where('status','pending')->count() }}</strong><h2>Openstaande aanvragen</h2><p>Reserveringen en verlengingen.</p></a>
@endif
@if($section==='dashboard' && $can('lvs.view') && $can('incidents.view'))
<a class="external-card" href="{{ route($portalRoute,['section'=>'incidents']) }}"><span class="external-card-icon">◎</span><h2>LVS-meldingen</h2><p>Bekijk vrijgegeven meldingen.</p></a>
@endif
@if(app(\App\Services\LibraryAccess::class)->has(request(),'bibliotheek.view'))
<a class="external-card" href="{{ route('bibliotheek.index') }}"><span class="external-card-icon">▦</span><h2>Bibliotheekbeheer</h2><p>Open de werkruimte voor bibliotheekmedewerkers.</p></a>
@endif
</div>
@endif

@if($section==='profile' && $can('profile.view'))
<section class="library-form-card"><h2>Leerlinggegevens</h2><dl class="lvs-details-grid"><div><dt>Naam</dt><dd>{{ $student->voornaam }} {{ $student->naam }}</dd></div><div><dt>Klas</dt><dd>{{ $profile ?? '—' }}</dd></div></dl></section>
@endif
@if(session('success'))<div class="library-message" role="status">{{ session('success') }}</div>
@endif
@if($errors->any())<div class="library-errors" role="alert">{{ $errors->first() }}</div>
@endif
@if($section==='incidents' && $incidents!==null)
<section class="library-form-card"><h2>LVS-meldingen</h2>
@forelse($incidents as $incident)<article><strong>{{ $incident->soort }} · {{ $incident->gebeurdop }}</strong><p>{{ $incident->comment }}</p></article>
@empty
<p>Geen vrijgegeven meldingen.</p>
@endforelse
@include('partials.portal-pagination',['paginator'=>$incidents])
</section>
@endif
@if($section==='loans' && $loans!==null)
<section class="library-form-card"><h2>Actuele uitleningen</h2><div class="library-table-wrap"><table class="library-table"><thead><tr><th>Boek</th><th>Inleverdatum</th>
@if($can('library.renew.request'))<th>Actie</th>
@endif
</tr></thead><tbody>
@forelse($loans as $loan)<tr><td>{{ $loan->title }}</td><td>{{ \Illuminate\Support\Carbon::parse($loan->due_date)->format('d/m/Y') }} @if($loan->due_date < now('Europe/Brussels')->toDateString())<strong> · Te laat</strong>
@endif
</td>
@if($can('library.renew.request'))<td><form method="post" action="{{ route($submitRoute) }}">
@csrf
<input type="hidden" name="type" value="renew"><input type="hidden" name="target_id" value="{{ $loan->id }}"><button class="library-button library-button-secondary" >{{ session('portal_preview') ? 'Verlenging testen' : 'Verlenging aanvragen' }}</button></form></td>
@endif
</tr>
@empty
<tr><td colspan="2">Geen openstaande uitleningen.</td></tr>
@endforelse
</tbody></table></div></section>
@endif
@if($section==='history' && $history!==null)
<section class="library-form-card"><h2>Uitleenhistoriek</h2><div class="library-table-wrap"><table class="library-table"><thead><tr><th>Boek</th><th>Ingeleverd</th></tr></thead><tbody>
@forelse($history as $loan)<tr><td>{{ $loan->title }}</td><td>{{ \Illuminate\Support\Carbon::parse($loan->returned_at)->format('d/m/Y') }}</td></tr>
@empty
<tr><td colspan="2">Geen eerdere uitleningen.</td></tr>
@endforelse
</tbody></table></div>
@include('partials.portal-pagination',['paginator'=>$history])</section>
@endif
@if($section==='catalog' && $books!==null)
<section class="library-form-card"><h2>Bibliotheekcatalogus</h2><form method="get" action="{{ route($portalRoute) }}" class="library-search" style="display:flex;gap:12px;align-items:center;flex-wrap:wrap"><input type="hidden" name="section" value="catalog"><input style="flex:1" type="search" name="q" value="{{ request('q') }}" placeholder="Titel, auteur of ISBN" maxlength="100"><button class="library-button library-button-primary" type="submit">Zoeken</button></form>
<div class="library-table-wrap"><table class="library-table"><thead><tr><th>Titel</th><th>Auteur</th>
@if($can('library.reserve'))<th>Actie</th>
@endif
@if($can('library.availability.view'))<th>Beschikbaar</th>
@endif
</tr></thead><tbody>
@forelse($books as $book)<tr><td>{{ $book->title }}</td><td>{{ $book->authors }}</td>
@if($can('library.reserve'))<td><form method="post" action="{{ route($submitRoute) }}">
@csrf
<input type="hidden" name="type" value="reserve"><input type="hidden" name="target_id" value="{{ $book->id }}"><button class="library-button library-button-secondary" >{{ session('portal_preview') ? 'Reserveren (test)' : 'Reserveren' }}</button></form></td>
@endif
@if($can('library.availability.view'))<td>{{ $book->available }}</td>
@endif
</tr>
@empty
<tr><td colspan="{{ 2 + (int)$can('library.availability.view') + (int)$can('library.reserve') }}">Geen boeken gevonden.</td></tr>
@endforelse
</tbody></table></div>
@include('partials.portal-pagination',['paginator'=>$books])</section>
@endif
@if($section==='requests')
<section class="library-form-card"><h2>Mijn aanvragen</h2>
<div class="library-table-wrap"><table class="library-table"><thead><tr><th>Boek</th><th>Type</th><th>Datum</th><th>Status</th><th>Toelichting</th></tr></thead><tbody>
@forelse($requests as $item)
<tr><td><strong>{{ ($item->request_type==='reserve'?$item->requested_book:$item->renew_book) ?? 'Boek niet beschikbaar' }}</strong></td>
<td>{{ $item->request_type==='reserve'?'Reservering':'Verlenging' }}</td>
<td>{{ \Illuminate\Support\Carbon::parse($item->created_at)->format('d/m/Y') }}</td>
<td><span class="library-pill">{{ $item->status==='approved' && $item->reserved_until && $item->reserved_until < now('Europe/Brussels')->toDateString() ? 'Verlopen' : (['pending'=>'Open','approved'=>'Goedgekeurd','rejected'=>'Geweigerd','fulfilled'=>'Opgehaald','cancelled'=>'Geannuleerd'][$item->status] ?? '') }}</span>
@if($item->reserved_until && $item->status==='approved')
<div>Ophalen uiterlijk {{ \Illuminate\Support\Carbon::parse($item->reserved_until)->format('d/m/Y') }}</div>
@endif
</td><td>{{ $item->decision_note ?? '—' }}</td></tr>
@empty
<tr><td colspan="5">Je hebt nog geen aanvragen.</td></tr>
@endforelse
</tbody></table></div></section>
@endif
</main>
@endsection
