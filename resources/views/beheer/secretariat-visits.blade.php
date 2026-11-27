@extends('layouts.app')
@section('title','Secretariaatsbezoeken 18-jarigen')
@section('content')
<main class="library-page">
<div class="library-page-head"><div><h1>Secretariaatsbezoeken 18-jarigen</h1><p>Opvolging ondertekening schoolreglement en administratieve documenten.</p></div><a href="{{ route('home') }}">Dashboard</a></div>
@if(session('success'))<div class="library-message">{{ session('success') }}</div>@endif
@if($errors->any())<div class="library-errors">{{ $errors->first() }}</div>@endif
<section class="library-form-card">
<p>Herinneringen: {{ $config['secretariat_reminder_enabled']==='1' ? 'ingeschakeld' : 'uitgeschakeld' }} · Om de {{ $config['secretariat_reminder_days'] }} schooldagen. Afvinken stopt verdere herinneringen.</p>
<form method="get" class="library-loan-filters"><input name="q" value="{{ $q }}" placeholder="Naam of klas"><select name="status">@foreach(['open'=>'Openstaand','done'=>'Afgehandeld','all'=>'Alles'] as $key=>$label)<option value="{{ $key }}" @selected($mode===$key)>{{ $label }}</option>@endforeach</select><button class="library-button library-button-secondary">Filteren</button></form>
<div class="library-table-wrap"><table class="library-table"><thead><tr><th>Leerling</th><th>Klas</th><th>Opvolging sinds</th><th>Volgende herinnering</th><th>Langsgekomen</th></tr></thead><tbody>
@forelse($rows as $row)<tr><td><div class="student-photo-inline">@include('partials.student-photo',['studentId'=>$row->student_id])<strong>{{ $row->voornaam }} {{ $row->naam }}</strong></div></td><td>{{ $row->klasnaam }}</td><td>{{ $row->invited_on }}</td><td>{{ $row->completed_at || $config['secretariat_reminder_enabled']!=='1' ? '—' : $row->next_date }}</td><td>
<form method="post" action="{{ route('secretariat-visits.update',$row->id) }}">@csrf
<input type="hidden" name="completed" value="{{ $row->completed_at ? '0' : '1' }}"><button class="library-button library-button-secondary">{{ $row->completed_at ? 'Heropenen' : '✓ Langsgekomen' }}</button></form>
@if($row->completed_at)<small>{{ $row->completed_at }} · {{ $row->completed_by }}</small>@endif
</td></tr>@empty<tr><td colspan="5">Geen leerlingen gevonden.</td></tr>@endforelse
</tbody></table></div>@include('partials.portal-pagination', ['paginator'=>$rows,'label'=>'Leerlingen'])</section></main>
@endsection
