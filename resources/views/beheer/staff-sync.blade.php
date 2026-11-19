@extends('layouts.app')
@section('title', 'Personeel synchroniseren')
@section('breadcrumbs')
@include('partials.breadcrumbs', ['items' => [
    ['label' => 'Dashboard', 'url' => route('home')],
    ['label' => 'Beheer', 'url' => route('beheer.users.index')],
    ['label' => 'Personeel synchroniseren'],
]])
@endsection
@section('content')
<main class="library-page portal-users-page portal-staff-sync-page">
    <div class="library-page-head">
        <div>
            <p class="portal-eyebrow">PORTAALBEHEER / SMARTSCHOOL</p>
            <h1>Personeel synchroniseren</h1>
            <p>Haal leerkrachten en administratieve personeelsleden inclusief subgroepen rechtstreeks uit Smartschool op.</p>
        </div>
        <a class="library-button library-button-secondary" href="{{ route('beheer.users.index') }}">Terug naar gebruikers</a>
    </div>
    @if(session('success'))<div class="library-message" role="status">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="library-errors" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <section class="library-form-card">
        <h2>Synchronisatie starten</h2>
        <p class="portal-users-card-intro">De hoofdgroep <strong>Leerkrachten Atheneum Sint-Truiden</strong> wordt recursief ingelezen als Leerkracht. De hoofdgroep <strong>Administratie Campus Speelhof</strong> wordt recursief ingelezen als Administratie. Bij beide groepslidmaatschappen krijgt Administratie voorrang.</p>
        <p>Handmatig toegekende speciale rollen worden behouden. Accounts die eerder via deze sync werden opgehaald maar niet meer in Smartschool staan, worden na een volledig geslaagde synchronisatie geblokkeerd en verborgen in de gewone gebruikerslijst. Er wordt niets verwijderd.</p>
        @if(!$ready)
            <div class="portal-sync-feedback">De synchronisatie is nog niet geïnstalleerd. Voer de migratie voor personeelssynchronisatie uit.</div>
        @else
            <form method="post" action="{{ route('beheer.staff-sync.start') }}" class="portal-sync-form">
                @csrf
                <label class="portal-sync-option">
                    <input type="checkbox" name="apply_changes" value="1">
                    <span><strong>Wijzigingen werkelijk uitvoeren</strong><small>Niet aanvinken = uitsluitend veilige preview. Aangevinkt = accounts aanmaken/bijwerken en verdwenen gesynchroniseerde accounts blokkeren.</small></span>
                </label>
                <label class="portal-sync-option">
                    <input type="checkbox" name="confirm_sync" value="1" required>
                    <span>Ik bevestig dat ik de personeelssynchronisatie wil starten.</span>
                </label>
                <div class="portal-role-actions">
                    <span class="portal-role-help">De Smartschool-wachtrijworker moet actief zijn.</span>
                    <button type="submit" class="library-button library-button-primary" @disabled($runs->contains(fn ($run) => in_array($run->status, ['waiting', 'running'], true)))>Synchronisatie aanvragen</button>
                </div>
            </form>
        @endif
    </section>
    <section class="library-form-card">
        <div class="portal-users-table-heading">
            <div><h2>Laatste synchronisaties</h2><p>Ververs om de actuele status te bekijken.</p></div>
            <a class="library-button library-button-secondary" href="{{ route('beheer.staff-sync.index') }}">Verversen</a>
        </div>
        <div class="library-table-wrap">
            <table class="library-table">
                <thead><tr><th>Gestart door</th><th>Aangevraagd</th><th>Modus</th><th>Status</th><th>Resultaat</th></tr></thead>
                <tbody>
                @forelse($runs as $run)
                    <tr>
                        <td>{{ $run->requested_by }}</td>
                        <td>{{ \Illuminate\Support\Carbon::parse($run->created_at)->timezone('Europe/Brussels')->format('d/m/Y H:i') }}</td>
                        <td>{{ $run->apply_changes ? 'Wijzigen' : 'Preview' }}</td>
                        <td><span class="portal-sync-status portal-sync-status-{{ $run->status }}">{{ ['waiting' => 'In wachtrij', 'running' => 'Bezig', 'completed' => 'Voltooid', 'failed' => 'Mislukt'][$run->status] ?? $run->status }}</span></td>
                        <td class="portal-sync-result">{{ $run->summary ?: 'Nog geen resultaat.' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5">Nog geen synchronisaties uitgevoerd.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
    <section class="library-form-card portal-night-jobs">
        <div class="portal-users-table-heading">
            <div>
                <h2>Geplande nachtelijke taken</h2>
                <p>Werkelijke uitvoering en resultaat, bijgehouden vanaf de installatie van deze monitoring.</p>
            </div>
            <a class="library-button library-button-secondary" href="{{ route('beheer.staff-sync.index') }}">Verversen</a>
        </div>
        @if(!$taskMonitoringEnabled)
            <div class="portal-sync-feedback">De monitoring is nog niet geactiveerd. Voer eerst de nieuwe migratie voor <code>scheduled_task_runs</code> uit.</div>
        @else
            <div class="library-table-wrap">
                <table class="library-table portal-night-table">
                    <thead>
                    <tr><th>Taak</th><th>Planning</th><th>Laatste uitvoering</th><th>Status</th><th>Resultaat</th></tr>
                    </thead>
                    <tbody>
                    @foreach($scheduledTasks as $taskKey => $task)
                        @php($last = $lastTaskRuns->get($taskKey))
                        @php($lastStatus = $last?->status ?? 'unknown')
                        @php($lastStatusText = ['running' => 'Bezig', 'completed' => 'Gelukt', 'warning' => 'Aandacht nodig', 'failed' => 'Mislukt', 'unknown' => 'Nog geen registratie'][$lastStatus] ?? 'Onbekend')
                        <tr>
                            <td>
                                <strong>{{ $task['title'] }}</strong>
                                <div class="portal-night-description">{{ $task['description'] }}</div>
                            </td>
                            <td class="portal-night-nowrap">{{ $task['time'] }} uur</td>
                            <td class="portal-night-nowrap">{{ $last ? \Illuminate\Support\Carbon::parse($last->started_at)->timezone('Europe/Brussels')->format('d/m/Y H:i') : '—' }}</td>
                            <td><span class="portal-sync-status portal-sync-status-{{ $lastStatus }}">{{ $lastStatusText }}</span></td>
                            <td class="portal-sync-result">{{ $last?->summary ?: 'Nog geen gemonitorde uitvoering.' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <p class="portal-night-footnote">Alle tijden zijn Belgische tijd. Een taak zonder registratie is niet automatisch mislukt: historische uitvoeringen van vóór deze wijziging zijn niet beschikbaar. ‘Gelukt’ betekent dat de opdracht zonder gemelde uitvoerfout eindigde; afzonderlijke Smartschool-acties kunnen aanvullende controle vereisen.</p>
        @endif
    </section>

    @if($taskMonitoringEnabled && $taskHistory->isNotEmpty())
    <section class="library-form-card portal-night-jobs">
        <h2>Historiek nachtelijke taken</h2>
        <p class="portal-users-card-intro">De laatste {{ $taskHistory->count() }} werkelijk geregistreerde uitvoeringen.</p>
        <div class="library-table-wrap">
            <table class="library-table portal-night-history">
                <thead><tr><th>Taak</th><th>Uitgevoerd</th><th>Duur</th><th>Status</th><th>Wat is er gebeurd?</th></tr></thead>
                <tbody>
                @foreach($taskHistory as $run)
                    @php($task = $scheduledTasks[$run->task_key] ?? null)
                    @php($started = \Illuminate\Support\Carbon::parse($run->started_at)->timezone('Europe/Brussels'))
                    @php($finished = $run->finished_at ? \Illuminate\Support\Carbon::parse($run->finished_at)->timezone('Europe/Brussels') : null)
                    <tr>
                        <td><strong>{{ $task['title'] ?? $run->task_key }}</strong></td>
                        <td class="portal-night-nowrap">{{ $started->format('d/m/Y H:i') }}</td>
                        <td>{{ $finished ? $started->diffForHumans($finished, true) : '—' }}</td>
                        <td><span class="portal-sync-status portal-sync-status-{{ $run->status }}">{{ [
                            'completed' => 'Gelukt', 'warning' => 'Aandacht nodig',
                            'failed' => 'Mislukt', 'running' => 'Bezig'
                        ][$run->status] ?? $run->status }}</span></td>
                        <td class="portal-sync-result">{{ $run->summary ?: 'Wordt verwerkt.' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </section>
    @endif
</main>
@endsection
