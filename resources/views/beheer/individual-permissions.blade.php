@extends('layouts.app')
@section('title','Individuele rechten')
@section('content')
<main class="library-page portal-roles-page">
<div class="library-page-head"><div><h1>Individuele rechten</h1><p>Erven van rol, toestaan of weigeren per medewerker.</p></div><a class="library-button library-button-secondary" href="{{ route('beheer.permissions.index') }}">Rollen en rechten</a></div>
@if(session('success'))<div class="library-message">{{ session('success') }}</div>@endif
@if($errors->any())<div class="library-errors">{{ $errors->first() }}</div>@endif
<section class="library-form-card"><form method="get" class="library-loan-filters"><input name="q" value="{{ $q }}" placeholder="Naam of gebruikersnaam"><button class="library-button library-button-secondary">Zoeken</button></form>
<div class="library-table-wrap"><table class="library-table"><thead><tr><th>Medewerker</th><th>Schoolrol</th><th></th></tr></thead><tbody>@foreach($users as $user)<tr><td>{{ $user->voornaam }} {{ $user->name }}</td><td>{{ $user->groupname }}</td><td style="text-align:right;white-space:nowrap"><a class="library-button library-button-secondary" href="{{ route('beheer.permissions.individual',['user_id'=>$user->id,'q'=>$q]) }}#individuele-instellingen">Rechten bekijken <span aria-hidden="true">→</span></a></td></tr>@endforeach</tbody></table></div>{{ $users->links() }}</section>
@if($selected)
<section id="individuele-instellingen" class="library-form-card" style="scroll-margin-top:100px"><h2>{{ $selected->voornaam }} {{ $selected->name }}</h2>
@if($role==='ict_coordinator')<p>ICT heeft vaste volledige toegang.</p>
@elseif(!$role)<p>Geen geldige schoolrol.</p>
@else
<form method="post" action="{{ route('beheer.permissions.individual.save',$selected->id) }}">@csrf
@foreach($modules as $module)
<details class="portal-permission-module"><summary class="portal-permission-module-summary">{{ $module['label'] }}</summary>
<div class="library-table-wrap"><table class="library-table"><thead><tr><th>Recht</th><th>Via rol</th><th>Individueel</th><th>Effectief</th></tr></thead><tbody>
@foreach($module['permissions'] as $key=>$label)
@php
$base=in_array($key,$inherited,true);
$override=array_key_exists($key,$overrides)?($overrides[$key]?'allow':'deny'):'inherit';
@endphp
<tr><td>{{ $label }}</td><td>{{ $base?'Ja':'Nee' }}</td><td><select name="rights[{{ $key }}]">@foreach(['inherit'=>'Erven','allow'=>'Toestaan','deny'=>'Weigeren'] as $value=>$text)<option value="{{ $value }}" @selected($override===$value)>{{ $text }}</option>@endforeach</select></td><td>{{ ($override==='allow'||($override==='inherit'&&$base))?'Ja':'Nee' }}</td></tr>
@endforeach
</tbody></table></div></details>
@endforeach
<p class="library-help">Effectief toont de opgeslagen rechten. Aanmeldblokkering blijft gelden. Alleen ICT kan uitzonderingen opslaan.</p>
<button class="library-button library-button-primary">Opslaan</button></form>
@endif
</section>
@endif
</main>
@endsection
