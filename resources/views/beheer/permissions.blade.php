@extends('layouts.app')
@section('title', 'Rollen en rechten')
@section('breadcrumbs')
@include('partials.breadcrumbs', ['items' => [
    ['label' => 'Dashboard', 'url' => route('home')],
    ['label' => 'Beheer'],
    ['label' => 'Rollen en rechten'],
]])
@endsection

@section('content')
<main class="library-page portal-roles-page">
    <div class="library-page-head">
        <div>
            <p class="portal-eyebrow">PORTAALBEHEER</p>
            <h1>Rollen en rechten</h1>
            <p>Beheer per schoolrol welke modules zichtbaar zijn en welke acties of instellingen aangepast mogen worden.</p>
        </div>
        <a class="library-button library-button-secondary" href="{{ route('beheer.users.index') }}">Personeelsbeheer</a>
    </div>

    @if(session('success'))<div class="library-message" role="status">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="library-errors" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <section class="library-form-card" style="margin-bottom:1.5rem;">
        <h2>Persoonlijke beheerrechten</h2>
        <p class="portal-users-card-intro">Geef een individuele medewerker toegang tot specifieke onderdelen van <strong>Beheer</strong>, zonder de schoolrol van die medewerker te veranderen. Met <strong>Volledig beheer</strong> kan bijvoorbeeld een directeur alle beheerschermen openen, inclusief Rollen en rechten, zonder van schoolrol te veranderen. Dit wordt uitsluitend per medewerker toegekend en niet automatisch aan alle directieleden.</p>
        @if(!$personalReady)
            <div class="library-errors" role="alert">De tabel voor persoonlijke beheerrechten is nog niet geïnstalleerd. Voer de gerichte migratie uit voordat je individuele rechten kunt opslaan.</div>
        @else
            <form method="post" action="{{ route('beheer.permissions.personal.update') }}" id="personal-permissions-form">
                @csrf
                <div class="library-field">
                    <label for="personal-permissions-user">1. Kies een personeelslid</label>
                    <select id="personal-permissions-user" name="user_id" required style="width:100%;">
                        <option value="">Selecteer medewerker...</option>
                        @foreach($personnel as $member)
                            <option value="{{ $member->id }}">{{ trim(($member->voornaam ?? '').' '.($member->name ?? '')) }} ({{ $member->smartschool_gebruikersnaam }})</option>
                        @endforeach
                    </select>
                </div>
                <p style="font-weight:700;margin:1.25rem 0 .75rem;">2. Welke beheersonderdelen mag deze persoon gebruiken?</p>
                <div class="portal-permission-list" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:.75rem;">
                    @foreach($personalPermissions as $permission => $label)
                        <label class="portal-permission-option" @if($permission === 'beheer.full') style="grid-column:1/-1;border:1px solid #b5c8d6;border-radius:10px;padding:1rem;background:#f2f7fa;" @endif>
                            <input type="checkbox" name="permissions[]" value="{{ $permission }}" data-personal-permission disabled>
                            <span><strong>{{ $label }}</strong>@if($permission === 'beheer.full')<small style="display:block;color:#64748b;margin-top:.25rem;">Geeft toegang tot alle beheermodules, inclusief het toekennen van rechten aan andere medewerkers.</small>@endif</span>
                        </label>
                    @endforeach
                </div>
                <p id="personal-permissions-summary" role="status" style="margin:1rem 0;color:#64748b;">Kies eerst een medewerker. De huidige persoonlijke rechten worden dan ingevuld.</p>
                <button id="personal-permissions-submit" class="library-button library-button-primary" type="submit" disabled>Persoonlijke beheerrechten opslaan</button>
            </form>
        @endif
    </section>

    <div class="portal-roles-intro">
        <span aria-hidden="true" class="portal-roles-intro-icon">✓</span>
        <p>Alle rollen staan standaard dicht. Klik op een rol om de rechten te bekijken of aan te passen. <strong>Niet aangevinkt = geen toegang.</strong></p>
    </div>

    <div class="portal-roles-accordion" aria-label="Schoolrollen en toegangsrechten">
        <details class="portal-role-panel portal-role-panel-ict">
            <summary class="portal-role-summary">
                <span class="portal-role-identity">
                    <span class="portal-role-avatar" aria-hidden="true">ICT</span>
                    <span class="portal-role-name">ICT-coördinator <small>Volledige beheertoegang</small></span>
                </span>
                <span class="portal-role-summary-end">
                    <span class="portal-role-count portal-role-count-full">Alle rechten</span>
                    <span class="portal-role-chevron" aria-hidden="true"></span>
                </span>
            </summary>
            <div class="portal-role-content">
                <p>De ICT-coördinator heeft automatisch toegang tot alle modules en beheerschermen. Deze rechten zijn niet uit te schakelen, zodat het beheer van het portaal bereikbaar blijft.</p>
            </div>
        </details>

        @foreach($roles as $role => $title)
            @continue($role === 'ict_coordinator')
            @php
                $assigned = $stored[$role] ?? [];
                $available = collect($modules)->flatMap(fn ($module) =>
                    collect($module['permissions'] ?? [])->keys()->reject(
                        fn ($key) => in_array($key, array_merge(['beheer.users.manage', 'beheer.permissions.manage'], array_keys(\App\Services\LibraryAccess::PERSONAL_MANAGEMENT)), true)
                    )
                )->values()->all();
                $enabledCount = count(array_intersect($assigned, $available));
            @endphp
            <details class="portal-role-panel" data-role-panel="{{ $role }}">
                <summary class="portal-role-summary">
                    <span class="portal-role-identity">
                        <span class="portal-role-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($title, 0, 2)) }}</span>
                        <span class="portal-role-name">{{ $title }} <small>Rechten per module instellen</small></span>
                    </span>
                    <span class="portal-role-summary-end">
                        <span class="portal-role-count" data-role-count>{{ $enabledCount }} van {{ count($available) }} actief</span>
                        <span class="portal-role-chevron" aria-hidden="true"></span>
                    </span>
                </summary>

                <form class="portal-role-content portal-role-form" method="post" action="{{ route('beheer.permissions.update') }}">
                    @csrf
                    <input type="hidden" name="role" value="{{ $role }}">
                    <div class="portal-role-content-head">
                        <p>Open een module om de toegangsrechten voor <strong>{{ $title }}</strong> te wijzigen.</p>
                        <span class="portal-role-unsaved" data-unsaved hidden>Niet-opgeslagen wijzigingen</span>
                    </div>

                    <div class="portal-permission-modules">
                        @foreach($modules as $module)
                            @php
                                $rights = collect($module['permissions'] ?? [])->except(array_merge(['beheer.users.manage', 'beheer.permissions.manage'], array_keys(\App\Services\LibraryAccess::PERSONAL_MANAGEMENT)));
                                $moduleActive = count(array_intersect($assigned, $rights->keys()->all()));
                            @endphp
                            @if($rights->isNotEmpty())
                                <details class="portal-permission-module">
                                    <summary class="portal-permission-module-summary">
                                        <span class="portal-permission-module-name">{{ $module['label'] }}</span>
                                        <span class="portal-permission-module-side">
                                            <span data-module-count>{{ $moduleActive }}/{{ $rights->count() }}</span>
                                            <span class="portal-role-chevron" aria-hidden="true"></span>
                                        </span>
                                    </summary>
                                    <div class="portal-permission-module-body">
                                        <div class="portal-permission-list">
                                            @foreach($rights as $permission => $label)
                                                <label class="portal-permission-option">
                                                    <input type="checkbox" name="permissions[]" value="{{ $permission }}" @checked(in_array($permission, $assigned, true))>
                                                    <span>{{ $label }}</span>
                                                </label>
                                            @endforeach
                                        </div>
                                    </div>
                                </details>
                            @endif
                        @endforeach
                    </div>

                    <div class="portal-role-actions">
                        <span class="portal-role-help">Wijzigingen worden pas actief na opslaan.</span>
                        <button type="submit" class="library-button library-button-primary">Rechten voor {{ $title }} opslaan</button>
                    </div>
                </form>
            </details>
        @endforeach
    </div>
