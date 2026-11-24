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
<style>
@@page { size: A4 portrait; margin: 0; }
@media print {
    html, body { width: 210mm !important; margin: 0 !important; padding: 0 !important; background: white !important; }
    .portal-topbar, .portal-sidebar, .portal-page-breadcrumb, .library-page-head,
    .library-qr-toolbar, .library-label-settings, #qr-feedback { display: none !important; }
    .app, .portal-workspace, .portal-content, .library-page, .library-form-card, .library-label-sheets {
        display: block !important; width: 210mm !important; min-width: 0 !important;
        max-width: none !important; height: auto !important; margin: 0 !important;
        padding: 0 !important; border: 0 !important; box-shadow: none !important;
        background: transparent !important; overflow: visible !important;
    }
    .library-label-sheet {
        margin: 0 !important; box-shadow: none !important;
        break-after: page; page-break-after: always;
    }
    .library-label-sheet:last-child { break-after: auto; page-break-after: auto; }
    .library-sheet-label, .library-sheet-label-empty { border: 0 !important; }
}
</style>
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
            <strong id="qr-selection-count">0 geselecteerd</strong>
            <button type="button" class="library-button library-button-primary" id="qr-download-zip">Alle QR-codes downloaden (ZIP)</button>
            <button type="button" class="library-button library-button-secondary" id="qr-print">A4-etiketten afdrukken / PDF opslaan</button>
        </div>
        @if($total > 500)
            <p class="library-help">Er zijn meer dan 500 exemplaren. Om de browser vlot te houden worden maximaal 500 labels per keer gegenereerd. Filter op titel of ISBN in de catalogus om de overige exemplaren afzonderlijk te downloaden.</p>
        @endif
        <div class="library-label-selection">
            <form method="get" action="{{ route('bibliotheek.books.qr-labels') }}" class="library-loan-filters">
                @if($search !== '')<input type="hidden" name="q" value="{{ $search }}">@endif
                @if($bookId !== null)<input type="hidden" name="book" value="{{ $bookId }}">@endif
                <div class="library-field">
                    <label for="qr-selection-mode">Welke exemplaren?</label>
                    <select name="select" id="qr-selection-mode">
                        <option value="all" @selected($mode === 'all')>Alle exemplaren in de selectie (max. 500)</option>
                        <option value="latest5" @selected($mode === 'latest5')>5 laatst toegevoegde exemplaren</option>
                        <option value="selected" @selected($mode === 'selected')>Zelf kiezen uit de lijst (max. 500)</option>
                    </select>
                </div>
                <button type="submit" class="library-button library-button-secondary">Toepassen</button>
            </form>
            <p class="library-help">{{ $mode === 'latest5' ? 'Gesorteerd op laatst toegevoegd.' : 'Vink alleen de exemplaren aan waarvoor je een QR-etiket wilt maken.' }}</p>
            <div class="library-qr-toolbar">
                <button type="button" class="library-button library-button-secondary" id="qr-select-all">Alles selecteren</button>
                <button type="button" class="library-button library-button-secondary" id="qr-select-none">Alles deselecteren</button>
            </div>
            <div class="library-qr-choices">
                @foreach($copies as $copy)
                    <label class="library-qr-choice">
                        <input class="qr-copy-checkbox" type="checkbox" value="{{ $copy->id }}" checked>
                        <span><strong>{{ $copy->title }}</strong><small>{{ $copy->barcode }}</small></span>
                    </label>
                @endforeach
            </div>
        </div>
        <div class="library-field library-label-settings">
            <label for="qr-start-position">Eerste etiket op het eerste A4-vel</label>
            <select id="qr-start-position">
                @for($position = 1; $position <= 21; $position++)
                    <option value="{{ $position }}" @selected($position === 1)>Positie {{ $position }} (rij {{ intdiv($position - 1, 3) + 1 }}, kolom {{ ($position - 1) % 3 + 1 }})</option>
                @endfor
            </select>
            <p class="library-help">Etiketten: 63,5 × 38,1 mm · 3 kolommen × 7 rijen · 21 per A4. Kies bijvoorbeeld positie 8 als de eerste zeven etiketten al gebruikt zijn.</p>
            <p class="library-help">Kies bij het afdrukken A4, schaal 100%, marges 'Geen' en schakel kop- en voetteksten uit. Als je een PDF wilt, kies je 'Opslaan als PDF' als printer. Druk eerst één testvel af op gewoon papier.</p>
        </div>
        <p id="qr-feedback" class="library-help" role="status">QR-codes genereren...</p>
        <div id="qr-sheets" class="library-label-sheets"></div>
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
    const checks = Array.from(document.querySelectorAll('.qr-copy-checkbox'));
    const countElement = document.getElementById('qr-selection-count');
    function selectedCopies() {
        const selectedIds = new Set(checks.filter(box => box.checked).map(box => String(box.value)));
        return copies.filter(copy => selectedIds.has(String(copy.id)));
    }
    document.getElementById('qr-select-all').addEventListener('click', () => {
        checks.forEach(box => { box.checked = true; });
        renderSheets();
    });
    document.getElementById('qr-select-none').addEventListener('click', () => {
        checks.forEach(box => { box.checked = false; });
        renderSheets();
    });
    checks.forEach(box => box.addEventListener('change', renderSheets));
    const feedback = document.getElementById('qr-feedback');
    const sheets = document.getElementById('qr-sheets');
    const startPosition = document.getElementById('qr-start-position');
    function makeLabel(copy) {
        const label = document.createElement('div');
        label.className = 'library-sheet-label';
        const qr = document.createElement('img');
        qr.src = LibraryQr.dataUrl(copy.barcode);
        qr.alt = 'QR-code ' + copy.barcode;
        const text = document.createElement('div');
        text.className = 'library-sheet-label-text';
        const title = document.createElement('strong');
        title.textContent = copy.title || 'Boek';
        title.title = copy.title || '';
        const code = document.createElement('span');
        code.textContent = copy.barcode;
        text.append(title, code);
        label.append(qr, text);
        return label;
    }
    function renderSheets() {
        sheets.replaceChildren();
        const offset = Number(startPosition.value) - 1;
        const selected = selectedCopies();
        countElement.textContent = selected.length + ' van ' + copies.length + ' exemplaren geselecteerd';
        const slots = Array(offset).fill(null).concat(selected);
        const sheetCount = selected.length ? Math.ceil(slots.length / 21) : 0;
        for (let page = 0; page < sheetCount; page++) {
            const sheet = document.createElement('div');
            sheet.className = 'library-label-sheet';
            for (let i = 0; i < 21; i++) {
                const copy = slots[page * 21 + i];
                const slot = copy ? makeLabel(copy) : document.createElement('div');
                if (!copy) slot.className = 'library-sheet-label library-sheet-label-empty';
                sheet.appendChild(slot);
            }
            sheets.appendChild(sheet);
        }
    }
    startPosition.addEventListener('change', () => { if (ready) renderSheets(); });
    let ready = false;
    try {
        renderSheets();
        ready = true;
        feedback.textContent = copies.length ? 'Selecteer de gewenste exemplaren en download hun QR-codes of print etiketten.' : 'Geen exemplaren gevonden.';
    } catch (error) {
        feedback.textContent = 'QR-codes genereren is mislukt: ' + error.message;
    }
    document.getElementById('qr-print').addEventListener('click', () => {
        if (ready && selectedCopies().length) window.print();
        else feedback.textContent = 'Selecteer eerst minstens één exemplaar.';
    });
    document.getElementById('qr-download-zip').addEventListener('click', async () => {
        if (!ready || !selectedCopies().length) {
            feedback.textContent = 'Selecteer eerst minstens één exemplaar.';
            return;
        }
        const button = document.getElementById('qr-download-zip');
        button.disabled = true;
        feedback.textContent = 'ZIP-bestand wordt samengesteld...';
        try {
            const selected = selectedCopies();
            await LibraryQr.downloadZip(selected, 'bibliotheek-exemplaren-qr.zip');
            feedback.textContent = 'ZIP met ' + selected.length + ' QR-codes klaar.';
        } catch (error) {
            feedback.textContent = 'Download mislukt: ' + error.message;
        } finally {
            button.disabled = false;
        }
    });
})();
</script>
@endpush
