@extends('layouts.app')
@section('title','Meldingsvrijgave')
@section('content')
<main class="library-page"><h1>Meldingsvrijgave</h1><p>Standaardkeuzes voor nieuwe meldingen. Vertrouwelijke meldingen worden nooit vrijgegeven. Bestaande meldingen wijzigen niet automatisch.</p>
@if(session('success'))<p role="status">{{ session('success') }}</p>@endif
<form method="post" action="{{ route('beheer.incident-release.save') }}">@csrf
<div class="library-table-wrap"><table class="library-table"><thead><tr><th>Categorie</th><th>Meldingssoort</th><th>Leerling</th><th>Co-account</th></tr></thead><tbody>
@foreach($types as $type)<tr><td>{{ $type->categorie }}</td><td>{{ $type->naam }}</td><td><input type="hidden" name="rights[{{ $type->id }}][student]" value="0"><input type="checkbox" name="rights[{{ $type->id }}][student]" value="1" @checked($type->visible_student) aria-label="Leerling: {{ $type->naam }}"></td><td><input type="hidden" name="rights[{{ $type->id }}][coaccount]" value="0"><input type="checkbox" name="rights[{{ $type->id }}][coaccount]" value="1" @checked($type->visible_coaccount) aria-label="Co-account: {{ $type->naam }}"></td></tr>@endforeach
</tbody></table></div><button class="library-button library-button-primary">Opslaan</button></form></main>
@endsection
