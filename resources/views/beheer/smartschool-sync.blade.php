@extends('layouts.app')
@section('title', 'Leerlingensynchronisatie')
@section('breadcrumbs')
@include('partials.breadcrumbs', ['items' => [
    ['label' => 'Dashboard', 'url' => route('home')],
    ['label' => 'Beheer', 'url' => route('beheer.users.index')],
    ['label' => 'Leerlingensynchronisatie'],
]])
@endsection
@section('content')
<main class="library-page portal-users-page portal-student-sync-page">
    <div class="library-page-head">
        <div>
            <p class="portal-eyebrow">PORTAALBEHEER / SMARTSCHOOL</p>
            <h1>Leerlingensynchronisatie</h1>
            <p>Synchroniseer leerlingen, klassen en QR-scancodes uit Smartschool. De codes worden opgeslagen in onze eigen leerlingenlijst.</p>
        </div>
    </div>

    @if(session('success'))<div class="library-message" role="status">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="library-errors" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <div class="portal-sync-stats">
        <div class="portal-sync-stat"><span>Actieve leerlingen</span><strong>{{ number_format($pupils, 0, ',', '.') }}</strong></div>
        <div class="portal-sync-stat"><span>Leerlingen met QR-code</span><strong>{{ number_format($codes, 0, ',', '.') }}</strong></div>
        <div class="portal-sync-stat"><span>Automatische synchronisatie</span><strong>Elke nacht om 03.00 uur</strong></div>
    </div>

    <section class="library-form-card">
        <h2>Synchronisatie handmatig starten</h2>
        <p class="portal-users-card-intro">Dezelfde synchronisatie als 's nachts wordt in de wachtrij geplaatst. Ze actualiseert leerlingen, klassen, actieve status en, waar beschikbaar, de Smartschool-scancode. Tijdens de synchronisatie worden geen bibliotheekuitleningen gewijzigd.</p>
        @if(!$ready)
            <div class="library-errors" role="alert">De nieuwe migraties zijn nog niet uitgevoerd. Synchronisatie via dit scherm is daarom nog niet beschikbaar.</div>
        @else
            <form method="post" action="{{ route('beheer.smartschool-sync.start') }}" class="portal-sync-form">
                @csrf
                <label class="portal-sync-option">
                    <input type="checkbox" name="refresh_scancodes" value="1">
                    <span><strong>Ook bestaande scancodes opnieuw ophalen</strong><small>Normaal niet nodig. Vraagt mogelijk een extra Smartschool-oproep per leerling en duurt langer.</small></span>
                </label>
                <label class="portal-sync-option">
                    <input type="checkbox" name="confirm_sync" value="1" required>
                    <span>Ik bevestig dat ik de leerlingengegevens vanuit Smartschool wil synchroniseren.</span>
                </label>
                <div class="portal-role-actions">
                    <span class="portal-role-help">De afzonderlijke wachtrijworker <code>smartschool-sync</code> moet actief zijn.</span>
                    <button type="submit" class="library-button library-button-primary"
                        @disabled($runs->contains(fn ($run) => in_array($run->status, ['waiting', 'running'], true)))>
                        Synchronisatie starten
                    </button>
                </div>
            </form>
        @endif
    </section>

    <section class="library-form-card">
        <div class="portal-users-table-heading">
            <div><h2>Recente handmatige synchronisaties</h2><p>Ververs deze pagina om de voortgang te bekijken.</p></div>
            <a class="library-button library-button-secondary" href="{{ route('beheer.smartschool-sync.index') }}">Verversen</a>
        </div>
        <div class="library-table-wrap">
            <table class="library-table">
                <thead><tr><th>Gestart door</th><th>Aangevraagd</th><th>Status</th><th>Resultaat</th></tr></thead>
                <tbody>
                @forelse($runs as $run)
                    <tr>
                        <td>{{ $run->requested_by }}</td>
                        <td>{{ \Illuminate\Support\Carbon::parse($run->created_at)->timezone('Europe/Brussels')->format('d/m/Y H:i') }}</td>
                        <td><span class="portal-sync-status portal-sync-status-{{ $run->display_status }}">{{ $run->display_status_text }}</span></td>
                        <td class="portal-sync-result">{{ $run->display_summary }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4">Nog geen handmatige synchronisaties uitgevoerd.</td></tr>
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
            <a class="library-button library-button-secondary" href="{{ route('beheer.smartschool-sync.index') }}">Verversen</a>
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
