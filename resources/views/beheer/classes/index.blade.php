@extends('layouts.app')
@section('title','Klassenbeheer')
@section('content')
<main class="library-page portal-users-page">
<div class="library-page-head"><div><p class="portal-eyebrow">BEHEER / KLASSEN</p><h1>Klassenbeheer</h1><p>Schooljaar {{ $year }} · actieve klassen en personeelskoppelingen.</p></div></div>
@if(session('success'))<div class="library-message">{{ session('success') }}</div>@endif
@if($errors->any())<div class="library-errors">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
<section class="library-form-card"><h2>Leerkrachten uit Smartschool</h2><p>Zoekt per klas de Smartschool-groep met voorvoegsel Lkr (bijvoorbeeld Lkr 3 BO). Eerst controleren, daarna bevestigen.</p>
<form method="post" action="{{ route('beheer.classes.preview') }}">@csrf<input type="hidden" name="kind" value="teacher"><button class="library-button library-button-primary">Smartschool-koppelingen controleren</button></form></section>
<section class="library-form-card"><h2>Klastitularissen uit Smartschool</h2><p>Haalt alle titularissen van officiële klassen rechtstreeks op, inclusief meerdere titularissen per klas.</p><form method="post" action="{{ route('beheer.classes.preview') }}">@csrf<input type="hidden" name="kind" value="mentor_smartschool"><button class="library-button library-button-primary">Klastitularissen controleren</button></form></section>
<section class="library-form-card"><h2>Klastitularissen importeren (reserveoptie)</h2><p>XLSX of CSV met kolommen <strong>klas</strong> en <strong>usernameKlastitularis</strong>. Meerdere regels per klas zijn toegestaan. Alleen titularissen van de opgenomen klassen worden vervangen.</p>
<form method="post" enctype="multipart/form-data" action="{{ route('beheer.classes.preview') }}">@csrf<input type="hidden" name="kind" value="mentor"><div class="library-field"><label for="mentor-file">Excel-bestand</label><input id="mentor-file" name="file" type="file" accept=".xlsx,.csv" required></div><button class="library-button library-button-primary">Import controleren</button></form></section>
<section class="library-form-card"><h2>Klassen</h2>
@foreach($classes as $class)
@php($items=$members->get($class->id,collect()))
<details class="class-admin-details" data-roster-url="{{ route('beheer.classes.students',$class->id) }}"><summary style="cursor:pointer;font-weight:650">{{ $class->klasnaam }} · {{ $counts[$class->id]??0 }} leerlingen · {{ $items->where('kind','teacher')->count() }} leerkrachten · {{ $items->where('kind','mentor')->count() }} titularissen</summary>
<p><strong>Klastitularissen:</strong> {{ $items->where('kind','mentor')->pluck('smartschool_gebruikersnaam')->implode(', ') ?: 'Niet gekoppeld' }}</p>
<p><strong>Leerkrachten:</strong> {{ $items->where('kind','teacher')->pluck('smartschool_gebruikersnaam')->implode(', ') ?: 'Niet gekoppeld' }}</p>
<h3>Leerlingen</h3>
<div data-class-roster aria-live="polite">Open de klas om de leerlingen te laden.</div>
</details>
@endforeach
</section></main>
@endsection
@push('scripts')
<script>
document.querySelectorAll('details[data-roster-url]').forEach(details => {
    const target=details.querySelector('[data-class-roster]');
    async function load(){
        if(details.dataset.loaded || details.dataset.loading)return;
        details.dataset.loading='1';
        target.textContent='Leerlingen laden…';
        try{
            const response=await fetch(details.dataset.rosterUrl,{credentials:'same-origin',headers:{'X-Requested-With':'XMLHttpRequest'}});
            if(!response.ok||response.redirected)throw new Error('load');
            target.innerHTML=await response.text();
            details.dataset.loaded='1';
        }catch(e){
            target.textContent='Leerlingen konden niet worden geladen. ';
            const retry=document.createElement('button');
            retry.type='button'; retry.className='library-button library-button-secondary'; retry.textContent='Opnieuw proberen';
            retry.addEventListener('click',load);target.append(retry);
        }finally{delete details.dataset.loading;}
    }
    details.addEventListener('toggle',()=>{if(details.open)load();});
    if(details.open)load();
});
</script>
@endpush
