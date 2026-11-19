@extends('layouts.app')
@section('title', 'Beheerdashboard')
@section('breadcrumbs')
@include('partials.breadcrumbs', ['items' => [
    ['label' => 'Dashboard', 'url' => route('home')],
    ['label' => 'Beheer'],
]])
@endsection
@section('content')
<main class="library-page portal-management-dashboard">
    <div class="library-page-head">
        <div>
            <p class="portal-eyebrow">PORTAALBEHEER / OVERZICHT</p>
            <h1>Beheerdashboard</h1>
            <p>Een centraal overzicht van je beheermodules, actuele aantallen en nachtelijke taken.</p>
        </div>
    </div>

    @if(count($stats))
    <div class="portal-management-stats">
        @foreach($stats as $stat)
            <section class="library-form-card portal-management-stat">
                <p class="portal-management-eyebrow">{{ $stat['label'] }}</p>
                <strong>{{ number_format($stat['value'], 0, ',', '.') }}</strong>
                <p>{{ $stat['detail'] }}</p>
            </section>
        @endforeach
    </div>
    @endif

    <section class="library-form-card">
        <div class="portal-users-table-heading">
            <div>
                <h2>Beheersonderdelen</h2>
                <p>Je ziet uitsluitend de onderdelen waarvoor je schoolrol toegang heeft.</p>
            </div>
        </div>
        <div class="portal-management-grid">
            @foreach($sections as $section)
                <a class="portal-management-tile" href="{{ route($section['route']) }}">
                    <div class="portal-management-tile-heading">
                        <strong>{{ $section['title'] }}</strong>
                        <span aria-hidden="true">↗</span>
                    </div>
                    <p>{{ $section['description'] }}</p>
                </a>
            @endforeach
        </div>
    </section>

    @if(app(\App\Services\LibraryAccess::class)->has(request(), 'beheer.scheduled_tasks.view'))
    <section class="library-form-card">
        <div class="portal-users-table-heading">
            <div>
                <h2>Laatste nachtelijke taken</h2>
                <p>Meest recente geregistreerde uitvoering per taak. Details en logs vind je onder Geplande taken.</p>
            </div>
            <a class="library-button library-button-secondary" href="{{ route('beheer.scheduled-tasks.index') }}">Alle geplande taken</a>
        </div>
        @if($recentTasks->isEmpty())
            <p class="portal-management-muted">Er zijn nog geen gemonitorde uitvoeringen beschikbaar.</p>
        @else
            <div class="library-table-wrap">
                <table class="library-table">
                    <thead><tr><th>Taak</th><th>Laatste uitvoering</th><th>Status</th></tr></thead>
                    <tbody>
                    @foreach($recentTasks as $task)
                        <tr>
                            <td><strong>{{ $task->title }}</strong></td>
                            <td>{{ $task->started_at ? \Illuminate\Support\Carbon::parse($task->started_at)->timezone('Europe/Brussels')->format('d/m/Y H:i') : '—' }}</td>
                            <td>{{ ['completed' => 'Gelukt', 'failed' => 'Mislukt', 'warning' => 'Aandacht nodig', 'running' => 'Bezig'][$task->status] ?? 'Onbekend' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
    @endif
</main>
@endsection
