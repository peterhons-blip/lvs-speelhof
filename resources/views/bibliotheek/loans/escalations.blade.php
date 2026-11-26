@extends('layouts.app')
@section('title', 'Bibliotheek · Opvolging')
@section('breadcrumbs')
@include('partials.breadcrumbs', ['items'=>[
 ['label'=>'Dashboard','url'=>route('home')],
 ['label'=>'Bibliotheek','url'=>route('bibliotheek.index')],
 ['label'=>'Uitleningen','url'=>route('bibliotheek.loans.index')],
 ['label'=>'Opvolging beleid']
]])
@endsection
@section('content')
<main class="library-page">
    <div class="library-page-head">
        <div><p class="portal-eyebrow">BIBLIOTHEEK / OPVOLGING</p><h1>Niet-ingeleverde boeken: opvolging</h1>
        <p>Overzicht van uitleningen die na het ingestelde aantal verzonden herinneringen opvolging vragen. Er worden geen automatische sancties opgelegd.</p></div>
        <a href="{{ route('bibliotheek.loans.index') }}" class="library-button library-button-secondary">Terug naar uitleningen</a>
    </div>
    @if(session('success'))<div class="library-message">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="library-errors" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    @if(!$ready)
        <section class="library-form-card"><h2>Nog niet geïnstalleerd</h2><p>Voer de gerichte bibliotheekmigratie voor herinneringen en escalaties uit.</p></section>
    @else
        <section class="library-form-card">
            <div class="library-page-head"><h2>{{ $all ? 'Alle opvolgingen' : 'Openstaande opvolgingen' }}</h2>
                <a href="{{ route('bibliotheek.loans.escalations.index', $all ? [] : ['alle'=>1]) }}" class="library-button library-button-secondary">{{ $all ? 'Alleen openstaand' : 'Toon historiek' }}</a></div>
            <p class="library-help">{{ $items->total() }} {{ $all ? 'dossiers' : 'openstaande dossiers' }}.</p>
            <div class="library-table-wrap">
                <table class="library-table">
                    <thead><tr><th>Boek / exemplaar</th><th>Ontlener</th><th>Inleverdatum</th><th>Herinneringen</th><th>Status / opvolging</th></tr></thead>
                    <tbody>
                    @forelse($items as $item)
                    <tr>
                        <td><strong>{{ $item->title }}</strong><small style="display:block">{{ $item->barcode }}</small></td>
                        <td>{{ trim(($item->voornaam ?? $item->staff_voornaam ?? '').' '.($item->naam ?? $item->staff_naam ?? '')) }}</td>
                        <td>{{ $item->due_date }} @if($item->returned_at)<small style="display:block">Ingeleverd</small>@endif</td>
                        <td>{{ $item->reminder_count }}<small style="display:block">Beleidssignaal: {{ ['sent' => 'Verzonden', 'failed' => 'Mislukt', 'processing' => 'Onzeker / bezig'][$item->policy_alert_status ?? ''] ?? 'Niet verzonden' }}</small></td>
                        <td>
                            @if($item->status === 'open')
                            <form method="post" action="{{ route('bibliotheek.loans.escalations.resolve', $item->id) }}" class="library-form">
                                @csrf
                                <label>Opvolgingsnotitie <textarea name="note" rows="2" maxlength="1000" minlength="5" required></textarea></label>
                                <div class="library-form-actions">
                                    <button class="library-button library-button-primary" name="status" value="resolved">Behandeld</button>
                                    <button class="library-button library-button-secondary" name="status" value="dismissed">Geen verdere actie</button>
                                </div>
                            </form>
                            @else
                                {{ $item->status === 'resolved' ? 'Behandeld' : 'Afgesloten' }}
                                <small style="display:block">{{ $item->resolved_by }} · {{ $item->resolved_at }}</small>
                                <p>{{ $item->resolution_note }}</p>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="5">Geen opvolgingen gevonden.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @if($items->hasPages()){{ $items->links() }}@endif
        </section>
    @endif
</main>
@endsection
