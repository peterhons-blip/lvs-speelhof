@extends('layouts.app')
@section('title', 'Uitleningen')
@section('breadcrumbs')
    @include('partials.breadcrumbs', ['items' => [
        ['label' => 'Dashboard', 'url' => route('home')],
        ['label' => 'Bibliotheek', 'url' => route('bibliotheek.index')],
        ['label' => 'Uitleningen'],
    ]])
@endsection
@section('content')
<main class="library-page">
    <div class="library-page-head">
        <div>
            <p class="portal-eyebrow">BIBLIOTHEEK / UITLEENBEHEER</p>
            <h1>Uitleningen</h1>
            <p>Registreer, verleng en sluit uitleningen af per fysiek exemplaar.</p>
        </div>
        @if(app(\App\Services\LibraryAccess::class)->has(request(),'bibliotheek.loans.create'))
<a href="{{ route('bibliotheek.loans.create') }}" class="library-button library-button-primary">+ Boek uitlenen</a>
@endif
    </div>
    @if(session('success'))<div class="library-message" role="status">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="library-errors" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <div class="library-loan-stats">
        <a href="{{ route('bibliotheek.loans.index', ['filter' => 'open']) }}" class="library-loan-stat">
            <span>Openstaande uitleningen</span><strong>{{ $totals['open'] }}</strong>
        </a>
        <a href="{{ route('bibliotheek.loans.index', ['filter' => 'overdue']) }}" class="library-loan-stat {{ $totals['overdue'] ? 'library-loan-stat-alert' : '' }}">
            <span>Te laat</span><strong>{{ $totals['overdue'] }}</strong>
        </a>
        <a href="{{ route('bibliotheek.loans.index', ['filter' => 'returned']) }}" class="library-loan-stat">
            <span>Ingeleverd</span><strong>{{ $totals['returned'] }}</strong>
        </a>
    </div>

    <section class="library-form-card">
        <h2>Snel inleveren met barcode</h2>
        @if(app(\App\Services\LibraryAccess::class)->has(request(),'bibliotheek.loans.return'))
<form method="post" action="{{ route('bibliotheek.loans.return-barcode') }}" class="library-loan-filters">
            @csrf
            <div class="library-field">
                <label for="return-barcode">Scan of typ de barcode van het exemplaar</label>
                <input id="return-barcode" name="barcode" placeholder="BIB-..." required maxlength="60">
            </div>
            <button type="submit" class="library-button library-button-primary">Als ingeleverd registreren</button>
        </form>
