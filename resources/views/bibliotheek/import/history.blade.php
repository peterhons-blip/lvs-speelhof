@extends('layouts.app')

@section('title', 'Importhistoriek')
@section('breadcrumbs')
    @include('partials.breadcrumbs', ['items' => [
        ['label' => 'Dashboard', 'url' => route('home')],
        ['label' => 'Bibliotheek', 'url' => route('bibliotheek.index')],
        ['label' => 'Boekencatalogus', 'url' => route('bibliotheek.books.index')],
        ['label' => 'Importhistoriek'],
    ]])
@endsection

@section('content')
<main class="library-page" aria-labelledby="import-history-title">
    <div class="library-page-head">
        <div>
            <p class="portal-eyebrow">BIBLIOTHEEK / ISBN-IMPORT</p>
            <h1 id="import-history-title">Importhistoriek</h1>
            <p>Bekijk je eerdere ISBN-imports en open een import die nog wordt verwerkt.</p>
        </div>
        <a class="library-button library-button-primary" href="{{ route('bibliotheek.books.import.create') }}">+ Nieuwe import</a>
    </div>

    <section class="library-form-card">
        <h2>Mijn imports</h2>
        @if($batches->isEmpty())
            <p>Je hebt nog geen ISBN-lijsten geïmporteerd.</p>
            <a class="library-button library-button-secondary" href="{{ route('bibliotheek.books.import.create') }}">Eerste import starten</a>
        @else
            <div class="library-table-wrap">
                <table class="library-table">
                    <thead>
                        <tr>
                            <th>Gestart op</th>
                            <th>ISBN's</th>
                            <th>Voortgang</th>
                            <th>Resultaat</th>
                            <th>Actie</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($batches as $batch)
                            @php
                                $waiting = (int) $batch->waiting;
                                $added = (int) $batch->added;
                                $duplicates = (int) $batch->duplicates;
                                $notFound = (int) $batch->not_found;
                                $errors = (int) $batch->errors;
                                $processed = (int) $batch->total - $waiting;
                            @endphp
                            <tr>
                                <td>{{ \Illuminate\Support\Carbon::parse($batch->created_at)->format('d/m/Y H:i') }}</td>
                                <td>{{ $batch->total }}</td>
                                <td>
                                    @if($waiting > 0)
                                        <span class="library-pill library-import-pending">Bezig / wachtend</span>
                                        <div class="library-import-subtext">{{ $processed }} van {{ $batch->total }} verwerkt</div>
                                    @else
                                        <span class="library-pill">Afgerond</span>
                                    @endif
                                </td>
                                <td>
                                    {{ $added }} toegevoegd · {{ $duplicates }} dubbel
                                    @if($notFound || $errors)
                                        <div class="library-import-subtext">{{ $notFound }} niet gevonden · {{ $errors }} fouten</div>
                                    @endif
                                </td>
                                <td>
                                    <a class="library-button library-button-secondary" href="{{ route('bibliotheek.books.import.show', $batch->id) }}">
                                        {{ $waiting > 0 ? 'Voortgang bekijken' : 'Resultaten bekijken' }}
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="library-pagination">{{ $batches->links() }}</div>
            <p class="library-help">De resultaten worden opgeslagen in de eigen databank. Een import met status 'Bezig / wachtend' kun je op elk moment opnieuw openen.</p>
        @endif
    </section>
</main>
@endsection
