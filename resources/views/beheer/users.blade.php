@extends('layouts.app')
@section('title', 'Gebruikersbeheer')
@section('breadcrumbs')
@include('partials.breadcrumbs', ['items' => [
    ['label' => 'Dashboard', 'url' => route('home')],
    ['label' => 'Beheer'],
    ['label' => 'Gebruikersbeheer'],
]])
@endsection
@section('content')
<main class="library-page portal-users-page">
    <div class="library-page-head">
        <div>
            <p class="portal-eyebrow">PORTAALBEHEER</p>
            <h1>Gebruikersbeheer</h1>
            <p>De medewerkers komen rechtstreeks uit de bestaande LVS-tabellen <strong>users</strong> en <strong>groups</strong>. Er worden geen dubbele gebruikers aangemaakt.</p>
        </div>
        <a class="library-button library-button-secondary" href="{{ route('beheer.permissions.index') }}">Rollen en rechten</a>
    </div>
    @if(session('success'))<div class="library-message" role="status">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="library-errors" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <section class="library-form-card">
        <h2>Schoolrol toekennen</h2>
        <p class="portal-users-card-intro">Zoek een bestaand personeelsaccount via de Smartschool-gebruikersnaam en kies de gewenste schoolrol.</p>
        <form action="{{ route('beheer.users.store') }}" method="post" class="portal-users-assign-form">
            @csrf
            <div class="library-field">
                <label for="smartschool_username">Bestaande Smartschool-gebruikersnaam</label>
                <input id="smartschool_username" name="smartschool_username" value="{{ old('smartschool_username') }}" required maxlength="120" placeholder="bv. voornaam.naam">
            </div>
            <div class="library-field">
                <label for="role">Schoolrol</label>
                <select id="role" name="role">
                    @foreach($roles as $slug => $label)
                        <option value="{{ $slug }}" @selected(old('role') === $slug)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="portal-users-assign-action"><button class="library-button library-button-primary" type="submit">Rol toekennen</button></div>
        </form>
        <div class="portal-users-note"><span aria-hidden="true">i</span><p>Je kent enkel een rol toe aan een <strong>bestaand personeelsaccount</strong>. De gebruikersnaam en de huidige aanmeldrechten blijven behouden.</p></div>
    </section>

    <section class="library-form-card">
        <div class="portal-users-table-heading"><div><h2>Personeelsaccounts</h2><p>Gesorteerd per schoolrol en vervolgens alfabetisch op familienaam.</p></div><span class="portal-users-total">{{ $users->total() }} medewerkers</span></div>
        <div class="library-table-wrap">
            <table class="library-table">
                <thead><tr><th>Medewerker</th><th>Smartschool-account</th><th>Huidige groep</th><th>Nieuwe rol</th></tr></thead>
                <tbody>
                @forelse($users as $user)
                    @php($current = array_search($user->groupname, \App\Services\LibraryAccess::ROLES, true))
                    <tr>
                        <td><strong>{{ trim(($user->voornaam ?? '').' '.($user->name ?? '')) ?: '—' }}</strong>
                            @if(!(int) $user->allowed_login)<div class="library-help">Login niet toegestaan</div>@endif
                        </td>
                        <td><strong>{{ $user->gebruikersnaam }}</strong></td>
                        <td>{{ $user->groupname ?: 'Geen groep' }}</td>
                        <td>
                            <form action="{{ route('beheer.users.update', $user->id) }}" method="post" class="portal-user-row portal-user-role-form">
                                @csrf
                                @method('PUT')
                                <select name="role" aria-label="Rol voor {{ $user->gebruikersnaam }}" required>
                                    @foreach($roles as $slug => $label)
                                        <option value="{{ $slug }}" @selected($current === $slug)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                <button type="submit" class="library-button library-button-secondary">Opslaan</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4">Geen personeelsaccounts gevonden.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="portal-users-pagination">{{ $users->links() }}</div>
    </section>
</main>
@endsection
