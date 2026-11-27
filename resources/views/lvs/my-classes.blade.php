@extends('layouts.app')
@section('title','LVS · Mijn klassen')
@section('breadcrumbs')
@include('partials.breadcrumbs',['items'=>[['label'=>'Portaal','url'=>route('home')],['label'=>'LVS','url'=>route('lvs.dashboard')],['label'=>'Mijn klassen']]])
@endsection
@section('content')
<main class="library-page">
<div class="library-page-head"><div><p class="portal-eyebrow">LEERLINGVOLGSYSTEEM</p><h1>Mijn klassen</h1><p>Schooljaar {{ $year }} · jouw titularisklassen en lesleerlingen volgens Smartschool en Skore.</p></div><a class="library-button library-button-secondary" href="{{ route('leerlingen.index') }}">Alle leerlingen zoeken</a></div>
<section class="library-form-card"><label for="my-class-search"><strong>Zoek binnen mijn klassen</strong></label><input id="my-class-search" class="lvs-search" type="search" placeholder="Naam, klas of vak" autocomplete="off"><p class="lvs-field-help">Bij een zoekopdracht worden de eigen klassen geladen en gefilterd.</p><p id="my-class-status" role="status"></p></section>
<div id="my-classes" data-session-key="my-classes:{{ $staffId }}:{{ $year }}">
@foreach([true,false] as $mentor)
<section class="library-form-card my-class-section"><h2>{{ $mentor?'Mijn titularisklassen':'Mijn lesklassen' }}</h2>
@forelse($classes->where('mentor',$mentor) as $class)
<details class="class-admin-details my-class-detail" data-class-id="{{ $class['id'] }}" data-roster-url="{{ route('lvs.my-classes.roster',$class['id']) }}">
<summary><span>{{ $class['name'] }} · {{ $class['count'] }} leerlingen</span>@if($mentor)<span class="my-class-role">Klastitularis</span>@endif
@if($class['courses']->isNotEmpty())<small>{{ $class['courses']->implode(' · ') }}</small>@endif</summary>
<div data-class-roster aria-live="polite">Open de klas om de leerlingen te laden.</div>
</details>
@empty
<p class="lvs-empty">Geen klassen gekoppeld.</p>
@endforelse
</section>
@endforeach
</div>
</main>
@endsection
@push('scripts')
<script src="{{ asset('js/my-classes.js') }}?v=1" defer></script>
@endpush
