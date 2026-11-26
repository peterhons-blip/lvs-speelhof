@extends('layouts.app')
@section('title', 'Bibliotheekrapportages')
@section('breadcrumbs')
    @include('partials.breadcrumbs', ['items' => [
        ['label' => 'Dashboard', 'url' => route('home')],
        ['label' => 'Bibliotheek', 'url' => route('bibliotheek.index')],
        ['label' => 'Rapportages'],
    ]])
@endsection
@section('content')
<main class="library-page">
    <div class="library-page-head">
        <div><p class="portal-eyebrow">BIBLIOTHEEK / OVERZICHT</p>
            <h1>Rapportages</h1>
            <p>Voorraadcijfers, uitleningen, populaire titels en exports per periode.</p>
        </div>
        <a href="{{ route('bibliotheek.index') }}" class="library-button library-button-secondary">Terug naar bibliotheek</a>
    </div>
    @if($errors->any())<div class="library-errors" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <section class="library-form-card">
        <h2>Filter uitleningen</h2>
        <form method="get" action="{{ route('bibliotheek.reports.index') }}" class="library-form-grid">
            <div class="library-field"><label for="from">Van</label><input type="date" id="from" name="from" value="{{ $from }}" required></div>
            <div class="library-field"><label for="to">Tot en met</label><input type="date" id="to" name="to" value="{{ $to }}" required></div>
            <div class="library-field"><label for="borrower">Ontlener</label>
                <select id="borrower" name="borrower">
                    @foreach(['all'=>'Iedereen','leerling'=>'Leerlingen','personeel'=>'Personeel'] as $value=>$label)
                        <option value="{{ $value }}" @selected($borrower === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="library-field"><label for="status">Status</label>
                <select id="status" name="status">
                    @foreach(['all'=>'Alle','open'=>'Openstaand','overdue'=>'Te laat','returned'=>'Ingeleverd'] as $value=>$label)
                        <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="library-wide library-report-actions">
                <button class="library-button library-button-primary" type="submit">Rapport bekijken</button>
                <a class="library-button library-button-secondary" href="{{ route('bibliotheek.reports.export', compact('from','to','status','borrower')) }}">Uitleningen exporteren (CSV)</a>
            </div>
        </form>
        <p class="library-help">Voorraadcijfers zijn actueel; uitleningen, populaire boeken en de lijst hieronder volgen de geselecteerde datums en filters. Een uitlening telt mee op basis van de uitleendatum.</p>
    </section>
    <div class="library-report-stats">
        @foreach([
            'Boektitels' => $stats['titles'],
            'Fysieke exemplaren' => $stats['copies'],
            'Beschikbaar' => $stats['available'],
            'Momenteel uitgeleend' => $stats['on_loan'],
            'Momenteel te laat' => $stats['overdue'],
            'QR-code gemaakt' => $stats['qr_generated'],
            'QR-etiket bevestigd' => $stats['qr_printed'],
            'Uitleningen in selectie' => $stats['period_loans']
        ] as $title => $value)
            <div class="library-report-stat"><span>{{ $title }}</span><strong>{{ $value }}</strong></div>
        @endforeach
    </div>
    <section class="library-form-card"><h2>Voorraad per status</h2>@foreach($stockStatus as $row)<p>{{ ucfirst($row->status) }}: <strong>{{ $row->total }}</strong></p>@endforeach
    @if(app(\App\Services\LibraryAccess::class)->has(request(),'bibliotheek.books.view'))<a class="library-button library-button-secondary" href="{{ route('bibliotheek.reports.stock') }}">Voorraad exporteren (CSV voor Excel)</a>@endif</section>
    <section class="library-form-card"><h2>Meest uitgeleende auteurs</h2>@forelse($authors as $row)<p>{{ $row->authors }} · {{ $row->total }} uitleningen</p>@empty<p>Geen gegevens.</p>@endforelse</section>
    <section class="library-form-card"><h2>Registraties per dag (gekozen periode)</h2><table class="library-table"><thead><tr><th>Datum</th><th>Exemplaren</th></tr></thead><tbody>@foreach($registered as $row)<tr><td>{{ $row->day }}</td><td>{{ $row->total }}</td></tr>@endforeach</tbody></table></section>
    <section class="library-form-card"><h2>Nooit uitgeleend (max. 100 titels)</h2>@forelse($unused as $row)<p>{{ $row->title }}</p>@empty<p>Geen titels.</p>@endforelse</section>
    <section class="library-form-card"><h2>Langer dan een jaar niet uitgeleend (max. 100 titels)</h2>@forelse($stale as $row)<p>{{ $row->title }} · {{ $row->last_loan }}</p>@empty<p>Geen titels.</p>@endforelse</section>
    <section class="library-form-card">
        <h2>Uitleningen per maand</h2>
        <div class="library-table-wrap"><table class="library-table">
            <thead><tr><th>Maand</th><th>Aantal uitleningen</th></tr></thead>
            <tbody>
                @forelse($monthly as $item)
                    <tr><td>{{ $item->month }}</td><td>{{ $item->total }}</td></tr>
                @empty
                    <tr><td colspan="2">Geen uitleningen in de gekozen periode.</td></tr>
                @endforelse
            </tbody>
        </table></div>
        <p class="library-help">Maandtotalen tonen alle nieuwe uitleningen in de periode, ongeacht de status- en ontlenerfilter.</p>
    </section>
    <section class="library-form-card">
        <h2>Meest uitgeleende boeken</h2>
        <div class="library-table-wrap"><table class="library-table">
            <thead><tr><th>Boek</th><th>Auteur</th><th>Uitleningen</th></tr></thead>
            <tbody>
                @forelse($popular as $book)
                    <tr><td><a href="{{ route('bibliotheek.books.show', $book->id) }}">{{ $book->title }}</a></td>
                        <td>{{ $book->authors ?: '—' }}</td><td>{{ $book->times_loaned }}</td></tr>
                @empty
                    <tr><td colspan="3">Nog geen uitleningen gevonden.</td></tr>
                @endforelse
            </tbody>
        </table></div>
    </section>
    <section class="library-form-card">
        <h2>Meest actieve ontleners</h2>
        <div class="library-table-wrap"><table class="library-table">
            <thead><tr><th>Ontlener</th><th>Type</th><th>Aantal uitleningen</th></tr></thead>
            <tbody>
                @forelse($borrowers as $person)
                    <tr>
                        <td>{{ $person->staff_user_id !== null
                            ? trim(($person->staff_first ?? '').' '.($person->staff_last ?? ''))
                            : trim(($person->student_first ?? '').' '.($person->student_last ?? '')) }}</td>
                        <td>{{ $person->staff_user_id !== null ? 'Personeel' : 'Leerling' }}</td>
                        <td>{{ $person->times_loaned }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3">Geen ontleners gevonden.</td></tr>
                @endforelse
            </tbody>
        </table></div>
    </section>
    <section class="library-form-card">
        <h2>Uitleningen ({{ $stats['period_loans'] }})</h2>
        <div class="library-table-wrap"><table class="library-table">
            <thead><tr><th>Datum</th><th>Ontlener</th><th>Boek</th><th>Exemplaarcode</th><th>Inleverdatum</th><th>Status</th></tr></thead>
            <tbody>
                @forelse($loans as $loan)
                    <tr>
                        <td>{{ $loan->loan_date }}</td>
                        <td>{{ $loan->staff_user_id !== null
                            ? trim(($loan->staff_first ?? '').' '.($loan->staff_last ?? ''))
                            : trim(($loan->student_first ?? '').' '.($loan->student_last ?? '')) }}</td>
                        <td>{{ $loan->title }}</td><td>{{ $loan->barcode }}</td>
                        <td>{{ $loan->due_date }}</td>
                        <td>{{ $loan->returned_at ? 'Ingeleverd' : ($loan->due_date < now('Europe/Brussels')->toDateString() ? 'Te laat' : 'Uitgeleend') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6">Geen uitleningen gevonden.</td></tr>
                @endforelse
            </tbody>
        </table></div>
        <div class="library-pagination">{{ $loans->links() }}</div>
    </section>
</main>
@endsection
