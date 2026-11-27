@extends('layouts.app')
@section('title','Systeemprestaties')
@section('breadcrumbs')
@include('partials.breadcrumbs',['items'=>[
    ['label'=>'Dashboard','url'=>route('home')],
    ['label'=>'Beheer','url'=>route('beheer.dashboard')],
    ['label'=>'Systeemprestaties'],
]])
@endsection
@section('content')
<main class="library-page">
    <div class="library-page-head">
        <div><p class="portal-eyebrow">PORTAALBEHEER / ICT</p><h1>Systeemprestaties</h1><p>Responstijden, databankbelasting en indexanalyse. Uitsluitend voor ICT.</p></div>
        <a class="library-button library-button-secondary" href="{{ route('beheer.performance.index',['period'=>$period]) }}">Vernieuwen</a>
    </div>
    @php
        $routeLabels = ['home'=>'Hoofddashboard','lvs.dashboard'=>'LVS-dashboard','students.photo'=>'Leerlingfoto','leerlingen.show'=>'Leerlingendossier','leerlingen.index'=>'Leerlingenoverzicht','meldingen.index'=>'Meldingen','meldingen.create'=>'Nieuwe melding','beheer.dashboard'=>'Beheer','beheer.users.index'=>'Personeelsbeheer','beheer.permissions.index'=>'Rollen en rechten','bibliotheek.index'=>'Bibliotheek','personal-library.index'=>'Persoonlijke bibliotheek','smartschool.callback'=>'Smartschool-aanmelding'];
    @endphp
    @if($databaseError)<div class="library-errors">Niet alle databankdiagnostiek kon worden opgehaald.</div>@endif
    <div class="portal-management-stats">
        <section class="library-form-card portal-management-stat"><p>Databankverbinding</p><strong>{{ $local ? 'Lokaal' : 'Extern / anders' }}</strong><p>{{ $version ?? 'Onbekend' }}</p></section>
        <section class="library-form-card portal-management-stat"><p>Gemiddelde SELECT 1 (5 queries)</p><strong>{{ $latency === null ? '—' : number_format($latency,2,',','.').' ms' }}</strong><p>Via bestaande verbinding</p></section>
        <section class="library-form-card portal-management-stat"><p>Databankgrootte</p><strong>{{ $size === null ? '—' : number_format($size/1048576,2,',','.').' MB' }}</strong><p>Data en indexen</p></section>
        <section class="library-form-card portal-management-stat"><p>Vrije schijfruimte (storage)</p><strong>{{ $disk === false ? '—' : number_format($disk/1073741824,1,',','.').' GB' }}</strong></section>
        <section class="library-form-card portal-management-stat"><p>PHP / OPcache</p><strong>{{ PHP_VERSION }}</strong><p>{{ function_exists('opcache_get_status') && (opcache_get_status(false)['opcache_enabled'] ?? false) ? 'OPcache actief' : 'OPcache niet bevestigd' }}</p></section>
        <section class="library-form-card portal-management-stat"><p>Laravel-configuratiecache</p><strong>{{ app()->configurationIsCached() ? 'Actief' : 'Uit' }}</strong><p>Sessie: {{ config('session.driver') }} · Cache: {{ config('cache.default') }}</p></section>
    </div>
    <section class="library-form-card" style="margin-top:20px">
        <h2>Responstijden per route</h2>
        <p>Metingen: {{ config('lvs_performance.enabled') ? 'actief' : 'uitgeschakeld' }} · Logdrempel: {{ config('lvs_performance.slow_request_ms') }} ms · {{ count($rows) }} gevonden metingen.</p>
        <p>Alleen gelogde aanvragen, maximaal 2.000 recente regels uit de laatste 4 MiB per logbestand (vast log en daglogs van vandaag en gisteren). Bij een drempel boven nul ontbreken snelle aanvragen. De cijfers zijn geen volledige verkeersstatistiek.</p>
        <form method="get" class="library-loan-filters">
            <label>Periode <select name="period">
                @foreach(['15'=>'Laatste 15 minuten','60'=>'Laatste uur','1440'=>'Laatste 24 uur','all'=>'Alle beschikbare metingen'] as $value=>$label)
                    <option value="{{ $value }}" @selected((string)$value===$period)>{{ $label }}</option>
                @endforeach
            </select></label>
            <button class="library-button library-button-secondary">Toepassen</button>
        </form>
        <div class="library-table-wrap"><table class="library-table">
            <thead><tr><th>Route</th><th>Aantal</th><th>Gem. ms</th><th>P95 ms</th><th>Max. ms</th><th>Gem. SQL-queries</th><th>Gem. SQL-ms</th></tr></thead>
            <tbody>@forelse($summary as $row)
                <tr><td><strong>{{ $routeLabels[$row['route']] ?? $row['route'] }}</strong><br><small>{{ $row['route'] }}</small></td><td>{{ $row['count'] }}</td><td style="font-weight:700;color:{{ $row['avg'] < 300 ? '#167044' : ($row['avg'] < 1000 ? '#946200' : '#b42318') }}">{{ round($row['avg'],1) }}</td><td>{{ round($row['p95'],1) }}</td><td>{{ round($row['max'],1) }}</td><td>{{ round($row['queries'],1) }}</td><td>{{ round($row['sql'],1) }}</td></tr>
            @empty<tr><td colspan="7">Geen metingen voor deze periode.</td></tr>@endforelse</tbody>
        </table></div>
    </section>
    <section class="library-form-card" style="margin-top:20px">
        <h2>Verloop per uur</h2>
        <p>Enkel geregistreerde aanvragen; geen totaal verkeer. Oude metingen kunnen dateren van vóór de databankverhuis.</p>
        @php($maxAvg = max(1, max(array_column($hourly,'avg') ?: [0])))
        @forelse($hourly as $hour)
            <div style="margin:12px 0"><strong>{{ $hour['hour'] }}</strong> · {{ $hour['count'] }} metingen · {{ round($hour['avg'],1) }} ms gemiddeld
                <div style="height:10px;background:#e5edf2;border-radius:4px;overflow:hidden"><div style="height:10px;background:#087eaf;width:{{ round(100*$hour['avg']/$maxAvg,2) }}%"></div></div>
            </div>
        @empty<p>Geen metingen.</p>@endforelse
        <h3>HTTP-fouten in geregistreerde antwoorden</h3>
        <p>Status bekend voor {{ $statusKnown }} van {{ count($rows) }} metingen. Uitzonderingen zonder geregistreerd antwoord kunnen ontbreken.</p>
        @forelse($errors as $code=>$count)<p>HTTP {{ $code }}: {{ $count }}</p>@empty<p>Geen geregistreerde foutstatussen.</p>@endforelse
    </section>
    <section class="library-form-card" style="margin-top:20px">
        <h2>Databankindexen</h2>
        <p>Controle van indexprefixen voor veelgebruikte querypatronen. Een ontbrekend prefix is een onderzoekspunt, geen bewijs dat een index nodig is. Geen automatische schemawijzigingen.</p>
        <div class="library-table-wrap"><table class="library-table">
            <thead><tr><th>Tabel</th><th>Kolommen (volgorde)</th><th>Beoordeling</th><th>Bestaande index</th></tr></thead>
            <tbody>@forelse($indexes as $index)
                <tr><td>{{ $index['table'] }}</td><td>{{ implode(', ',$index['columns']) }}</td><td>{{ ['covered'=>'Aanwezig','review'=>'Onderzoeken','unavailable'=>'Tabel/kolom niet aanwezig'][$index['status']] }}</td><td>{{ $index['index'] ?? '—' }}</td></tr>
            @empty<tr><td colspan="4">Geen indexanalyse beschikbaar.</td></tr>@endforelse</tbody>
        </table></div>
    </section>
    <section class="library-form-card" style="margin-top:20px">
        <h2>Volledige indexinventaris</h2>
        <details><summary>Alle bestaande indexen tonen ({{ count($inventory) }})</summary>
        <div class="library-table-wrap"><table class="library-table"><thead><tr><th>Tabel</th><th>Index</th><th>Kolommen (volgorde)</th><th>Uniek</th><th>Type</th></tr></thead><tbody>
        @foreach($inventory as $item)<tr><td>{{ $item['table'] }}</td><td>{{ $item['name'] }}</td><td>{{ implode(', ',$item['columns']) }}</td><td>{{ $item['unique'] ? 'Ja' : 'Nee' }}</td><td>{{ $item['type'] }}</td></tr>@endforeach
        </tbody></table></div></details>
    </section>
    <section class="library-form-card" style="margin-top:20px">
        <h2>Databankgrootte per tabel</h2>
        <div class="library-table-wrap"><table class="library-table"><thead><tr><th>Tabel</th><th>Rijen (schatting)</th><th>Data KB</th><th>Index KB</th><th>Totaal KB</th></tr></thead><tbody>
        @foreach($tableSizes as $item)<tr><td>{{ $item->table_name }}</td><td>{{ $item->estimated_rows ?? '—' }}</td><td>{{ round($item->data_bytes/1024,1) }}</td><td>{{ round($item->index_bytes/1024,1) }}</td><td>{{ round(($item->data_bytes+$item->index_bytes)/1024,1) }}</td></tr>@endforeach
        </tbody></table></div>
    </section>
    <section class="library-form-card" style="margin-top:20px">
        <h2>Laatste metingen</h2>
        <div class="library-table-wrap"><table class="library-table">
            <thead><tr><th>Tijd</th><th>Route</th><th>ms</th><th>SQL-queries</th><th>SQL-ms</th><th>PHP MB</th></tr></thead>
            <tbody>@forelse($recent as $row)
                <tr><td>{{ $row['at'] }}</td><td>{{ $row['route'] }}</td><td>{{ $row['duration_ms'] }}</td><td>{{ $row['sql_queries'] }}</td><td>{{ $row['sql_time_ms'] }}</td><td>{{ $row['memory'] }}</td></tr>
            @empty<tr><td colspan="6">Geen metingen.</td></tr>@endforelse</tbody>
        </table></div>
    </section>
</main>
@endsection
