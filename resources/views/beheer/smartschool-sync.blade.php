@extends('layouts.app')
@section('title', 'Leerlingensynchronisatie')
@section('breadcrumbs')
@include('partials.breadcrumbs', ['items' => [
    ['label' => 'Dashboard', 'url' => route('home')],
    ['label' => 'Beheer', 'url' => route('beheer.users.index')],
    ['label' => 'Leerlingensynchronisatie'],
]])
@endsection
@section('content')
<main class="library-page portal-users-page portal-student-sync-page">
    <div class="library-page-head">
        <div>
            <p class="portal-eyebrow">PORTAALBEHEER / SMARTSCHOOL</p>
            <h1>Leerlingensynchronisatie</h1>
            <p>Synchroniseer leerlingen, klassen en QR-scancodes uit Smartschool. De codes worden opgeslagen in onze eigen leerlingenlijst.</p>
        </div>
    </div>

    @if(session('success'))<div class="library-message" role="status">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="library-errors" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <div class="portal-sync-stats">
        <div class="portal-sync-stat"><span>Actieve leerlingen</span><strong>{{ number_format($pupils, 0, ',', '.') }}</strong></div>
        <div class="portal-sync-stat"><span>Leerlingen met QR-code</span><strong>{{ number_format($codes, 0, ',', '.') }}</strong></div>
        <div class="portal-sync-stat"><span>Automatische synchronisatie</span><strong>Elke nacht om 03.00 uur</strong></div>
    </div>

    <section class="library-form-card">
        <h2>Synchronisatie handmatig starten</h2>
        <p class="portal-users-card-intro">Dezelfde synchronisatie als 's nachts wordt in de wachtrij geplaatst. Ze actualiseert leerlingen, klassen, actieve status en, waar beschikbaar, de Smartschool-scancode. Tijdens de synchronisatie worden geen bibliotheekuitleningen gewijzigd.</p>
        @if(!$ready)
            <div class="library-errors" role="alert">De nieuwe migraties zijn nog niet uitgevoerd. Synchronisatie via dit scherm is daarom nog niet beschikbaar.</div>
        @else
            <form method="post" action="{{ route('beheer.smartschool-sync.start') }}" class="portal-sync-form">
                @csrf
                <label class="portal-sync-option">
                    <input type="checkbox" name="refresh_scancodes" value="1">
                    <span><strong>Ook bestaande scancodes opnieuw ophalen</strong><small>Normaal niet nodig. Vraagt mogelijk een extra Smartschool-oproep per leerling en duurt langer.</small></span>
                </label>
                <label class="portal-sync-option">
                    <input type="checkbox" name="confirm_sync" value="1" required>
                    <span>Ik bevestig dat ik de leerlingengegevens vanuit Smartschool wil synchroniseren.</span>
                </label>
                <div class="portal-role-actions">
                    <span class="portal-role-help">De afzonderlijke wachtrijworker <code>smartschool-sync</code> moet actief zijn.</span>
                    <button type="submit" class="library-button library-button-primary"
                        @disabled($runs->contains(fn ($run) => in_array($run->status, ['waiting', 'running'], true)))>
                        Synchronisatie starten
                    </button>
                </div>
            </form>
        @endif
    </section>

    <section class="library-form-card">
        <div class="portal-users-table-heading">
            <div><h2>Recente handmatige synchronisaties</h2><p>Ververs deze pagina om de voortgang te bekijken.</p></div>
            <a class="library-button library-button-secondary" href="{{ route('beheer.smartschool-sync.index') }}">Verversen</a>
        </div>
        <div class="library-table-wrap">
            <table class="library-table">
                <thead><tr><th>Gestart door</th><th>Aangevraagd</th><th>Status</th><th>Resultaat</th></tr></thead>
                <tbody>
                @forelse($runs as $run)
                    @php($statusText = [
                        'waiting' => 'In wachtrij',
                        'running' => 'Bezig',
                        'completed' => 'Voltooid',
                        'failed' => 'Mislukt',
                    ][$run->status] ?? $run->status)
                    <tr>
                        <td>{{ $run->requested_by }}</td>
                        <td>{{ \Illuminate\Support\Carbon::parse($run->created_at)->format('d/m/Y H:i') }}</td>
                        <td><span class="portal-sync-status portal-sync-status-{{ $run->status }}">{{ $statusText }}</span></td>
                        <td>{{ $run->summary ?: '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4">Nog geen handmatige synchronisaties uitgevoerd.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
</main>
@endsection
