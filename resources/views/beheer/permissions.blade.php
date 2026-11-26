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
            <p>Bepaal per schoolrol welke modules en beheerschermen toegankelijk zijn. De gekozen rechten gelden voor iedereen met die schoolrol.</p>
        </div>
        <a class="library-button library-button-secondary" href="{{ route('beheer.users.index') }}">Personeelsbeheer</a>
    </div>

    @if(session('success'))<div class="library-message" role="status">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="library-errors" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <section class="library-form-card" style="margin-bottom:22px">
        <h2>Individuele uitzonderingen</h2>
        <p>De schoolrol is de basis. Een individuele toestemming of weigering heeft voorrang. ICT behoudt volledige toegang.</p>
        <a class="library-button library-button-secondary" href="{{ route('beheer.permissions.individual') }}">Individuele rechten beheren</a>
    </section>

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
                $roleModules = array_diff_key($modules,\App\Services\LibraryAccess::EXTERNAL_ROLES);
                if (array_key_exists($role,\App\Services\LibraryAccess::EXTERNAL_ROLES)) {
                    $roleModules = [];
                    foreach (($modules[$role]['permissions'] ?? []) as $key => $label) {
                        $parts = explode(' · ', $label, 2);
                        $section = count($parts) === 2 ? $parts[0] : 'Algemeen';
                        $roleModules[$section]['label'] = $section;
                        $roleModules[$section]['permissions'][$key] = count($parts) === 2 ? $parts[1] : $label;
                    }
                }
                $available = collect($roleModules)->flatMap(fn ($module) =>
                    array_keys($module['permissions'] ?? [])
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
                        @foreach($roleModules as $module)
                            @php
                                $rights = collect($module['permissions'] ?? []);
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
                                        <label class="portal-permission-option" style="margin-bottom:12px;padding-bottom:10px;border-bottom:1px solid #dbe3eb;font-weight:700">
                                            <input type="checkbox" data-module-toggle @checked($moduleActive === $rights->count())>
                                            <span>Alle rechten in {{ $module['label'] }} selecteren</span>
                                        </label>
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
                const boxes = [...module.querySelectorAll('input[name="permissions[]"]')];
                const selected = boxes.filter(box => box.checked).length;
                const toggle = module.querySelector('[data-module-toggle]');
                toggle.checked = selected === boxes.length;
                toggle.indeterminate = selected > 0 && selected < boxes.length;
                module.querySelector('[data-module-count]').textContent = selected + '/' + boxes.length;
            });
            unsaved.hidden = !checkboxes.some((box, index) => box.checked !== original[index]);
        };

        form.addEventListener('change', event => {
            if (event.target.matches('[data-module-toggle]')) {
                const module = event.target.closest('.portal-permission-module');
                module.querySelectorAll('input[name="permissions[]"]').forEach(box => {
                    box.checked = event.target.checked;
                });
            }
            update();
        });
        update();
    });
})();
</script>
@endpush
