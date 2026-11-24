@extends('layouts.app')
@section('title', 'Verzonden items')
@section('breadcrumbs')
@include('partials.breadcrumbs', ['items' => [
    ['label' => 'Dashboard', 'url' => route('home')],
    ['label' => 'Beheer', 'url' => route('beheer.dashboard')],
    ['label' => 'Verzonden items'],
]])
@endsection
@section('content')
<main class="library-page portal-users-page portal-sent-items-page">
    <div class="library-page-head">
        <div>
            <p class="portal-eyebrow">PORTAALBEHEER / COMMUNICATIE</p>
            <h1>Verzonden items</h1>
            <p>Overzicht van Smartschool-berichten en e-mails die door het LVS zijn aangeboden, met de ontvangers en verzendstatus.</p>
        </div>
    </div>
    <section class="library-form-card">
        <h2>Zoeken en filteren</h2>
        <form method="get" action="{{ route('beheer.sent-items.index') }}" class="portal-sent-filters">
            <div class="library-field">
                <label for="sent-channel">Kanaal</label>
                <select id="sent-channel" name="channel">
                    <option value="">Alle kanalen</option>
                    <option value="smartschool" @selected(($data['channel'] ?? '') === 'smartschool')>Smartschool</option>
                    <option value="email" @selected(($data['channel'] ?? '') === 'email')>E-mail</option>
                </select>
            </div>
            <div class="library-field">
                <label for="sent-status">Status</label>
                <select id="sent-status" name="status">
                    <option value="">Alle statussen</option>
                    <option value="sent" @selected(($data['status'] ?? '') === 'sent')>Aangeboden</option>
                    <option value="failed" @selected(($data['status'] ?? '') === 'failed')>Mislukt</option>
                </select>
            </div>
            <div class="library-field">
                <label for="sent-from">Vanaf</label>
                <input type="date" id="sent-from" name="date_from" value="{{ $data['date_from'] ?? '' }}">
            </div>
            <div class="library-field">
                <label for="sent-to">Tot en met</label>
                <input type="date" id="sent-to" name="date_to" value="{{ $data['date_to'] ?? '' }}">
            </div>
            <div class="library-field portal-sent-search">
                <label for="sent-search">Zoek ontvanger, onderwerp, afzender of module</label>
                <input type="search" id="sent-search" name="search" maxlength="150"
                    value="{{ $data['search'] ?? '' }}" placeholder="Gebruikersnaam, e-mailadres of onderwerp">
            </div>
            <div class="portal-sent-actions">
                <button type="submit" class="library-button library-button-primary">Zoeken</button>
                <a href="{{ route('beheer.sent-items.index') }}" class="library-button library-button-secondary">Wissen</a>
            </div>
        </form>
    </section>

    <section class="library-form-card">
        <div class="portal-users-table-heading">
            <div>
                <h2>Verzendhistoriek</h2>
                @if($hasHistory)
                    <p>Geregistreerde verzendingen: {{ number_format($totals->sum('total'), 0, ',', '.') }} binnen de gekozen filters.</p>
                @else
                    <p>De nieuwe registratietabel is nog niet aanwezig.</p>
                @endif
            </div>
        </div>
        @if(!$hasHistory)
            <p class="portal-sync-feedback">Voer eerst de gerichte migratie <code>2026_11_24_170000_create_portal_sent_items.php</code> uit. De bestaande berichten blijven wel werken; de historiek wordt pas vanaf installatie gevuld.</p>
        @else
            <div class="library-table-wrap">
                <table class="library-table portal-sent-table">
                    <thead><tr>
                        <th>Datum en tijd</th><th>Kanaal</th><th>Module</th><th>Van</th>
                        <th>Naar</th><th>Onderwerp</th><th>Status</th>
                    </tr></thead>
                    <tbody>
                    @forelse($items as $item)
                        <tr>
                            <td class="portal-sent-nowrap">{{ $item->created_at ? \Illuminate\Support\Carbon::parse($item->created_at)->timezone('Europe/Brussels')->format('d/m/Y H:i:s') : '—' }}</td>
                            <td>{{ $item->channel === 'smartschool' ? 'Smartschool' : 'E-mail' }}</td>
                            <td>{{ $item->source }}</td>
                            <td class="portal-sent-target">{{ $item->sender ?: '—' }}</td>
                            <td class="portal-sent-target">
                                <strong>{{ $item->recipient }}</strong>
                                @if($item->channel === 'smartschool' && $item->coaccount !== null && $item->coaccount > 0)
                                    <small>Co-account {{ $item->coaccount }}</small>
                                @elseif($item->channel === 'email' && $item->recipient_type)
                                    <small>{{ strtoupper($item->recipient_type) }}</small>
                                @endif
                            </td>
                            <td>{{ $item->subject ?: '—' }}</td>
                            <td>
                                <span class="portal-sync-status portal-sync-status-{{ $item->status === 'sent' ? 'completed' : 'failed' }}">
                                    {{ $item->status === 'sent' ? 'Aangeboden' : 'Mislukt' }}
                                </span>
                                @if($item->status === 'failed' && $item->error_message)
                                    <details><summary>Technische fout</summary><small>{{ $item->error_message }}</small></details>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7">Geen verzendingen gevonden met deze filters.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @if($items->hasPages())
                <div class="portal-sent-pagination">
                    @if($items->onFirstPage())
                        <span>Vorige</span>
                    @else
                        <a href="{{ $items->previousPageUrl() }}">Vorige</a>
                    @endif
                    <span>Pagina {{ $items->currentPage() }} van {{ $items->lastPage() }}</span>
                    @if($items->hasMorePages())
                        <a href="{{ $items->nextPageUrl() }}">Volgende</a>
                    @else
                        <span>Volgende</span>
                    @endif
                </div>
            @endif
        @endif
        <p class="library-help portal-sent-note">
            De historiek bevat alleen berichten die via dit LVS zijn verzonden <strong>nadat de centrale registratie werd geïnstalleerd</strong>.
            Oude Smartschool-berichten en e-mails kunnen niet automatisch uit de mailboxen worden teruggehaald.
            'Aangeboden' betekent dat de verzenddienst de oproep zonder gemelde fout aanvaardde, niet dat de ontvanger het bericht gelezen heeft.
            Er worden geen berichtinhoud, bijlagen of vertrouwelijke technische toegangssleutels opgeslagen.
        </p>
    </section>
</main>
@endsection
