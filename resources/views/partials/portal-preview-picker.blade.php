@php
    $previewStaff = \Illuminate\Support\Facades\DB::table('users')
        ->join('groups','groups.id','=','users.groupId')
        ->where('users.allowed_login',1)
        ->where(fn($q)=>$q->whereNull('users.smartschool_active')->orWhere('users.smartschool_active',1))
        ->whereNotNull('users.smartschool_gebruikersnaam')->where('users.smartschool_gebruikersnaam','<>','')
        ->whereIn('groups.groupname',array_values(array_diff_key(\App\Services\LibraryAccess::ROLES,['ict_coordinator'=>true])))
        ->orderBy('users.name')->orderBy('users.voornaam')
        ->get(['users.id','users.voornaam','users.name','users.smartschool_gebruikersnaam','groups.groupname']);
    $previewStudents = \Illuminate\Support\Facades\DB::table('leerlingen')->where('schoolid',1)->where('active',1)->whereNotNull('gebruikersnaam')->orderBy('naam')->orderBy('voornaam')->get(['id','voornaam','naam','gebruikersnaam','geboortedatum']);
@endphp
<details class="portal-preview-picker" id="portal-preview-picker">
    <summary class="portal-preview-trigger">Inloggen als… <span aria-hidden="true">▾</span></summary>
    <div class="portal-preview-menu">
        <label for="portal-preview-search">Gebruiker zoeken</label>
        <input id="portal-preview-search" type="search" placeholder="Naam, account of rol…" autocomplete="off">
        <p class="portal-preview-hint">Testmodus: testmeldingen worden echt opgeslagen; overige acties beperkt.</p>
        <div class="portal-preview-list">
            @foreach($previewStaff as $person)
                <form method="post" action="{{ route('portal-preview.start',$person->id) }}" data-preview-person>
                    @csrf
                    <button type="submit">
                        <strong>{{ $person->voornaam }} {{ $person->name }}</strong>
                        <small>{{ $person->groupname }} · {{ $person->smartschool_gebruikersnaam }}</small>
                    </button>
                </form>
            @endforeach
            @foreach($previewStudents as $person)
                @foreach(['leerling'=>'Leerling','coaccount'=>'Co-account'] as $role=>$label)
                    @continue($role==='coaccount' && (!$person->geboortedatum || $person->geboortedatum<=now('Europe/Brussels')->subYears(18)->toDateString()))
                    <form method="post" action="{{ route('portal-preview.external',[$role,$person->id]) }}" data-preview-person hidden>@csrf
                        <button type="submit"><strong>{{ $person->voornaam }} {{ $person->naam }}</strong><small>{{ $label }} · {{ $person->gebruikersnaam }}</small></button>
                    </form>
                @endforeach
            @endforeach
            <p id="portal-preview-empty" hidden>Geen gebruikers gevonden.</p>
        </div>
    </div>
</details>
<script>
(() => {
    const picker = document.getElementById('portal-preview-picker');
    const search = document.getElementById('portal-preview-search');
    const rows = [...picker.querySelectorAll('[data-preview-person]')];
    const empty = document.getElementById('portal-preview-empty');
    function filter() {
        const words = search.value.toLocaleLowerCase('nl').trim().split(/\s+/);
        let count = 0;
        rows.forEach(row => {
            row.hidden = !words.every(word => row.textContent.toLocaleLowerCase('nl').includes(word));
            if (!row.hidden) count++;
        });
        empty.hidden = count !== 0;
    }
    search.addEventListener('input', filter);
    picker.addEventListener('toggle', () => { if (picker.open) { search.value=''; filter(); search.focus(); } });
    document.addEventListener('click', e => { if (!picker.contains(e.target)) picker.open=false; });
    picker.addEventListener('keydown', e => { if(e.key==='Escape') { picker.open=false; picker.querySelector('summary').focus(); } });
    filter();
})();
</script>
