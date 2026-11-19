@extends('layouts.app')
@section('title', 'Personeelsbeheer')
@section('breadcrumbs')
@include('partials.breadcrumbs', ['items' => [
    ['label' => 'Dashboard', 'url' => route('home')],
    ['label' => 'Beheer'],
    ['label' => 'Personeelsbeheer'],
]])
@endsection
@section('content')
<main class="library-page portal-users-page">
    <div class="library-page-head">
        <div>
            <p class="portal-eyebrow">PORTAALBEHEER</p>
            <h1>Personeelsbeheer</h1>
            <p>De medewerkers komen rechtstreeks uit de bestaande LVS-tabellen <strong>users</strong> en <strong>groups</strong>. Er worden geen dubbele gebruikers aangemaakt.</p>
        </div>
        <div class="portal-role-actions"><a class="library-button library-button-secondary" href="{{ route('beheer.staff-sync.index') }}">Personeel synchroniseren</a><a class="library-button library-button-secondary" href="{{ route('beheer.permissions.index') }}">Rollen en rechten</a></div>
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

    <section class="library-form-card" data-live-management>
        <div class="portal-users-table-heading"><div><h2>Personeelsaccounts</h2><p>Gesorteerd per schoolrol en vervolgens alfabetisch op familienaam.</p></div><span class="portal-users-total" data-management-total>{{ $users->total() }} medewerkers</span></div>
        <nav class="portal-users-account-filters" aria-label="Accounts filteren" style="display:flex;flex-wrap:wrap;gap:0.75rem;margin:1rem 0 1.25rem;">
            <a href="{{ route('beheer.users.index', request()->filled('zoek') ? ['zoek' => request('zoek')] : []) }}" class="library-button {{ request()->boolean('toon_geblokkeerd') ? 'library-button-secondary' : 'library-button-primary' }}" @if(!request()->boolean('toon_geblokkeerd')) aria-current="page" @endif>Actieve accounts</a>
            <a href="{{ route('beheer.users.index', array_merge(['toon_geblokkeerd' => 1], request()->filled('zoek') ? ['zoek' => request('zoek')] : [])) }}" class="library-button {{ request()->boolean('toon_geblokkeerd') ? 'library-button-primary' : 'library-button-secondary' }}" @if(request()->boolean('toon_geblokkeerd')) aria-current="page" @endif>Alle accounts</a>
        </nav>
        <form method="get" action="{{ route('beheer.users.index') }}" class="portal-users-search-form" style="display:flex;align-items:end;gap:0.75rem;flex-wrap:wrap;margin-bottom:1rem;">
            @if(request()->boolean('toon_geblokkeerd'))<input type="hidden" name="toon_geblokkeerd" value="1">@endif
            <div class="library-field" style="flex:1 1 250px;margin:0;">
                <label for="personeel-zoeken">Zoek medewerker of groep</label>
                <input id="personeel-zoeken" data-live-search type="search" name="zoek" maxlength="100" value="{{ request('zoek') }}" placeholder="Naam, Smartschool-account of groep...">
            </div>
            <button type="submit" class="library-button library-button-primary">Zoeken</button>
            @if(request()->filled('zoek'))
                <a class="library-button library-button-secondary" href="{{ route('beheer.users.index', request()->boolean('toon_geblokkeerd') ? ['toon_geblokkeerd' => 1] : []) }}">Wissen</a>
            @endif
        </form>
        <form method="post" action="{{ route('beheer.users.bulk') }}" data-bulk-form id="staff-bulk-form" class="portal-bulk-panel" style="border:1px solid #cbd5e1;border-radius:14px;background:#f8fafc;padding:1.25rem;margin:1.25rem 0;">
            @csrf
            <div style="display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap;margin-bottom:1rem;">
                <div>
                    <h3 style="margin:0 0 0.25rem;font-size:1.1rem;">Meerdere medewerkers aanpassen</h3>
                    <p style="margin:0;color:#64748b;">Vink hieronder de medewerkers aan die je samen wilt aanpassen.</p>
                </div>
                <strong data-selected-count style="white-space:nowrap;">0 geselecteerd (deze pagina)</strong>
            </div>
            <div style="display:flex;align-items:end;gap:1rem;flex-wrap:wrap;">
                <div class="library-field" style="margin:0;flex:1 1 220px;">
                    <label for="staff-bulk-action">1. Welke wijziging?</label>
                    <select id="staff-bulk-action" data-bulk-action name="action" required>
                        <option value="">Selecteer een actie...</option>
                        <option value="role">Schoolrol aanpassen</option>
                        <option value="allow">Toegang tot LVS toestaan</option>
                        <option value="block">Toegang tot LVS blokkeren</option>
                    </select>
                </div>
                <div class="library-field" data-bulk-role style="margin:0;flex:1 1 220px;display:none;">
                    <label for="staff-bulk-role">2. Welke nieuwe schoolrol?</label>
                    <select id="staff-bulk-role" name="role" disabled>
                        <option value="">Selecteer de nieuwe rol...</option>
                        @foreach($roles as $slug => $label)
                            <option value="{{ $slug }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="library-button library-button-primary" data-bulk-submit disabled>Wijziging uitvoeren</button>
            </div>
            <p data-bulk-summary role="status" aria-live="polite" style="margin:1rem 0 0;color:#475569;">Selecteer medewerkers en kies een actie. Er wordt nog niets gewijzigd.</p>
        </form>
        <div data-management-results>
        <div class="library-table-wrap">
            <table class="library-table">
                <thead><tr><th><input type="checkbox" data-select-all aria-label="Selecteer iedereen op deze pagina"></th><th>Medewerker</th><th>Smartschool-account</th><th>Huidige groep</th><th>Nieuwe rol</th><th>Aanmelden</th></tr></thead>
                <tbody>
                @forelse($users as $user)
                    @php($current = array_search($user->groupname, \App\Services\LibraryAccess::ROLES, true))
                    <tr>
                        <td><input type="checkbox" data-bulk-id form="staff-bulk-form" name="ids[]" value="{{ $user->id }}" aria-label="Selecteer {{ $user->voornaam }} {{ $user->name }}"></td>
                        <td><strong>{{ trim(($user->voornaam ?? '').' '.($user->name ?? '')) ?: '—' }}</strong>
                            @if(!(int) $user->allowed_login)<div class="library-help">Aanmelding geblokkeerd</div>@endif
                        </td>
                        <td><strong>{{ $user->smartschool_gebruikersnaam ?: $user->gebruikersnaam }}</strong></td>
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
                    <tr><td colspan="6">Geen personeelsaccounts gevonden.</td></tr>
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
        </div>
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
                meta.textContent = (person.smartschool_gebruikersnaam || person.gebruikersnaam) + (person.groupname ? ' · ' + person.groupname : '');
                button.append(name, meta);
                button.addEventListener('click', () => {
                    input.value = person.smartschool_gebruikersnaam || person.gebruikersnaam;
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
<script src="{{ asset('js/management-live.js') }}?v=1" defer></script>
@endpush
