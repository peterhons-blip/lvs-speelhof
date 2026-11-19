@extends('layouts.app')
@section('title','Gebruikersbeheer')
@section('breadcrumbs')
@include('partials.breadcrumbs', ['items' => [['label'=>'Dashboard','url'=>route('home')],['label'=>'Beheer'],['label'=>'Gebruikersbeheer']]])
@endsection
@section('content')
<main class="library-page">
    <div class="library-page-head">
        <div><p class="portal-eyebrow">PORTAALBEHEER</p><h1>Gebruikersbeheer</h1>
        <p>Koppel Smartschool-gebruikersnamen aan een schoolrol. De toegangsrechten van iedere rol stel je apart in.</p></div>
        <a class="library-button library-button-secondary" href="{{ route('beheer.permissions.index') }}">Rechten per rol</a>
    </div>
    @if(session('success'))<div class="library-message" role="status">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="library-errors" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <section class="library-form-card">
        <h2>Medewerker toevoegen</h2>
        <form method="post" action="{{ route('beheer.users.store') }}" class="library-form-grid">
            @csrf
            <div class="library-field"><label for="smartschool_username">Smartschool-gebruikersnaam</label><input id="smartschool_username" name="smartschool_username" required maxlength="120" value="{{ old('smartschool_username') }}"></div>
            <div class="library-field"><label for="display_name">Naam (optioneel)</label><input id="display_name" name="display_name" maxlength="160" value="{{ old('display_name') }}"></div>
            <div class="library-field"><label for="role">Rol</label><select id="role" name="role">@foreach($roles as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></div>
            <div class="library-wide"><button class="library-button library-button-primary" type="submit">Gebruiker toevoegen</button></div>
        </form>
        <p class="library-help">De Smartschool-gebruikersnaam moet exact overeenkomen met het account waarmee de medewerker zich aanmeldt.</p>
    </section>
    <section class="library-form-card">
        <h2>Geregistreerde gebruikers ({{ $users->count() }})</h2>
        <div class="library-table-wrap">
            <table class="library-table"><thead><tr><th>Gebruikersnaam</th><th>Naam</th><th>Rol</th><th>Toegang</th><th>Wijzigen</th></tr></thead><tbody>
            @forelse($users as $user)
                <tr>
                    <td><strong>{{ $user->smartschool_username }}</strong></td>
                    <td colspan="4">
                        <form method="post" action="{{ route('beheer.users.update', $user->id) }}" class="portal-user-row">
                            @csrf @method('PUT')
                            <input name="display_name" value="{{ $user->display_name }}" maxlength="160" placeholder="Naam" aria-label="Naam">
                            <select name="role" aria-label="Rol">
                                @foreach($roles as $key => $label)<option value="{{ $key }}" @selected($user->role === $key)>{{ $label }}</option>@endforeach
                            </select>
                            <select name="is_active" aria-label="Account actief">
                                <option value="1" @selected($user->is_active)>Actief</option>
                                <option value="0" @selected(!$user->is_active)>Geblokkeerd</option>
                            </select>
                            <button type="submit" class="library-button library-button-secondary">Opslaan</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5">Nog geen medewerkers toegevoegd.</td></tr>
            @endforelse
            </tbody></table>
        </div>
    </section>
</main>
@endsection
