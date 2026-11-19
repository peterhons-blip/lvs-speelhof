@extends('layouts.app')
@section('title', 'Personeel synchroniseren')
@section('breadcrumbs')
@include('partials.breadcrumbs', ['items' => [
    ['label' => 'Dashboard', 'url' => route('home')],
    ['label' => 'Beheer', 'url' => route('beheer.users.index')],
    ['label' => 'Personeel synchroniseren'],
]])
@endsection
@section('content')
<main class="library-page portal-users-page">
    <div class="library-page-head">
        <div>
            <p class="portal-eyebrow">BEHEER · SMARTSCHOOL</p>
            <h1>Personeel synchroniseren</h1>
            <p>Haal leerkrachten en administratieve personeelsleden inclusief subgroepen rechtstreeks uit Smartschool op.</p>
        </div>
        <a class="library-button library-button-secondary" href="{{ route('beheer.users.index') }}">Terug naar gebruikers</a>
    </div>
    @if(session('success'))<div class="library-message" role="status">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="library-errors" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <section class="library-form-card">
        <h2>Synchronisatie starten</h2>
        <p class="portal-users-card-intro">De hoofdgroep <strong>Leerkrachten Atheneum Sint-Truiden</strong> wordt recursief ingelezen als Leerkracht. De hoofdgroep <strong>Administratie Campus Speelhof</strong> wordt recursief ingelezen als Administratie. Bij beide groepslidmaatschappen krijgt Administratie voorrang.</p>
        <p>Handmatig toegekende speciale rollen worden behouden. Accounts die eerder via deze sync werden opgehaald maar niet meer in Smartschool staan, worden na een volledig geslaagde synchronisatie geblokkeerd en verborgen in de gewone gebruikerslijst. Er wordt niets verwijderd.</p>
        @if(!$ready)
            <div class="portal-sync-feedback">De synchronisatie is nog niet geïnstalleerd. Voer de migratie voor personeelssynchronisatie uit.</div>
        @else
            <form method="post" action="{{ route('beheer.staff-sync.start') }}" class="portal-sync-form">
                @csrf
                <label class="portal-sync-option">
                    <input type="checkbox" name="apply_changes" value="1">
                    <span><strong>Wijzigingen werkelijk uitvoeren</strong><small>Niet aanvinken = uitsluitend veilige preview. Aangevinkt = accounts aanmaken/bijwerken en verdwenen gesynchroniseerde accounts blokkeren.</small></span>
                </label>
                <label class="portal-sync-option">
                    <input type="checkbox" name="confirm_sync" value="1" required>
                    <span>Ik bevestig dat ik de personeelssynchronisatie wil starten.</span>
                </label>
                <div class="portal-role-actions">
                    <span class="portal-role-help">De Smartschool-wachtrijworker moet actief zijn.</span>
                    <button type="submit" class="library-button library-button-primary" @disabled($runs->contains(fn ($run) => in_array($run->status, ['waiting', 'running'], true)))>Synchronisatie aanvragen</button>
                </div>
            </form>
        @endif
    </section>
    <section class="library-form-card">
        <div class="portal-users-table-heading">
            <div><h2>Laatste synchronisaties</h2><p>Ververs om de actuele status te bekijken.</p></div>
            <a class="library-button library-button-secondary" href="{{ route('beheer.staff-sync.index') }}">Verversen</a>
        </div>
        <div class="library-table-wrap">
            <table class="library-table">
                <thead><tr><th>Gestart door</th><th>Aangevraagd</th><th>Modus</th><th>Status</th><th>Resultaat</th></tr></thead>
                <tbody>
                @forelse($runs as $run)
                    <tr>
                        <td>{{ $run->requested_by }}</td>
                        <td>{{ \Illuminate\Support\Carbon::parse($run->created_at)->timezone('Europe/Brussels')->format('d/m/Y H:i') }}</td>
                        <td>{{ $run->apply_changes ? 'Wijzigen' : 'Preview' }}</td>
                        <td>{{ ['waiting' => 'In wachtrij', 'running' => 'Bezig', 'completed' => 'Voltooid', 'failed' => 'Mislukt'][$run->status] ?? $run->status }}</td>
                        <td>{{ $run->summary ?: 'Nog geen resultaat.' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5">Nog geen synchronisaties uitgevoerd.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
</main>
@endsection
