@extends('layouts.app')
@section('title','Back-ups')
@section('breadcrumbs')
@include('partials.breadcrumbs',['items'=>[
    ['label'=>'Dashboard','url'=>route('home')],
    ['label'=>'Beheer','url'=>route('beheer.dashboard')],
    ['label'=>'Back-ups'],
]])
@endsection
@section('content')
<main class="library-page">
    <div class="library-page-head">
        <div><p class="portal-eyebrow">PORTAALBEHEER / ICT</p><h1>Back-ups</h1><p>Versleutelde databankback-ups van SRV-WEB naar Azure. Alleen-lezen.</p></div>
        <a class="library-button library-button-secondary" href="{{ route('beheer.backups.index') }}">Vernieuwen</a>
    </div>
    @if(!$backup || $backup['age'] > 5400 || $backup['local'] !== 'ok' || $backup['external'] !== 'ok')
        <div class="library-errors" role="alert">Back-upstatus ontbreekt, is verouderd of niet volledig geslaagd. Controleer Windows Taakplanner en de back-uplogs.</div>
    @else
        <p style="color:#167044;font-weight:700">Laatste lokale back-up en externe upload gemeld als geslaagd.</p>
    @endif
    <div class="portal-management-stats">
        <section class="library-form-card portal-management-stat"><p>Laatste poging</p><strong>{{ $backup ? \Carbon\Carbon::parse($backup['timestamp'])->setTimezone('Europe/Brussels')->format('d/m/Y H:i:s') : 'Onbekend' }}</strong><p>{{ $backup ? round($backup['age']/60).' minuten geleden' : 'Geen statusbestand' }}</p></section>
        <section class="library-form-card portal-management-stat"><p>Lokaal</p><strong>{{ $backup ? ['ok'=>'Geslaagd','failed'=>'Mislukt'][$backup['local']] : 'Onbekend' }}</strong><p>Bewaarbeleid: 14 dagen na succesvolle upload</p></section>
        <section class="library-form-card portal-management-stat"><p>Azure</p><strong>{{ $backup ? ['ok'=>'Upload geslaagd','failed'=>'Mislukt','not_configured'=>'Niet ingesteld'][$backup['external']] : 'Onbekend' }}</strong><p>Ingesteld beleid: 30 dagen</p></section>
        <section class="library-form-card portal-management-stat"><p>Grootte laatste export</p><strong>{{ $backup ? number_format($backup['bytes']/1024,1,',','.').' KB' : 'Onbekend' }}</strong><p>Versleuteld bestand</p></section>
        <section class="library-form-card portal-management-stat"><p>Geslaagde pogingen in historiek</p><strong>{{ $successful }}</strong></section>
        <section class="library-form-card portal-management-stat"><p>Mislukte pogingen in historiek</p><strong>{{ $failed }}</strong></section>
    </div>
    <section class="library-form-card" style="margin-top:20px">
        <h2>Back-uphistoriek</h2>
        <p>Maximaal 800 geregistreerde pogingen. De historiek start na installatie van het nieuwe script; oudere back-ups worden niet gereconstrueerd. Dit is een uitvoeringshistoriek, geen live inventaris van aanwezige Azure-bestanden.</p>
        <div class="library-table-wrap"><table class="library-table">
            <thead><tr><th>Tijdstip (Brussel)</th><th>Bestand</th><th>Grootte</th><th>Lokaal</th><th>Extern</th></tr></thead>
            <tbody>@forelse($history as $row)
                <tr><td>{{ \Carbon\Carbon::parse($row['timestamp'])->setTimezone('Europe/Brussels')->format('d/m/Y H:i:s') }}</td><td>{{ $row['file'] ?? '—' }}</td><td>{{ number_format($row['bytes']/1024,1,',','.') }} KB</td><td>{{ ['ok'=>'Geslaagd','failed'=>'Mislukt'][$row['local']] }}</td><td>{{ ['ok'=>'Upload geslaagd','failed'=>'Mislukt','not_configured'=>'Niet ingesteld'][$row['external']] }}</td></tr>
            @empty<tr><td colspan="5">Nog geen historiek. De volgende back-uppoging registreert een nieuwe meting.</td></tr>@endforelse</tbody>
        </table></div>
    </section>
    <section class="library-form-card" style="margin-top:20px">
        <h2>Herstel en dekking</h2>
        <p>De huidige taak maakt uitsluitend een databankback-up. Leerlingfoto's, documenten, configuratie en APP_KEY vereisen nog een afzonderlijke back-up. Een geslaagde upload bewijst niet dat herstel getest is.</p>
        <p>De webapplicatie heeft geen toegang nodig tot de back-upbestanden, private sleutels of Azure-inloggegevens.</p>
    </section>
</main>
@endsection
