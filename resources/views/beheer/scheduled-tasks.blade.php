@extends('layouts.app')
@section('title', 'Geplande taken')
@section('breadcrumbs')
@include('partials.breadcrumbs', ['items' => [
    ['label' => 'Dashboard', 'url' => route('home')],
    ['label' => 'Beheer', 'url' => route('beheer.users.index')],
    ['label' => 'Geplande taken'],
]])
@endsection
@section('content')
<main class="library-page portal-users-page portal-student-sync-page">
    <div class="library-page-head">
        <div>
            <p class="portal-eyebrow">PORTAALBEHEER / AUTOMATISERING</p>
            <h1>Geplande taken</h1>
            <p>Alle geplande taken met hun type, uitvoering, status en geregistreerde resultaten.</p>
        </div>
    </div>
    <section class="library-form-card portal-night-jobs">
        <div class="portal-users-table-heading">
            <div>
                <h2>Geplande taken</h2>
                <p>Alle vijf Laravel-schedulertaken. Laatste uitvoering, status en logbestanden op één plaats.</p>
            </div>
            <a class="library-button library-button-secondary" href="{{ route('beheer.scheduled-tasks.index') }}">Verversen</a>
        </div>
        @if(!$taskMonitoringEnabled)
            <div class="portal-sync-feedback">De monitoring is nog niet geactiveerd. Voer eerst de nieuwe migratie voor <code>scheduled_task_runs</code> uit.</div>
        @else
            <div class="library-table-wrap">
                <table class="library-table portal-night-table">
                    <thead>
                    <tr><th>Taak en type</th><th>Planning</th><th>Laatste uitvoering</th><th>Status</th><th>Resultaat</th><th>Log</th></tr>
                    </thead>
                    <tbody>
                    @foreach($scheduledTasks as $taskKey => $task)
                        @php($last = $lastTaskRuns->get($taskKey))
                        @php($lastStatus = $last?->status ?? 'unknown')
                        @php($lastStatusText = ['running' => 'Bezig', 'completed' => 'Gelukt', 'warning' => 'Aandacht nodig', 'failed' => 'Mislukt', 'unknown' => 'Nog geen registratie'][$lastStatus] ?? 'Onbekend')
                        <tr>
                            <td>
                                <strong>{{ $task['title'] }}</strong>
                                <div class="portal-night-description">{{ $task['type'] }} · {{ $task['description'] }}</div>
                            </td>
                            <td class="portal-night-nowrap">{{ $task['time'] }} uur</td>
                            <td class="portal-night-nowrap">{{ $last ? \Illuminate\Support\Carbon::parse($last->started_at)->timezone('Europe/Brussels')->format('d/m/Y H:i') : '—' }}</td>
                            <td><span class="portal-sync-status portal-sync-status-{{ $lastStatus }}">{{ $lastStatusText }}</span></td>
                            <td class="portal-sync-result">{{ $last?->summary ?: 'Nog geen gemonitorde uitvoering.' }}</td>
                            <td><a class="library-button library-button-secondary" href="{{ route('beheer.scheduled-tasks.log', $taskKey) }}" target="_blank" rel="noopener noreferrer">Bekijk log</a></td>
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
        <p class="portal-users-card-intro">De laatste {{ $taskHistory->count() }} geregistreerde uitvoeringen en hun samengevatte logs. Technische details zijn beschikbaar in de beveiligde serverlogs.</p>
        <div class="library-table-wrap">
            <table class="library-table portal-night-history">
                <thead><tr><th>Taak</th><th>Uitgevoerd</th><th>Duur</th><th>Status</th><th>Wat is er gebeurd?</th></tr></thead>
                <tbody>
                @foreach($taskHistory as $run)
                    @php($task = $scheduledTasks[$run->task_key] ?? null)
                    @php($started = \Illuminate\Support\Carbon::parse($run->started_at)->timezone('Europe/Brussels'))
                    @php($finished = $run->finished_at ? \Illuminate\Support\Carbon::parse($run->finished_at)->timezone('Europe/Brussels') : null)
                    <tr>
                        <td><strong>{{ $task['title'] ?? $run->task_key }} ({{ $task['type'] ?? 'Overig' }})</strong></td>
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