</main>
@endsection

@push('scripts')
<script>
(() => {
    const personSelect = document.getElementById('personal-permissions-user');
    if (personSelect) {
        const grants = @json($personalGrants);
        const checkboxes = [...document.querySelectorAll('[data-personal-permission]')];
        const submit = document.getElementById('personal-permissions-submit');
        const summary = document.getElementById('personal-permissions-summary');
        const refresh = () => {
            const selected = personSelect.value;
            const assigned = grants[selected] || [];
            for (const box of checkboxes) {
                box.disabled = !selected;
                box.checked = selected !== '' && assigned.includes(box.value);
            }
            submit.disabled = !selected;
            summary.textContent = selected
                ? 'De aangevinkte onderdelen zijn de huidige persoonlijke beheerrechten. Volledig beheer omvat ook Rollen en rechten. Wijzig de selectie en klik op Opslaan.'
                : 'Kies eerst een medewerker. De huidige persoonlijke rechten worden dan ingevuld.';
        };
        personSelect.addEventListener('change', refresh);
        refresh();
    }
    document.querySelectorAll('.portal-role-form').forEach(form => {
        const panel = form.closest('[data-role-panel]');
        const checkboxes = [...form.querySelectorAll('input[name="permissions[]"]')];
        const counter = panel.querySelector('[data-role-count]');
        const unsaved = form.querySelector('[data-unsaved]');
        const original = checkboxes.map(box => box.checked);

        const update = () => {
            const active = checkboxes.filter(box => box.checked).length;
            counter.textContent = active + ' van ' + checkboxes.length + ' actief';
            form.querySelectorAll('.portal-permission-module').forEach(module => {
                const boxes = [...module.querySelectorAll('input[type="checkbox"]')];
                module.querySelector('[data-module-count]').textContent =
                    boxes.filter(box => box.checked).length + '/' + boxes.length;
            });
            unsaved.hidden = !checkboxes.some((box, index) => box.checked !== original[index]);
        };

        form.addEventListener('change', update);
    });
})();
</script>
@endpush
