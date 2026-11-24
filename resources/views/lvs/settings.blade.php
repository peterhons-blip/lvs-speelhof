@extends('layouts.app')
@section('title', 'LVS · Instellingen')
@section('breadcrumbs')
@include('partials.breadcrumbs', ['items' => [
    ['label' => 'Dashboard', 'url' => route('home')],
    ['label' => 'LVS', 'url' => route('lvs.dashboard')],
    ['label' => 'Instellingen'],
]])
@endsection
@push('scripts')
<script>
document.querySelectorAll('.lvs-staff-search').forEach(search => {
    const select = document.getElementById(search.dataset.target);
    if (!select) return;
    const options = Array.from(select.options).map(option => ({
        value: option.value, text: option.textContent.trim(),
        disabled: option.disabled,
    }));
    const keepSelection = () => select.value;
    search.addEventListener('input', () => {
        const current = keepSelection();
        const term = search.value.trim().toLocaleLowerCase('nl-BE');
        const filtered = options.filter(option =>
            !option.value || option.value === current ||
            option.text.toLocaleLowerCase('nl-BE').includes(term));
        select.replaceChildren(...filtered.map(option => {
            const item = new Option(option.text, option.value,
                false, option.value === current);
            item.disabled = option.disabled;
            return item;
        }));
    });
});
</script>
@endpush
@section('content')
<main class="library-page lvs-settings-page">
    <div class="library-page-head">
        <div><p class="portal-eyebrow">LEERLINGVOLGSYSTEEM / BEHEER</p><h1>LVS-instellingen</h1>
        <p>Stel in wanneer het aantal gedragsmeldingen aanleiding geeft tot opvolging of een Smartschool-bericht.</p></div>
        <a class="library-button library-button-secondary" href="{{ route('lvs.dashboard') }}">LVS-dashboard</a>
    </div>
    @if(session('success'))<div class="library-message" role="status">{{ session('success') }}</div>@endif
    @if($errors->any())
        <div class="library-errors" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    @unless($ready)
        <section class="library-form-card">
            <h2>Opvolgregels nog niet geïnstalleerd</h2>
            <p>Voer eerst de gerichte databankmigratie <code>2026_11_24_182000_create_lvs_followup_rules.php</code> uit.</p>
        </section>
    @else
        <section class="library-form-card">
            <h2>Hoe werken de opvolgregels?</h2>
            <p>Een regel wordt uitgevoerd wanneer een leerling voor de <strong>exact ingestelde keer</strong> een melding krijgt, binnen de gekozen categorie/soort en periode.
            Je kunt bijvoorbeeld een regel maken voor de vierde melding en een andere voor de achtste.
            De telling is gebaseerd op <strong>registratiedatum</strong>, niet op de datum waarop het voorval gebeurde.</p>
            <p><strong>Veilige werking:</strong> nieuwe regels zijn standaard uitgeschakeld. Bij een voorstel tot sanctie wordt alleen een dossier voor handmatige opvolging aangemaakt; er wordt geen sanctie opgelegd.
            Smartschool-berichten worden uitsluitend automatisch aangeboden bij geactiveerde regels met expliciet bevestigde ontvangers en tekst. De omschrijving van het voorval wordt niet automatisch meegezonden.</p>
            <p class="library-help">Ouder-/voogdberichten worden afzonderlijk aan co-accounts 1 t/m 6 aangeboden. Een account hoeft niet te bestaan of actief te zijn; controleer de verzendstatus en de privacy-/toestemmingssituatie vóór activeren. Voor leerlingbegeleiding en klastitularis kies je hieronder een actief personeelslid uit een doorzoekbare lijst. Een gekozen klastitularis is voorlopig een vaste ontvanger voor de hele regel, niet automatisch de klastitularis van iedere leerling.</p>
        </section>

        <section class="library-form-card">
            <h2>Ingestelde regels ({{ $rules->count() }})</h2>
            @forelse($rules as $rule)
                <div class="lvs-rule-summary">
                    <div><strong>{{ $rule->title }}</strong>
                        <small>Bij melding {{ $rule->threshold }} · {{ $rule->window_days ? 'Laatste '.$rule->window_days.' dagen' : 'Alle geregistreerde meldingen' }} ·
                            {{ ['review'=>'Opvolging leerlingbegeleiding','sanction_proposal'=>'Sanctievoorstel','smartschool'=>'Smartschool-bericht'][$rule->action] ?? $rule->action }}</small>
                    </div>
                    <div class="lvs-rule-summary-actions">
                        <span class="lvs-rule-state {{ $rule->enabled ? 'is-on' : 'is-off' }}">{{ $rule->enabled ? 'Actief' : 'Uitgeschakeld' }}</span>
                        @if($rule->enabled)
                            <form method="post" action="{{ route('lvs.settings.toggle', $rule->id) }}">
                                @csrf <input type="hidden" name="enabled" value="0">
                                <button class="library-button library-button-secondary" type="submit">Uitschakelen</button>
                            </form>
                        @elseif($rule->action !== 'smartschool')
                            <form method="post" action="{{ route('lvs.settings.toggle', $rule->id) }}">
                                @csrf <input type="hidden" name="enabled" value="1">
                                <button class="library-button library-button-secondary" type="submit">Inschakelen</button>
                            </form>
                        @endif
                        <a href="#lvs-rule-{{ $rule->id }}" class="library-button library-button-secondary">Bewerken</a>
                    </div>
                </div>
            @empty
                <p>Nog geen regels. Maak hieronder bijvoorbeeld een opvolgregel aan voor de vierde melding.</p>
            @endforelse
        </section>

        @foreach(collect([null])->concat($rules) as $rule)
            @php($isNew = $rule === null)
            <details class="library-form-card lvs-rule-editor" id="{{ $isNew ? 'lvs-new-rule' : 'lvs-rule-'.$rule->id }}" @if($isNew) open @endif>
                <summary><strong>{{ $isNew ? '+ Nieuwe opvolgregel' : 'Regel bewerken: '.$rule->title }}</strong></summary>
                <form action="{{ route('lvs.settings.save') }}" method="post" class="lvs-rule-form">
                    @csrf
                    @unless($isNew)<input type="hidden" name="id" value="{{ $rule->id }}">@endunless
                    <div class="lvs-rule-fields">
                        <div class="library-field lvs-rule-title"><label for="title-{{ $rule->id ?? 'new' }}">Naam regel</label>
                            <input id="title-{{ $rule->id ?? 'new' }}" name="title" maxlength="160" required value="{{ $rule->title ?? '' }}" placeholder="Bijvoorbeeld: 4e melding storend gedrag"></div>
                        <div class="library-field"><label for="enabled-{{ $rule->id ?? 'new' }}">Status</label>
                            <select id="enabled-{{ $rule->id ?? 'new' }}" name="enabled">
                                <option value="0" @selected(!$rule || !$rule->enabled)>Uitgeschakeld</option>
                                <option value="1" @selected($rule && $rule->enabled)>Actief</option>
                            </select></div>
                        <div class="library-field"><label for="threshold-{{ $rule->id ?? 'new' }}">Vanaf welke melding?</label>
                            <input type="number" min="1" max="500" id="threshold-{{ $rule->id ?? 'new' }}" name="threshold" value="{{ $rule->threshold ?? 4 }}" required></div>
                        <div class="library-field"><label for="window-{{ $rule->id ?? 'new' }}">Periode (dagen)</label>
                            <input type="number" min="0" max="365" id="window-{{ $rule->id ?? 'new' }}" name="window_days" value="{{ $rule->window_days ?? 0 }}" required>
                            <small>0 = alle meldingen van deze leerling</small></div>
                        <div class="library-field"><label for="category-{{ $rule->id ?? 'new' }}">Categorie</label>
                            <select id="category-{{ $rule->id ?? 'new' }}" name="category_id">
                                <option value="">Alle categorieën</option>
                                @foreach($categories as $category)
                                    <option value="{{ $category->id }}" @selected($rule && (int)$rule->category_id === (int)$category->id)>{{ $category->naam }}</option>
                                @endforeach
                            </select></div>
                        <div class="library-field"><label for="type-{{ $rule->id ?? 'new' }}">Soort</label>
                            <select id="type-{{ $rule->id ?? 'new' }}" name="type_id">
                                <option value="">Alle soorten binnen gekozen categorie</option>
                                @foreach($types as $type)
                                    <option value="{{ $type->id }}" @selected($rule && (int)$rule->type_id === (int)$type->id)>{{ $type->naam }}</option>
                                @endforeach
                            </select></div>
                        <div class="library-field"><label for="action-{{ $rule->id ?? 'new' }}">Wat moet gebeuren?</label>
                            <select id="action-{{ $rule->id ?? 'new' }}" name="action">
                                <option value="review" @selected(!$rule || $rule->action === 'review')>Opvolging leerlingbegeleiding registreren</option>
                                <option value="sanction_proposal" @selected($rule && $rule->action === 'sanction_proposal')>Sanctievoorstel ter beoordeling registreren</option>
                                <option value="smartschool" @selected($rule && $rule->action === 'smartschool')>Smartschool-bericht verzenden</option>
                            </select></div>
                    </div>
                    <fieldset class="lvs-rule-recipients">
                        <legend>Smartschool-ontvangers (alleen bij actie Smartschool)</legend>
                        <div class="lvs-rule-checks">
                            @foreach([
                                'recipient_student'=>'Leerling',
                                'recipient_parents'=>'Ouders/voogden (co-accounts)',
                                'recipient_counselor'=>'Leerlingbegeleiding',
                                'recipient_mentor'=>'Klastitularis',
                            ] as $key => $caption)
                                <label><input type="checkbox" name="{{ $key }}" value="1" @checked($rule && $rule->$key)>{{ $caption }}</label>
                            @endforeach
                        </div>
                        <div class="lvs-rule-fields">
                            @foreach([
                                'counselor_username' => 'Ontvanger leerlingbegeleiding',
                                'mentor_username' => 'Ontvanger klastitularis',
                            ] as $field => $caption)
                                @php($fieldId = $field.'-'.($rule->id ?? 'new'))
                                @php($selectedUsername = $rule ? (string) ($rule->$field ?? '') : '')
                                <div class="library-field lvs-staff-chooser">
                                    <label for="{{ $fieldId }}">{{ $caption }}</label>
                                    <input type="search" class="lvs-staff-search" data-target="{{ $fieldId }}"
                                        placeholder="Zoek personeelslid op naam of gebruikersnaam" autocomplete="off"
                                        aria-label="Zoek {{ strtolower($caption) }}">
                                    <select id="{{ $fieldId }}" name="{{ $field }}">
                                        <option value="">Selecteer een personeelslid</option>
                                        @if($selectedUsername !== '' && !$staffOptions->contains('username', $selectedUsername))
                                            <option value="{{ $selectedUsername }}" selected disabled>
                                                {{ $selectedUsername }} (niet meer actief: kies een ander personeelslid)
                                            </option>
                                        @endif
                                        @foreach($staffOptions as $staff)
                                            <option value="{{ $staff->username }}" @selected($selectedUsername === $staff->username)>
                                                {{ $staff->label }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <small>Deze vaste ontvanger krijgt het bericht voor elke leerling die deze regel activeert.
                                        Voor de echte klastitularis per leerling is later een koppeling met de les- of klasgegevens nodig.</small>
                                </div>
                            @endforeach
                            <div class="library-field"><label>Smartschool-afzender</label>
                                <input name="sender_username" maxlength="120" value="{{ $rule->sender_username ?? '' }}" placeholder="Leeg = lvs"></div>
                            <div class="library-field"><label>Onderwerp</label>
                                <input name="subject" maxlength="200" value="{{ $rule->subject ?? '' }}" placeholder="Opvolging gedrag"></div>
                            <div class="library-field lvs-rule-title"><label>Berichttekst</label>
                                <textarea name="body" maxlength="3000" rows="5" placeholder="Beste {voornaam}, ...">{{ $rule->body ?? '' }}</textarea>
                                <small>Beschikbare velden: {voornaam}, {naam}, {aantal}, {categorie}. Voeg geen gevoelige commentaren in het sjabloon in.</small></div>
                        </div>
                    </fieldset>
                    <label class="lvs-rule-confirm"><input type="checkbox" name="confirm_automation" value="1">
                        Ik bevestig dat deze regel bij activering automatisch Smartschool-berichten mag versturen naar de hierboven gekozen ontvangers.</label>
                    <button type="submit" class="library-button library-button-primary">{{ $isNew ? 'Regel toevoegen' : 'Wijzigingen opslaan' }}</button>
                </form>
            </details>
        @endforeach

        <section class="library-form-card">
            <h2>Opvolging en verzendhistoriek</h2>
            <p class="library-help">Een aangemerkte sanctie wordt niet automatisch uitgevoerd. Smartschool-berichten worden per ontvanger geregistreerd, zodat een mislukte of onzekere verzending niet blind opnieuw wordt verstuurd.</p>
            <div class="library-table-wrap"><table class="library-table">
                <thead><tr><th>Datum</th><th>Regel</th><th>Leerling</th><th>Actie</th><th>Ontvanger</th><th>Status</th></tr></thead>
                <tbody>
                @forelse($events as $event)
                    <tr>
                        <td>{{ $event->created_at }}</td><td>{{ $event->rule_title ?: 'Verwijderde regel' }}</td>
                        <td>{{ trim(($event->voornaam ?? '').' '.($event->naam ?? '')) }}</td>
                        <td>{{ ['review'=>'Leerlingbegeleiding','sanction_proposal'=>'Sanctievoorstel','smartschool'=>'Smartschool'][$event->action] ?? $event->action }}</td>
                        <td>{{ $event->recipient }} @if($event->coaccount > 0)(co-account {{ $event->coaccount }})@endif</td>
                        <td>{{ ['pending'=>'Te beoordelen','sent'=>'Aangeboden','failed'=>'Mislukt','processing'=>'Onzeker','skipped'=>'Overgeslagen'][$event->status] ?? $event->status }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6">Nog geen automatische opvolging geregistreerd.</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </section>
    @endunless
</main>
@endsection