@endif
    </section>

    @if(app(\App\Services\LibraryAccess::class)->has(request(),'bibliotheek.loans.bulk'))
    <section class="library-form-card"><h2>Meerdere uitleningen verwerken</h2>
        <form id="bulk-loans" method="post" action="{{ route('bibliotheek.loans.bulk') }}" onsubmit="return confirm('Geselecteerde uitleningen verwerken?')">@csrf
            <label>Scan meerdere exemplaarcodes (één per regel, maximaal 100; enkel voor inleveren)<textarea name="barcodes" rows="3" maxlength="7000"></textarea></label>
            <button class="library-button library-button-primary" name="action" value="return">Inleveren</button>
            <button class="library-button library-button-secondary" name="action" value="extend">Selectie verlengen</button>
            <button type="button" class="library-button library-button-secondary" onclick="document.querySelectorAll('.bulk-loan-check').forEach(c=>c.checked=true)">Selecteer alle openstaande op deze pagina</button>
        </form>
    </section>
    @endif
    <section class="library-form-card">
        <form method="get" class="library-loan-filters">
            @foreach(['student_id','staff_id','copy_id'] as $key)@if(request()->filled($key))<input type="hidden" name="{{ $key }}" value="{{ request($key) }}">@endif @endforeach
            @if(request()->hasAny(['student_id','staff_id','copy_id']))<a href="{{ route('bibliotheek.loans.index',['filter'=>'all']) }}">Historiekfilter wissen</a>@endif
            <div class="library-field"><label for="loan-search">Zoeken op ontlener, titel of barcode</label>
                <input id="loan-search" name="q" value="{{ $search }}" placeholder="Naam, gebruikersnaam, boek of barcode">
            </div>
            <div class="library-field"><label for="loan-filter">Toon</label>
                <select name="filter" id="loan-filter">
                    @foreach(['open' => 'Openstaand', 'overdue' => 'Te laat', 'returned' => 'Ingeleverd', 'all' => 'Alle uitleningen', 'departed' => 'Uitgeschreven leerlingen'] as $value => $label)
                        <option value="{{ $value }}" @selected($mode === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="library-button library-button-secondary">Filteren</button>
        </form>

        <div class="library-table-wrap">
            <table class="library-table">
                <thead><tr><th>Ontlener / selectie</th><th>Boek / exemplaar</th><th>Uitgeleend</th><th>Inleveren voor</th><th>Status</th><th>Acties</th></tr></thead>
                <tbody>
                @forelse($loans as $loan)
                    @php($late = !$loan->returned_at && $loan->due_date < now('Europe/Brussels')->toDateString())
                    <tr>
                        <td>
                            @if(!$loan->returned_at && app(\App\Services\LibraryAccess::class)->has(request(),'bibliotheek.loans.bulk'))<label><input class="bulk-loan-check" type="checkbox" form="bulk-loans" name="ids[]" value="{{ $loan->id }}"> Selecteren</label><br>@endif
                            @if($loan->staff_user_id)
                                <strong>{{ trim(($loan->staff_voornaam ?? '').' '.($loan->staff_naam ?? '')) ?: 'Personeelslid #'.$loan->staff_user_id }}</strong>
                                <div class="library-help">Personeel · {{ $loan->staff_gebruikersnaam ?: 'Geen gebruikersnaam' }}</div>
                            @else
                                <strong>{{ trim(($loan->voornaam ?? '').' '.($loan->naam ?? '')) ?: 'Leerling #'.$loan->leerling_id }}</strong>
                                <div class="library-help">Leerling · {{ $loan->gebruikersnaam ?: 'Niet beschikbaar' }}</div>
                            @endif
                            <a href="{{ route('bibliotheek.loans.index',['filter'=>'all',($loan->staff_user_id ? 'staff_id' : 'student_id')=>($loan->staff_user_id ?: $loan->leerling_id)]) }}">Historiek ontlener</a>
                        </td>
                        <td><strong>{{ $loan->title }}</strong><div class="library-help">{{ $loan->barcode }}</div></td>
                        <td>{{ \Illuminate\Support\Carbon::parse($loan->loan_date)->format('d/m/Y') }}</td>
                        <td>{{ \Illuminate\Support\Carbon::parse($loan->due_date)->format('d/m/Y') }}</td>
                        <td>
                            @if($loan->returned_at)
                                <span class="library-pill">Ingeleverd</span>
                            @elseif($late)
                                <span class="library-loan-overdue">Te laat ({{ now('Europe/Brussels')->startOfDay()->diffInDays(\Illuminate\Support\Carbon::parse($loan->due_date)) }} dagen)</span>
                            @else
                                <span class="library-pill">Uitgeleend</span>
                            @endif
                            @if(!$loan->returned_at && $loan->reminders_paused_until && $loan->reminders_paused_until >= now('Europe/Brussels')->toDateString())<div class="library-help">Herinneringen gepauzeerd t/m {{ $loan->reminders_paused_until }}</div>@endif
                        </td>
                        <td>
                            @unless($loan->returned_at)
                                <div class="library-copy-actions">
                                    @if(app(\App\Services\LibraryAccess::class)->has(request(),'bibliotheek.loans.return'))
<form method="post" action="{{ route('bibliotheek.loans.return', $loan->id) }}" onsubmit="return confirm('Exemplaar {{ $loan->barcode }} als ingeleverd registreren?');">
                                        @csrf
                                        <button type="submit" class="library-button library-button-secondary">Inleveren</button>
                                    </form>
@endif
                                    @if(app(\App\Services\LibraryAccess::class)->has(request(),'bibliotheek.reminders.edit'))
<a class="library-button library-button-secondary" href="{{ route('bibliotheek.loans.reminders.edit', $loan->id) }}">Herinneringen / bericht sturen</a>
@endif
                                    @if(app(\App\Services\LibraryAccess::class)->has(request(),'bibliotheek.loans.extend'))
<form method="post" action="{{ route('bibliotheek.loans.extend', $loan->id) }}">
                                        @csrf
                                        <button type="submit" class="library-button library-button-secondary">Verlengen ({{ $loan->extensions }})</button>
                                    </form>
@endif
                                </div>
                            @endunless
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6">Geen uitleningen gevonden.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="library-pagination">{{ $loans->links() }}</div>
    </section>
</main>
@endsection
