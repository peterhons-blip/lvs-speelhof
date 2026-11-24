@extends('layouts.app')
@section('title', 'QR-labels exemplaren')
@section('breadcrumbs')
    @include('partials.breadcrumbs', ['items' => [
        ['label' => 'Dashboard', 'url' => route('home')],
        ['label' => 'Bibliotheek', 'url' => route('bibliotheek.index')],
        ['label' => 'Boekencatalogus', 'url' => route('bibliotheek.books.index')],
        ['label' => 'QR-labels'],
    ]])
@endsection
@section('content')
<main class="library-page">
    <div class="library-page-head">
        <div><p class="portal-eyebrow">BIBLIOTHEEK / INVENTARIS</p>
            <h1>QR-codes voor exemplaren</h1>
            <p>Elke QR-code bevat de unieke exemplaarbarcode. Gebruik een QR-scanner om hetzelfde exemplaar te herkennen bij het uitlenen.</p>
        </div>
        <a class="library-button library-button-secondary" href="{{ route('bibliotheek.books.index', $search ? ['q' => $search] : []) }}">Terug naar catalogus</a>
    </div>
    <section class="library-form-card">
        <div class="library-qr-toolbar">
            <strong>{{ $copies->count() }} van {{ $total }} exemplaren</strong>
            <button type="button" class="library-button library-button-primary" id="qr-download-zip">Alle QR-codes downloaden (ZIP)</button>
            <button type="button" class="library-button library-button-secondary" id="qr-print">Labels afdrukken / als PDF opslaan</button>
        </div>
        @if($total > 500)
            <p class="library-help">Er zijn meer dan 500 exemplaren. Om de browser vlot te houden worden maximaal 500 labels per keer gegenereerd. Filter op titel of ISBN in de catalogus om de overige exemplaren afzonderlijk te downloaden.</p>
        @endif
        <p id="qr-feedback" class="library-help" role="status">QR-codes genereren...</p>
        <div id="qr-grid" class="library-qr-grid"></div>
    </section>
</main>
@endsection
@push('scripts')
<script src="{{ asset('js/vendor/qrcode.js') }}"></script>
<script src="{{ asset('js/vendor/jszip.min.js') }}"></script>
<script src="{{ asset('js/library-qr.js') }}"></script>
<script>
(() => {
    const copies = @json($copies);
    const feedback = document.getElementById('qr-feedback');
    const grid = document.getElementById('qr-grid');
    let ready = false;
    try {
        for (const copy of copies) grid.appendChild(LibraryQr.createLabel(copy));
        ready = true;
        feedback.textContent = copies.length ? 'QR-codes klaar om te downloaden of af te drukken.' : 'Geen exemplaren gevonden.';
    } catch (error) {
        feedback.textContent = 'QR-codes genereren is mislukt: ' + error.message;
    }
    document.getElementById('qr-print').addEventListener('click', () => {
        if (ready) window.print();
    });
    document.getElementById('qr-download-zip').addEventListener('click', async () => {
        if (!ready || !copies.length) return;
        const button = document.getElementById('qr-download-zip');
        button.disabled = true;
        feedback.textContent = 'ZIP-bestand wordt samengesteld...';
        try {
            await LibraryQr.downloadZip(copies, 'bibliotheek-exemplaren-qr.zip');
            feedback.textContent = 'ZIP met ' + copies.length + ' QR-codes klaar.';
        } catch (error) {
            feedback.textContent = 'Download mislukt: ' + error.message;
        } finally {
            button.disabled = false;
        }
    });
})();
</script>
@endpush
