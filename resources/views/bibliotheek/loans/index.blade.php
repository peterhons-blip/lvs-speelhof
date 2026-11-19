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
        <a href="{{ route('bibliotheek.loans.create') }}" class="library-button library-button-primary">+ Boek uitlenen</a>
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
        <form method="post" action="{{ route('bibliotheek.loans.return-barcode') }}" class="library-loan-filters">
            @csrf
            <div class="library-field">
                <label for="return-barcode">Scan of typ de barcode van het exemplaar</label>
                <input id="return-barcode" name="barcode" placeholder="BIB-..." required maxlength="60">
            </div>
            <button type="submit" class="library-button library-button-primary">Als ingeleverd registreren</button>
        </form>
    </section>

    <section class="library-form-card">
        <form method="get" class="library-loan-filters">
            <div class="library-field"><label for="loan-search">Zoeken op leerling, titel of barcode</label>
                <input id="loan-search" name="q" value="{{ $search }}" placeholder="Naam, gebruikersnaam, boek of barcode">
            </div>
            <div class="library-field"><label for="loan-filter">Toon</label>
                <select name="filter" id="loan-filter">
                    @foreach(['open' => 'Openstaand', 'overdue' => 'Te laat', 'returned' => 'Ingeleverd', 'all' => 'Alle uitleningen'] as $value => $label)
                        <option value="{{ $value }}" @selected($mode === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="library-button library-button-secondary">Filteren</button>
        </form>

        <div class="library-table-wrap">
            <table class="library-table">
                <thead><tr><th>Leerling</th><th>Boek / exemplaar</th><th>Uitgeleend</th><th>Inleveren voor</th><th>Status</th><th>Acties</th></tr></thead>
                <tbody>
                @forelse($loans as $loan)
                    @php($late = !$loan->returned_at && $loan->due_date < now('Europe/Brussels')->toDateString())
                    <tr>
                        <td>
                            <strong>{{ trim(($loan->voornaam ?? '').' '.($loan->naam ?? '')) ?: 'Leerling #'.$loan->leerling_id }}</strong>
                            <div class="library-help">{{ $loan->gebruikersnaam ?: 'Niet beschikbaar' }}</div>
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
                        </td>
                        <td>
                            @unless($loan->returned_at)
                                <div class="library-copy-actions">
                                    <form method="post" action="{{ route('bibliotheek.loans.return', $loan->id) }}" onsubmit="return confirm('Exemplaar {{ $loan->barcode }} als ingeleverd registreren?');">
                                        @csrf
                                        <button type="submit" class="library-button library-button-secondary">Inleveren</button>
                                    </form>
                                    <a class="library-button library-button-secondary" href="{{ route('bibliotheek.loans.reminders.edit', $loan->id) }}">Herinneringen</a>
                                    <form method="post" action="{{ route('bibliotheek.loans.extend', $loan->id) }}">
                                        @csrf
                                        <button type="submit" class="library-button library-button-secondary">Verlengen ({{ $loan->extensions }})</button>
                                    </form>
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
