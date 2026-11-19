@extends('layouts.app')
@section('title', 'Smartschool-testbericht')
@section('breadcrumbs')
@include('partials.breadcrumbs', ['items' => [
    ['label' => 'Dashboard', 'url' => route('home')],
    ['label' => 'Beheer', 'url' => route('beheer.users.index')],
    ['label' => 'Smartschool-testbericht'],
]])
@endsection
@section('content')
<main class="library-page portal-users-page portal-test-message-page">
    <div class="library-page-head">
        <div>
            <p class="portal-eyebrow">PORTAALBEHEER / SMARTSCHOOL</p>
            <h1>Smartschool-testbericht</h1>
            <p>Test rechtstreeks de bestaande Smartschool-webservice met een afzender, ontvanger en eigen bericht.</p>
        </div>
        <a href="{{ route('beheer.users.index') }}" class="library-button library-button-secondary">Terug naar beheer</a>
    </div>
    @if(session('success'))<div class="library-message" role="status">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="library-errors" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <section class="library-form-card">
        <div class="portal-users-note portal-test-message-warning">
            <span aria-hidden="true">!</span>
            <p><strong>Dit verstuurt een echt Smartschool-bericht.</strong> Gebruik eerst je eigen account als ontvanger. Het gekozen afzenderaccount moet in Smartschool bestaan en door de webservice gebruikt mogen worden.</p>
        </div>

        <form method="post" action="{{ route('beheer.smartschool-test.send') }}" class="portal-test-message-form" autocomplete="off">
            @csrf
            <div class="portal-test-message-two-col">
                <div class="library-field">
                    <label for="sender">Van (Smartschool-account) *</label>
                    <select id="sender" name="sender" required>
                        <option value="bib" @selected(old('sender', $sender) === 'bib')>BIB — Schoolbibliotheek</option>
                        <option value="lvs" @selected(old('sender', $sender) === 'lvs')>LVS — Atheneum Portaal</option>
                    </select>
                    <p class="library-help">Enkel de vaste afzenders <strong>bib</strong> en <strong>lvs</strong> zijn toegelaten.</p>
                </div>
                <div class="library-field">
                    <label for="recipient">Aan (leerling of personeelslid) *</label>
                    <div class="portal-user-autocomplete portal-message-recipient-search">
                        <input id="recipient-search" type="search" value="" placeholder="Zoek op naam of Smartschool-gebruikersnaam..." autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="portal-message-recipient-results" aria-label="Zoek ontvanger">
                        <div id="portal-message-recipient-results" class="portal-user-suggestions" role="listbox" hidden></div>
                    </div>
                    <div class="portal-message-recipient-selection">
                        <span>Gekozen ontvanger</span>
                        <input id="recipient" name="recipient" value="{{ old('recipient', $recipient) }}" maxlength="120" required readonly aria-label="Smartschool-gebruikersnaam ontvanger">
                    </div>
                    <p class="library-help">Zoek een actieve leerling of medewerker en selecteer deze. Test eerst bij voorkeur met jezelf.</p>
                </div>
            </div>
            <div class="library-field">
                <label for="subject">Onderwerp *</label>
                <input id="subject" name="subject" value="{{ old('subject', 'Testbericht vanuit Atheneum Portaal') }}" maxlength="200" required>
            </div>
            <div class="library-field">
                <label for="message">Berichttekst *</label>
                <textarea id="message" name="message" rows="8" maxlength="5000" required placeholder="Typ hier de tekst die je in Smartschool wilt versturen.">{{ old('message', "Beste,\n\nDit is een testbericht vanuit het Atheneum Portaal.\n\nMet vriendelijke groeten\nBibliotheek") }}</textarea>
                <p class="library-help">Regelafbrekingen worden behouden. Er worden geen bijlagen of co-accountberichten verstuurd.</p>
            </div>
            <div class="portal-test-message-consent-row">
                <label class="portal-test-message-consent" for="confirm_send">
                    <input id="confirm_send" type="checkbox" name="confirm_send" value="1" required @checked(old('confirm_send') == '1')>
                    <span>Ik bevestig dat ik een <strong>echt Smartschool-bericht</strong> naar deze ontvanger wil versturen.</span>
                </label>
            </div>
            <div class="portal-role-actions">
                <span class="portal-role-help">Alleen voor ICT-coördinatoren. Geen automatische verzending.</span>
                <button type="submit" class="library-button library-button-primary">Testbericht verzenden</button>
            </div>
        </form>
    </section>
</main>
@endsection

@push('scripts')
<script>
(() => {
    const search = document.getElementById('recipient-search');
    const results = document.getElementById('portal-message-recipient-results');
    const recipient = document.getElementById('recipient');
    const endpoint = @json(route('beheer.smartschool-test.recipients'));
    if (!search || !results || !recipient) return;
    let requestId = 0;
    let pending = null;

    const close = () => {
        results.replaceChildren();
        results.hidden = true;
        search.setAttribute('aria-expanded', 'false');
    };

    const lookup = async () => {
        const term = search.value.trim();
        const id = ++requestId;
        if (pending) pending.abort();
        pending = null;
        close();
        if (term.length < 2) return;
        pending = new AbortController();
        try {
            const response = await fetch(endpoint + '?q=' + encodeURIComponent(term), {
                headers: {Accept: 'application/json'},
                signal: pending.signal,
            });
            if (!response.ok) throw Error('Zoeken mislukt');
            const list = await response.json();
            if (id !== requestId || term !== search.value.trim()) return;
            if (!list.length) {
                const empty = document.createElement('div');
                empty.className = 'portal-user-suggestion-empty';
                empty.textContent = 'Geen actieve leerlingen of personeelsleden gevonden.';
                results.appendChild(empty);
            }
            for (const person of list) {
                const item = document.createElement('button');
                item.type = 'button';
                item.className = 'portal-user-suggestion';
                item.setAttribute('role', 'option');
                const name = document.createElement('span');
                name.className = 'portal-user-suggestion-name';
                name.textContent = person.name || person.username;
                const meta = document.createElement('small');
                meta.textContent = person.type + ' · ' + person.username;
                item.append(name, meta);
                item.addEventListener('click', () => {
                    recipient.value = person.username;
                    search.value = (person.name || person.username) + ' (' + person.type + ')';
                    ++requestId;
                    if (pending) pending.abort();
                    close();
                });
                results.appendChild(item);
            }
            results.hidden = false;
            search.setAttribute('aria-expanded', 'true');
        } catch (error) {
            if (error.name !== 'AbortError' && id === requestId) close();
        }
    };

    search.addEventListener('input', () => {
        // Prevent accidentally sending to a previously selected recipient
        // after a new name was entered.
        recipient.value = '';
        lookup();
    });
    search.addEventListener('focus', () => { if (search.value.trim().length >= 2) lookup(); });
    search.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') close();
        if (event.key === 'ArrowDown' && !results.hidden) {
            const first = results.querySelector('button');
            if (first) { event.preventDefault(); first.focus(); }
        }
    });
    results.addEventListener('keydown', event => {
        if (event.key === 'Escape') { close(); search.focus(); }
        if (event.key !== 'ArrowDown' && event.key !== 'ArrowUp') return;
        const buttons = [...results.querySelectorAll('button')];
        const index = buttons.indexOf(document.activeElement);
        const next = buttons[index + (event.key === 'ArrowDown' ? 1 : -1)];
        if (next) { event.preventDefault(); next.focus(); }
    });
    document.addEventListener('click', event => {
        if (!search.contains(event.target) && !results.contains(event.target)) close();
    });
})();
</script>
@endpush
