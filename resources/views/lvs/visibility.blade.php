@extends('layouts.app')
@section('title','LVS · Zichtbaarheid meldingen')
@section('content')
<main class="library-page">
<div class="library-page-head"><div><p class="portal-eyebrow">LVS / INSTELLINGEN</p><h1>Zichtbaarheid meldingen</h1><p>Beperk welke meldingen personeelsleden kunnen lezen, ook voor bestaande meldingen.</p></div><a class="library-button library-button-secondary" href="{{ route('beheer.permissions.index') }}">Rollen en rechten</a></div>
@if(session('success'))<div class="library-message" role="status">{{ session('success') }}</div>@endif
@if($errors->any())<div class="library-errors">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
<section class="library-form-card">
<p>De algemene rechten blijven vereist. Vertrouwelijke meldingen vereisen ook het afzonderlijke vertrouwelijkheidsrecht. ICT heeft steeds toegang. Een titularis volgt de titularisregel, ook als die lesgeeft aan de leerling. Andere medewerkers volgen hun schoolrol.</p>
<p>Eigen meldingen betekent eigen gewone meldingen met een geregistreerde auteur. Bij ontbrekende lesrelaties krijgt een leerkracht alleen de rechten van een andere leerkracht. Historische dossiers vereisen hun eigen recht.</p>
@if(!$ready)<p>Voer eerst de migratie voor de zichtbaarheidsmatrix uit. Tot dan is inzage voor niet-ICT geblokkeerd.</p>@else
<form method="post" action="{{ route('lvs.visibility.save') }}">@csrf
<div class="library-table-wrap"><table class="library-table"><thead><tr><th>Rol / relatie</th><th>Eigen gewone meldingen</th><th>Alle gewone meldingen</th><th>Vertrouwelijke meldingen</th></tr></thead><tbody>
@foreach($labels as $key=>$label)
<tr><th scope="row">{{ $label }}</th>@foreach(['own'=>'Eigen gewone meldingen','ordinary'=>'Alle gewone meldingen','confidential'=>'Vertrouwelijke meldingen'] as $field=>$caption)
<td><input type="hidden" name="matrix[{{ $key }}][{{ $field }}]" value="0"><input type="checkbox" name="matrix[{{ $key }}][{{ $field }}]" value="1" aria-label="{{ $label }} — {{ $caption }}" @checked($matrix[$key][$field])></td>
@endforeach</tr>
@endforeach
</tbody></table></div>
<p class="library-help">Deze instellingen gelden onmiddellijk voor dossiers, meldingen, opvolging, tellingen en exports. Ze wijzigen geen leerling- of oudervrijgave.</p>
<button class="library-button library-button-primary" type="submit">Zichtbaarheid opslaan</button>
</form>
@endif
</section></main>
@endsection
