@extends('layouts.app')
@section('title', 'LVS · Meldingen')
@section('breadcrumbs')
@include('partials.breadcrumbs', ['items' => [
    ['label' => 'Dashboard', 'url' => route('home')],
    ['label' => 'LVS', 'url' => route('lvs.dashboard')],
    ['label' => 'Alle meldingen'],
]])
@endsection
@section('content')
<main class="library-page lvs-notifications-page">
    <div class="library-page-head">
        <div><p class="portal-eyebrow">LEERLINGVOLGSYSTEEM / OPVOLGING</p><h1>Alle meldingen</h1>
        <p>Bekijk en filter geregistreerde meldingen, inclusief datum waarop iets gebeurde en de registratiedatum.</p></div>
        <a href="{{ $canCreate ? route('meldingen.select-student') : route('leerlingen.index') }}" class="library-button library-button-primary">{{ $canCreate ? 'Nieuwe melding' : 'Leerling zoeken' }}</a>
    </div>

    <section class="library-form-card">
        <h2>Zoeken en filteren</h2>
        <form method="get" class="lvs-report-filters">
            <div class="library-field"><label for="zoek">Leerling of omschrijving</label>
                <input type="search" id="zoek" name="zoek" maxlength="100" value="{{ $data['zoek'] ?? '' }}" placeholder="Naam, Smartschool-naam of tekst"></div>
            <div class="library-field"><label for="categorie">Categorie</label>
                <select id="categorie" name="categorie"><option value="">Alle categorieën</option>
                @foreach($categorieen as $categorie)
                    <option value="{{ $categorie->id }}" @selected(($data['categorie'] ?? '') == $categorie->id)>{{ $categorie->naam }}</option>
                @endforeach
                </select>
            </div>
            <div class="library-field"><label for="klas">Klas</label>
                <input type="search" id="klas" name="klas" maxlength="60" value="{{ $data['klas'] ?? '' }}" placeholder="Bijv. 4 EL"></div>
            <div class="library-field"><label for="van">Voorval vanaf</label><input type="date" id="van" name="van" value="{{ $data['van'] ?? '' }}"></div>
            <div class="library-field"><label for="tot">Voorval tot en met</label><input type="date" id="tot" name="tot" value="{{ $data['tot'] ?? '' }}"></div>
            <div class="lvs-report-filter-actions">
                <button class="library-button library-button-primary" type="submit">Filteren</button>
                <a class="library-button library-button-secondary" href="{{ route('meldingen.index') }}">Wissen</a>
            </div>
        </form>
    </section>

    <section class="library-form-card">
        <div class="portal-users-table-heading"><div><h2>Meldingen</h2>
            <p>{{ number_format($meldingen->total(), 0, ',', '.') }} resultaten. Nieuwste voorvallen eerst.</p></div></div>
        <div class="library-table-wrap">
            <table class="library-table lvs-notifications-table">
                <thead><tr><th>Gebeurd op</th><th>Ingegeven op</th><th>Leerling</th><th>Klas</th><th>Categorie / soort</th><th>Omschrijving</th><th>Ingegeven door</th></tr></thead>
                <tbody>
                @forelse($meldingen as $item)
                    <tr>
                        <td>{{ $item->gebeurdop ? \Illuminate\Support\Carbon::parse($item->gebeurdop)->format('d/m/Y') : '—' }}</td>
                        <td>{{ $item->created_at ? \Illuminate\Support\Carbon::parse($item->created_at)->format('d/m/Y H:i') : '—' }}</td>
                        <td><a href="{{ route('leerlingen.show', $item->leerlingId) }}"><strong>{{ $item->voornaam }} {{ $item->naam }}</strong></a></td>
                        <td>{{ $item->klasnaam ?: '—' }}</td>
                        <td><span class="badge badge-cat">{{ $item->categorie ?: '—' }}</span><div>{{ $item->soort ?: '—' }}</div></td>
                        <td class="lvs-notification-comment">{{ $item->comment }}</td>
                        <td>{{ $item->auteur_gebruikersnaam ?? 'Niet geregistreerd' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7">Geen meldingen gevonden met deze filters.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if($meldingen->hasPages())
            <nav class="lvs-pagination" aria-label="Paginering meldingen">
                @if(!$meldingen->onFirstPage())<a href="{{ $meldingen->previousPageUrl() }}">← Vorige</a>@endif
                <span>Pagina {{ $meldingen->currentPage() }} van {{ $meldingen->lastPage() }}</span>
                @if($meldingen->hasMorePages())<a href="{{ $meldingen->nextPageUrl() }}">Volgende →</a>@endif
            </nav>
        @endif
    </section>
</main>
@endsection
