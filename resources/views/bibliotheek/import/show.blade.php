@extends('layouts.app')
@section('title', 'Resultaat ISBN-import')
@section('breadcrumbs')
    @include('partials.breadcrumbs', ['items' => [
        ['label' => 'Dashboard', 'url' => route('home')],
        ['label' => 'Bibliotheek', 'url' => route('bibliotheek.index')],
        ['label' => 'Boekencatalogus', 'url' => route('bibliotheek.books.index')],
        ['label' => 'Importresultaat'],
    ]])
@endsection
@section('content')
<main class="library-page">
    <div class="library-page-head">
        <div><p class="portal-eyebrow">BIBLIOTHEEK / ISBN-IMPORT</p><h1>Importoverzicht</h1>
        <p>{{ $batch->total }} exemplaren ingevoerd (herhaalde ISBN's tellen mee). De verwerking verloopt via de bibliotheekwachtrij.</p></div>
        <div class="library-form-actions" style="margin:0">
            <a class="library-button library-button-secondary" href="{{ route('bibliotheek.books.import.history') }}">Importhistoriek</a>
            <a class="library-button library-button-secondary" href="{{ route('bibliotheek.books.import.create') }}">Nieuwe import</a>
        </div>
    </div>
    @if(session('success'))<div class="library-message" role="status">{{ session('success') }}</div>@endif
    <section class="library-form-card">
        <h2>Voortgang</h2>
        @if(($counts['wachten'] ?? 0) > 0)
            <form action="{{ route('bibliotheek.books.import.stop', $batch->id) }}" method="post"
                onsubmit="return confirm('De nog wachtende exemplaren stoppen? Reeds toegevoegde exemplaren blijven bestaan.');">
                @csrf
                <button class="library-button library-button-secondary" type="submit">Import stoppen</button>
            </form>
        @endif
        <p id="import-progress" role="status">Wachten: {{ $counts['wachten'] ?? 0 }} · Toegevoegd: {{ $counts['toegevoegd'] ?? 0 }} · Extra exemplaren: {{ $counts['exemplaar'] ?? 0 }} · Gestopt: {{ $counts['gestopt'] ?? 0 }} · Dubbel (oude import): {{ $counts['dubbel'] ?? 0 }} · Niet gevonden: {{ $counts['niet_gevonden'] ?? 0 }} · Fouten: {{ $counts['fout'] ?? 0 }}</p>
        <p class="library-help">Je kunt deze pagina sluiten: een actieve Laravel queue-worker verwerkt de lijst verder. Als de teller blijft wachten, moet de bibliotheek-worker gestart worden.</p>
        <button class="library-button library-button-secondary" type="button" onclick="window.location.reload()">Overzicht vernieuwen</button>
    </section>
    <section class="library-form-card">
        <h2>ISBN-nummers en resultaten</h2>
        <div class="library-table-wrap"><table class="library-table">
            <thead><tr><th>ISBN</th><th>Status</th><th>Boek / melding</th></tr></thead><tbody>
                @foreach($items as $item)
                <tr><td>{{ $item->isbn }}</td><td>{{ str_replace('_', ' ', ucfirst($item->status)) }}</td><td>{{ $item->title ?: $item->message ?: 'Nog niet verwerkt' }}</td></tr>
                @endforeach
            </tbody>
        </table></div>
    </section>
    <a class="library-button library-button-primary" href="{{ route('bibliotheek.books.index') }}">Naar catalogus</a>
</main>
@endsection
@push('scripts')
<script>
(() => {
    const progress = document.getElementById('import-progress');
    const url = @json(route('bibliotheek.books.import.status', $batch->id));
    // Finished batches do not need polling (and must not reload endlessly).
    if (@json((int) ($counts['wachten'] ?? 0)) === 0) return;
    let polls = 0;
    const timer = setInterval(async () => {
        if (polls++ > 180) return clearInterval(timer);
        try {
            const response = await fetch(url, {headers: {Accept: 'application/json'}});
            if (!response.ok) return;
            const data = await response.json();
            const count = data.counts || {};
            progress.textContent = 'Wachten: ' + (count.wachten || 0) + ' · Toegevoegd: ' + (count.toegevoegd || 0) + ' · Extra exemplaren: ' + (count.exemplaar || 0) + ' · Gestopt: ' + (count.gestopt || 0) + ' · Dubbel (oude import): ' + (count.dubbel || 0) + ' · Niet gevonden: ' + (count.niet_gevonden || 0) + ' · Fouten: ' + (count.fout || 0);
            if (!data.pending) { clearInterval(timer); window.location.reload(); }
        } catch { /* handmatig vernieuwen blijft mogelijk */ }
    }, 5000);
})();
</script>
@endpush
