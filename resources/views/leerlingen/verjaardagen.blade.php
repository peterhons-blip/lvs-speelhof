@extends('layouts.app')

@section('title', 'LVS · Verjaardagen')

@section('breadcrumbs')
@include('partials.breadcrumbs', ['items' => [
        ['label' => 'Dashboard', 'url' => route('home')],
        ['label' => 'LVS', 'url' => route('leerlingen.index')],
        ['label' => 'Verjaardagen'],
    ]])
@endsection

@section('content')
<main class="screen lvs-screen">
    <div class="lvs-page-heading">
        <div>
            <p class="portal-eyebrow">LEERLINGVOLGSYSTEEM / VERJAARDAGEN</p>
            <h1>Verjaardagen</h1>
            <p>Leerlingen die jarig zijn van {{ $today->format('d/m') }} tot en met {{ $end->format('d/m') }}.</p>
        </div>
    </div>
    <section class="card lvs-panel">
        <div class="lvs-panel-heading">
            <div><h2>Deze week jarig</h2><p>Een overzicht per dag.</p></div>
        </div>
        @forelse($groups as $ymd => $items)
            <div class="bday-group">
                <div class="bday-group-header">
                    <div class="bday-date">{{ ucfirst(\Carbon\CarbonImmutable::parse($ymd)->locale('nl')->isoFormat('dddd D/M')) }}</div>
                    <span class="lvs-count-pill">{{ $items->count() }} {{ $items->count() === 1 ? 'leerling' : 'leerlingen' }}</span>
                </div>
                <div class="bday-rows">
                    @foreach($items as $l)
                        <a href="{{ route('leerlingen.show', $l->id) }}" class="bday-row-link">
                            <div class="bday-row">
                                <div class="bday-name">
                                    <strong>{{ $l->voornaam }} {{ $l->achternaam }}</strong>
                                    <span class="badge badge-klas">{{ optional($l->klas)->klasnaam ?? '—' }}</span>
                                </div>
                                <div class="bday-age">Wordt <strong>{{ $l->turns }}</strong> jaar</div>
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>
        @empty
            <p class="lvs-empty">Geen verjaardagen in de komende 7 dagen.</p>
        @endforelse
    </section>
</main>
@endsection
