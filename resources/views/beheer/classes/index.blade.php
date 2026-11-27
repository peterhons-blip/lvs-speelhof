@extends('layouts.app')
@section('title','Klassenbeheer')
@section('content')
<main class="library-page portal-users-page">
<div class="library-page-head"><div><p class="portal-eyebrow">BEHEER / KLASSEN</p><h1>Klassenbeheer</h1><p>Schooljaar {{ $year }} · actieve klassen en personeelskoppelingen.</p></div></div>
@if(session('success'))<div class="library-message">{{ session('success') }}</div>@endif
@if($errors->any())<div class="library-errors">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
<section class="library-form-card"><h2>Leerkrachten uit Smartschool</h2><p>Zoekt per klas de groep met achtervoegsel LK. Eerst controleren, daarna bevestigen.</p>
<form method="post" action="{{ route('beheer.classes.preview') }}">@csrf<input type="hidden" name="kind" value="teacher"><button class="library-button library-button-primary">Smartschool-koppelingen controleren</button></form></section>
<section class="library-form-card"><h2>Klastitularissen importeren</h2><p>XLSX of CSV met kolommen <strong>klas</strong> en <strong>usernameKlastitularis</strong>. Meerdere regels per klas zijn toegestaan. Alleen titularissen van de opgenomen klassen worden vervangen.</p>
<form method="post" enctype="multipart/form-data" action="{{ route('beheer.classes.preview') }}">@csrf<input type="hidden" name="kind" value="mentor"><div class="library-field"><label for="mentor-file">Excel-bestand</label><input id="mentor-file" name="file" type="file" accept=".xlsx,.csv" required></div><button class="library-button library-button-primary">Import controleren</button></form></section>
<section class="library-form-card"><h2>Klassen</h2>
@foreach($classes as $class)
@php($items=$members->get($class->id,collect()))
<details style="padding:12px 0;border-bottom:1px solid #d5e0e8"><summary style="cursor:pointer;font-weight:650">{{ $class->klasnaam }} · {{ $counts[$class->id]??0 }} leerlingen · {{ $items->where('kind','teacher')->count() }} leerkrachten · {{ $items->where('kind','mentor')->count() }} titularissen</summary>
<p><strong>Klastitularissen:</strong> {{ $items->where('kind','mentor')->pluck('smartschool_gebruikersnaam')->implode(', ') ?: 'Niet gekoppeld' }}</p>
<p><strong>Leerkrachten:</strong> {{ $items->where('kind','teacher')->pluck('smartschool_gebruikersnaam')->implode(', ') ?: 'Niet gekoppeld' }}</p>
</details>
@endforeach
</section></main>
@endsection
