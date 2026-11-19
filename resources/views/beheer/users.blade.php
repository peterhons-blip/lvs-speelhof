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
                <div class="portal-user-autocomplete">
                    <input id="smartschool_username" name="smartschool_username" value="{{ old('smartschool_username') }}" required maxlength="120" placeholder="Typ naam of gebruikersnaam..." autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="portal-user-suggestions">
                    <div id="portal-user-suggestions" class="portal-user-suggestions" role="listbox" hidden></div>
                </div>
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
                <thead><tr><th>Medewerker</th><th>Smartschool-account</th><th>Huidige groep</th><th>Nieuwe rol</th><th>Aanmelden</th></tr></thead>
                <tbody>
                @forelse($users as $user)
                    @php($current = array_search($user->groupname, \App\Services\LibraryAccess::ROLES, true))
                    <tr>
                        <td><strong>{{ trim(($user->voornaam ?? '').' '.($user->name ?? '')) ?: '—' }}</strong>
                            @if(!(int) $user->allowed_login)<div class="library-help">Aanmelding geblokkeerd</div>@endif
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
                        <td>
                            <form action="{{ route('beheer.users.login', $user->id) }}" method="post" class="portal-login-toggle-form" @if((int) $user->allowed_login) onsubmit="return confirm('Aanmelding blokkeren voor {{ $user->gebruikersnaam }}?');" @endif>
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="allowed_login" value="{{ (int) $user->allowed_login ? '0' : '1' }}">
                                <span class="portal-login-status {{ (int) $user->allowed_login ? 'portal-login-status-yes' : 'portal-login-status-no' }}">{{ (int) $user->allowed_login ? 'Toegang actief' : 'Geblokkeerd' }}</span>
                                <button type="submit" class="library-button library-button-secondary">{{ (int) $user->allowed_login ? 'Blokkeren' : 'Toestaan' }}</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5">Geen personeelsaccounts gevonden.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <nav class="portal-users-pagination" aria-label="Pagina's met medewerkers">
            <span class="portal-pagination-summary">
                Medewerkers {{ $users->firstItem() ?? 0 }}–{{ $users->lastItem() ?? 0 }} van {{ $users->total() }}
            </span>
            @if($users->hasPages())
                <div class="portal-pagination-controls">
                    @if($users->onFirstPage())
                        <span class="portal-page-link is-disabled" aria-disabled="true">‹ Vorige</span>
                    @else
                        <a class="portal-page-link" href="{{ $users->previousPageUrl() }}" rel="prev">‹ Vorige</a>
                    @endif
                    @foreach($users->getUrlRange(max(1, $users->currentPage() - 2), min($users->lastPage(), $users->currentPage() + 2)) as $page => $url)
                        @if($page === $users->currentPage())
                            <span class="portal-page-link is-current" aria-current="page">{{ $page }}</span>
                        @else
                            <a class="portal-page-link" href="{{ $url }}" aria-label="Pagina {{ $page }}">{{ $page }}</a>
                        @endif
                    @endforeach
                    @if($users->hasMorePages())
                        <a class="portal-page-link" href="{{ $users->nextPageUrl() }}" rel="next">Volgende ›</a>
                    @else
                        <span class="portal-page-link is-disabled" aria-disabled="true">Volgende ›</span>
                    @endif
                </div>
            @endif
        </nav>
    </section>
</main>
@endsection

@push('scripts')
<script>
(() => {
    const input = document.getElementById('smartschool_username');
    const menu = document.getElementById('portal-user-suggestions');
    const url = @json(route('beheer.users.search'));
    if (!input || !menu) return;

    let sequence = 0;
    let controller = null;

    const close = () => {
        menu.replaceChildren();
        menu.hidden = true;
        input.setAttribute('aria-expanded', 'false');
    };
    const show = () => {
        menu.hidden = false;
        input.setAttribute('aria-expanded', 'true');
    };
    const lookup = async () => {
        const term = input.value.trim();
        const current = ++sequence;
        if (controller) controller.abort();
        controller = null;
        close();
        if (term.length < 2) return;
        controller = new AbortController();
        try {
            const response = await fetch(url + '?q=' + encodeURIComponent(term), {
                headers: {Accept: 'application/json'},
                signal: controller.signal
            });
            if (!response.ok) throw new Error('Zoeken mislukt');
            const items = await response.json();
            if (current !== sequence || input.value.trim() !== term) return;
            if (!items.length) {
                const empty = document.createElement('div');
                empty.className = 'portal-user-suggestion-empty';
                empty.textContent = 'Geen bestaande medewerkers gevonden.';
                menu.appendChild(empty);
                show();
                return;
            }
            for (const person of items) {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'portal-user-suggestion';
                button.setAttribute('role', 'option');
                const name = document.createElement('span');
                name.className = 'portal-user-suggestion-name';
                name.textContent = [person.voornaam, person.name].filter(Boolean).join(' ') || person.gebruikersnaam;
                const meta = document.createElement('small');
                meta.textContent = person.gebruikersnaam + (person.groupname ? ' · ' + person.groupname : '');
                button.append(name, meta);
                button.addEventListener('click', () => {
                    input.value = person.gebruikersnaam;
                    input.focus();
                    ++sequence;
                    if (controller) controller.abort();
                    close();
                });
                menu.appendChild(button);
            }
            show();
        } catch (e) {
            if (e.name !== 'AbortError' && current === sequence) close();
        }
    };
    input.addEventListener('input', lookup);
    input.addEventListener('focus', () => { if (input.value.trim().length >= 2) lookup(); });
    input.addEventListener('keydown', e => {
        if (e.key === 'Escape') close();
        if (e.key === 'ArrowDown' && !menu.hidden) {
            const first = menu.querySelector('button');
            if (first) { e.preventDefault(); first.focus(); }
        }
    });
    menu.addEventListener('keydown', e => {
        if (e.key === 'Escape') { close(); input.focus(); }
        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
            const buttons = [...menu.querySelectorAll('button')];
            const index = buttons.indexOf(document.activeElement);
            const next = buttons[index + (e.key === 'ArrowDown' ? 1 : -1)];
            if (next) { e.preventDefault(); next.focus(); }
        }
    });
    document.addEventListener('click', e => {
        if (!input.contains(e.target) && !menu.contains(e.target)) close();
    });
})();
</script>
@endpush
