@extends('layouts.app')
@section('title', 'Leerlingenbeheer')
@section('breadcrumbs')
@include('partials.breadcrumbs', ['items' => [
    ['label' => 'Dashboard', 'url' => route('home')],
    ['label' => 'Beheer', 'url' => route('beheer.users.index')],
    ['label' => 'Leerlingenbeheer'],
]])
@endsection
@push('scripts')
<script src="{{ asset('js/management-live.js') }}?v=2" defer></script>
@endpush
@section('content')
<main class="library-page portal-users-page">
    <div class="library-page-head">
        <div>
            <p class="portal-eyebrow">PORTAALBEHEER / LEERLINGEN</p>
            <h1>Leerlingenbeheer</h1>
            <p>Beheer bestaande leerlinggegevens uit het LVS. Gegevens uit Smartschool worden iedere nacht opnieuw gesynchroniseerd.</p>
        </div>
    </div>
    @if(session('success'))<div class="library-message" role="status">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="library-errors" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <section class="library-form-card" data-live-management>
        <div class="portal-users-table-heading">
            <div><h2>Leerlingen</h2><p>Zoek op naam, Smartschool-gebruikersnaam of klas.</p></div>
            <span class="portal-users-total" data-management-total>{{ $students->total() }} leerlingen</span>
        </div>
        <nav class="portal-users-account-filters" style="display:flex;gap:0.75rem;flex-wrap:wrap;margin:1rem 0;">
            <a class="library-button {{ $showInactive ? 'library-button-secondary' : 'library-button-primary' }}" href="{{ route('beheer.students.index', array_filter(['zoek'=>$search,'klas'=>$classId])) }}">Actieve leerlingen</a>
            <a class="library-button {{ $showInactive ? 'library-button-primary' : 'library-button-secondary' }}" href="{{ route('beheer.students.index', array_merge(['toon_inactief' => 1], $search !== '' ? ['zoek' => $search] : [])) }}">Alle leerlingen</a>
        </nav>
        <form method="get" action="{{ route('beheer.students.index') }}" style="display:flex;gap:0.75rem;flex-wrap:wrap;align-items:end;margin-bottom:1rem;">
            @if($showInactive)<input type="hidden" name="toon_inactief" value="1">@endif
            <div class="library-field" style="flex:1 1 240px;margin:0;">
                <label for="leerlingenbeheer-zoek">Leerling of klas zoeken</label>
                <input id="leerlingenbeheer-zoek" data-live-search type="search" name="zoek" maxlength="100" value="{{ $search }}" placeholder="Naam, gebruikersnaam of klas...">
            </div>
            <div class="library-field" style="min-width:180px;margin:0"><label for="student-class">Klas</label><select id="student-class" name="klas"><option value="">Alle klassen</option>
            @foreach($classes as $class)
            <option value="{{ $class->id }}" @selected($classId===$class->id)>{{ $class->klasnaam }}</option>
            @endforeach
            </select></div>
            <button class="library-button library-button-primary" type="submit">Zoeken</button>
            @if($search !== '' || $classId)
                <a class="library-button library-button-secondary" href="{{ route('beheer.students.index', $showInactive ? ['toon_inactief' => 1] : []) }}">Wissen</a>
            @endif
        </form>
        <form method="post" action="{{ route('beheer.students.bulk') }}" id="students-bulk-form" data-bulk-form style="display:flex;gap:0.75rem;flex-wrap:wrap;align-items:end;margin:1rem 0;">
            @csrf
            <span data-selected-count>0 geselecteerd (deze pagina)</span>
            <div class="library-field" style="margin:0;"><label for="students-active">Bulkactie</label>
                <select id="students-active" name="active" required>
                    <option value="">Kies actie...</option>
                    <option value="1">Als actief markeren</option>
                    <option value="0">Als inactief markeren</option>
                </select>
            </div>
            <button class="library-button library-button-primary" type="submit">Toepassen op selectie</button>
        </form>
        @if(app(\App\Services\LibraryAccess::class)->role(request())==='ict_coordinator')
        <details class="library-form-card" style="margin:16px 0">
            <summary style="cursor:pointer;font-weight:700">Individuele rechten in bulk wijzigen</summary>
            <p>Vink leerlingen in de tabel aan. Standaard blijven alle rechten ongewijzigd; kies alleen wat je wilt aanpassen. De selectie geldt voor deze pagina.</p>
            <form method="post" action="{{ route('beheer.students.bulk-rights') }}" id="student-rights-form">
                @csrf
                @foreach(['leerling'=>'Persoonlijk portaal','bibliotheek'=>'Bibliotheekbeheer (individueel)'] as $module=>$label)
                <details style="margin:12px 0" @if($module==='leerling') open @endif>
                    <summary style="cursor:pointer;font-weight:600">{{ $label }}</summary>
                    @if($module==='bibliotheek')
                    <p>Activeer ook het persoonlijke dashboard. Bibliotheek openen bepaalt of het beheermenu verschijnt.</p>
                    <button type="button" class="library-button library-button-secondary" id="bulk-all-bib">Alle bibliotheekrechten toestaan</button>
                    <button type="button" class="library-button library-button-secondary" id="bulk-no-bib">Alle bibliotheekrechten weigeren</button>
                    @endif
                    <div class="library-table-wrap"><table class="library-table"><tbody>
                    @foreach(config('portal_permissions.'.$module.'.permissions',[]) as $key=>$title)
                    @continue($key==='leerling.library.help')
                    <tr><td>{{ $title }}</td><td><select name="rights[{{ $key }}]" data-module="{{ $module }}" aria-label="{{ $title }}">
                    <option value="unchanged">Ongewijzigd</option><option value="allow">Toestaan</option><option value="deny">Weigeren</option><option value="inherit">Individuele keuze wissen</option>
                    </select></td></tr>
                    @endforeach
                    </tbody></table></div>
                </details>
                @endforeach
                <button class="library-button library-button-primary" type="submit">Rechten toepassen op selectie</button>
            </form>
        </details>
        <script>
        (() => {
            const form=document.getElementById('student-rights-form');
            const master=form.querySelector('[name="rights[leerling.library.help]"]');
            const dash=form.querySelector('[name="rights[leerling.dashboard.view]"]');
            const setBib=value=>{
                form.querySelectorAll('[data-module="bibliotheek"]').forEach(s=>s.value=value);
                if(master) master.value=value;
                if(dash && value==='allow') dash.value='allow';
            };
            document.getElementById('bulk-all-bib').addEventListener('click',()=>setBib('allow'));
            document.getElementById('bulk-no-bib').addEventListener('click',()=>setBib('deny'));
            form.addEventListener('submit',e=>{
                const boxes=[...document.querySelectorAll('[data-bulk-id]:checked')];
                if(!boxes.length || ![...form.querySelectorAll('select')].some(s=>s.value!=='unchanged')){
                    e.preventDefault();alert('Selecteer leerlingen en minstens één recht.');return;
                }
                if(!confirm('Individuele rechten wijzigen voor '+boxes.length+' geselecteerde leerlingen?')){e.preventDefault();return;}
                form.querySelectorAll('input[name="ids[]"]').forEach(x=>x.remove());
                boxes.forEach(box=>{const input=document.createElement('input');input.type='hidden';input.name='ids[]';input.value=box.value;form.append(input);});
            });
        })();
        </script>
        @endif
        <p class="portal-users-card-intro">Let op: de nachtelijke Smartschool-synchronisatie kan de actieve status van leerlingen opnieuw aanpassen.</p>
        <div data-management-results>
        <div class="library-table-wrap">
            <table class="library-table">
                <thead><tr><th><input type="checkbox" data-select-all aria-label="Selecteer alle personen op deze pagina"></th><th><a href="{{ route('beheer.students.index', array_merge(request()->except(['sort', 'richting', 'page']), ['sort' => 'leerling', 'richting' => request('sort', 'leerling') === 'leerling' && request('richting', 'asc') === 'asc' ? 'desc' : 'asc'])) }}" class="portal-column-sort" @if(request('sort', 'leerling') === 'leerling') aria-label="Leerling, gesorteerd {{ request('richting', 'asc') === 'desc' ? 'aflopend' : 'oplopend' }}; klik om om te keren" @endif>Leerling <span aria-hidden="true">{{ request('sort', 'leerling') === 'leerling' ? (request('richting', 'asc') === 'desc' ? '▼' : '▲') : '↕' }}</span></a></th><th><a href="{{ route('beheer.students.index', array_merge(request()->except(['sort', 'richting', 'page']), ['sort' => 'klas', 'richting' => request('sort', 'leerling') === 'klas' && request('richting', 'asc') === 'asc' ? 'desc' : 'asc'])) }}" class="portal-column-sort" @if(request('sort', 'leerling') === 'klas') aria-label="Klas, gesorteerd {{ request('richting', 'asc') === 'desc' ? 'aflopend' : 'oplopend' }}; klik om om te keren" @endif>Klas <span aria-hidden="true">{{ request('sort', 'leerling') === 'klas' ? (request('richting', 'asc') === 'desc' ? '▼' : '▲') : '↕' }}</span></a></th><th><a href="{{ route('beheer.students.index', array_merge(request()->except(['sort', 'richting', 'page']), ['sort' => 'account', 'richting' => request('sort', 'leerling') === 'account' && request('richting', 'asc') === 'asc' ? 'desc' : 'asc'])) }}" class="portal-column-sort" @if(request('sort', 'leerling') === 'account') aria-label="Smartschool-gebruikersnaam, gesorteerd {{ request('richting', 'asc') === 'desc' ? 'aflopend' : 'oplopend' }}; klik om om te keren" @endif>Smartschool-gebruikersnaam <span aria-hidden="true">{{ request('sort', 'leerling') === 'account' ? (request('richting', 'asc') === 'desc' ? '▼' : '▲') : '↕' }}</span></a></th><th><a href="{{ route('beheer.students.index', array_merge(request()->except(['sort', 'richting', 'page']), ['sort' => 'status', 'richting' => request('sort', 'leerling') === 'status' && request('richting', 'asc') === 'asc' ? 'desc' : 'asc'])) }}" class="portal-column-sort" @if(request('sort', 'leerling') === 'status') aria-label="Status, gesorteerd {{ request('richting', 'asc') === 'desc' ? 'aflopend' : 'oplopend' }}; klik om om te keren" @endif>Status <span aria-hidden="true">{{ request('sort', 'leerling') === 'status' ? (request('richting', 'asc') === 'desc' ? '▼' : '▲') : '↕' }}</span></a></th><th>Details</th></tr></thead>
                <tbody>
                    @forelse($students as $student)
                    <tr>
                        <td><input type="checkbox" data-bulk-id form="students-bulk-form" name="ids[]" value="{{ $student->id }}" aria-label="Selecteer {{ $student->voornaam }} {{ $student->naam }}"></td>
                        <td><strong>{{ trim(($student->naam ?? '').' '.($student->voornaam ?? '')) }}</strong></td>
                        <td>{{ $student->klasnaam ?: '—' }}</td>
                        <td>{{ $student->gebruikersnaam ?: '—' }}</td>
                        <td>{{ (int) $student->active === 1 ? 'Actief' : 'Inactief' }}</td>
                        <td><a class="library-button library-button-secondary" href="{{ route('beheer.students.show', $student->id) }}">Bekijken / bewerken</a></td>
                    </tr>
                    @empty
                    <tr><td colspan="6">Geen leerlingen gevonden.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <nav class="portal-users-pagination" aria-label="Pagina's met leerlingen">
            <span class="portal-pagination-summary">Leerlingen {{ $students->firstItem() ?? 0 }}–{{ $students->lastItem() ?? 0 }} van {{ $students->total() }}</span>
            @if($students->hasPages())
            <div class="portal-pagination-controls">
                @if($students->onFirstPage())<span class="portal-page-link is-disabled">‹ Vorige</span>
                @else<a class="portal-page-link" href="{{ $students->previousPageUrl() }}">‹ Vorige</a>@endif
                @if($students->hasMorePages())<a class="portal-page-link" href="{{ $students->nextPageUrl() }}">Volgende ›</a>
                @else<span class="portal-page-link is-disabled">Volgende ›</span>@endif
            </div>
            @endif
        </nav>
        </div>
    </section>
</main>
@endsection
