@extends('layouts.app')

@section('title', 'Verjaardagen binnen de week')

@section('content')
    <main class="screen">
        <div class="card">
            <div class="card-header">
                <h2 style="margin:0;">🎂 Verjaardagen binnen de week</h2>
                <a href="{{ route('leerlingen.index') }}" class="btn btn-green btn--sm">← Terug naar overzicht</a>
            </div>
            <p class="muted" style="margin-top:-6px; margin-bottom:12px;">
                {{ $today->format('d/m') }} → {{ $end->format('d/m') }}
            </p>

            @forelse($groups as $ymd => $items)
                <div class="bday-group" style="margin-bottom:14px;">
                    <div class="bday-group-header">
                        <div class="bday-date">
                            {{ ucfirst(\Carbon\CarbonImmutable::parse($ymd)->locale('nl')->isoFormat('dddd D/M')) }}
                        </div>
                        <span class="pill">{{ $items->count() }} leerling(en)</span>
                    </div>

                    <div class="bday-rows">
                        @foreach($items as $l)
                            <a href="{{ route('leerlingen.show', $l->id) }}" class="bday-row-link">
                                <div class="bday-row">
                                    <div class="bday-name">
                                        {{ $l->voornaam }} {{ $l->achternaam }}
                                        <span class="badge badge-klas">{{ optional($l->klas)->klasnaam ?? '—' }}</span>
                                    </div>
                                    <div class="bday-age">
                                        Wordt <strong>{{ $l->turns }}</strong>
                                    </div>
                                </div>
                            </a>
                        @endforeach
                    </div>
                </div>
            @empty
                <p class="muted">Geen jarigen in de komende 7 dagen.</p>
            @endforelse
        </div>
    </main>
@endsection
