@extends('layouts.app')
@section('title','Schoolkalender')
@section('content')
<main class="library-page">
<div class="library-page-head"><div><p class="portal-eyebrow">BEHEER</p><h1>Schoolkalender</h1><p>Weekends worden automatisch uitgesloten. Voeg extra studiedagen en sluitingsperiodes toe.</p></div><a href="{{ route('beheer.dashboard') }}" class="library-button library-button-secondary">Terug naar beheer</a></div>
@if(session('success'))<p class="library-message">{{ session('success') }}</p>@endif
@if($errors->any())<div class="library-errors">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
<section class="library-form-card">
<form method="get" class="library-loan-filters"><label>Schooljaar (startjaar) <input type="number" name="year" min="2000" max="2098" value="{{ $year }}"></label><button class="library-button library-button-secondary">Tonen</button></form>
<form method="post" action="{{ route('beheer.school-calendar.import') }}">@csrf<input type="hidden" name="school_year" value="{{ $year }}"><button class="library-button library-button-secondary">Officiële Vlaamse vakanties importeren</button></form>
<h2>{{ $year }}–{{ $year+1 }}</h2>
<table class="library-table"><thead><tr><th>Sluiting</th><th>Van</th><th>Tot en met</th><th>Bron</th><th></th></tr></thead><tbody>
@forelse($closures as $closure)
<tr><td>{{ $closure->name }}</td><td>{{ $closure->starts_on }}</td><td>{{ $closure->ends_on }}</td><td>{{ $closure->source === 'official' ? 'Vlaanderen' : 'School' }}</td><td>@if($closure->source === 'custom')<form method="post" action="{{ route('beheer.school-calendar.destroy',$closure->id) }}" onsubmit="return confirm('Sluitingsperiode verwijderen?')">@csrf @method('DELETE')<button class="library-button library-button-secondary">Verwijderen</button></form>@endif</td></tr>
@empty<tr><td colspan="5">Nog geen sluitingsperiodes voor dit schooljaar.</td></tr>@endforelse
</tbody></table>
</section>
<section class="library-form-card"><h2>Schoolgebonden sluitingsdag toevoegen of aanpassen</h2>
<form method="post" action="{{ route('beheer.school-calendar.store') }}" class="library-form-grid">@csrf<input type="hidden" name="school_year" value="{{ $year }}">
<div class="library-field"><label>Naam<input name="name" required maxlength="120" value="{{ old('name') }}"></label></div>
<div class="library-field"><label>Van<input type="date" name="starts_on" required value="{{ old('starts_on') }}"></label></div>
<div class="library-field"><label>Tot en met<input type="date" name="ends_on" required value="{{ old('ends_on') }}"></label></div>
<div class="library-wide"><button class="library-button library-button-primary">Opslaan</button></div></form><p>Gebruik dezelfde naam om een schoolgebonden periode aan te passen. Bij één vrije dag zijn beide datums gelijk.</p></section>
</main>
@endsection
