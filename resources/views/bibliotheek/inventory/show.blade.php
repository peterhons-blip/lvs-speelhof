@extends('layouts.app')
@section('title','Inventariscontrole')
@section('content')
<main class="library-page"><div class="library-page-head"><h1>{{ $item->name }}</h1><a href="{{ route('bibliotheek.inventory.index') }}">Alle controles</a></div>
@if(session('success'))<div class="library-message">{{ session('success') }}</div>@endif
@if($errors->any())<div class="library-errors">{{ $errors->first() }}</div>@endif
<div class="library-report-stats">@foreach(['total'=>'Voorraad bij start','seen'=>'Gecontroleerd','loaned'=>'Uitgeleend bij start, niet gescand','missing'=>'Nog niet gevonden'] as $k=>$label)<div class="library-report-stat"><span>{{ $label }}</span><strong>{{ $stats[$k] }}</strong></div>@endforeach</div>
@if(!$item->finished_at)<section class="library-form-card"><form method="post" action="{{ route('bibliotheek.inventory.scan',$item->id) }}">@csrf<label>Scan exemplaarbarcode <input name="barcode" autofocus autocomplete="off" required maxlength="60"></label><button class="library-button library-button-primary">Registreren</button></form><form method="post" action="{{ route('bibliotheek.inventory.finish',$item->id) }}" onsubmit="return confirm('Controle definitief afsluiten?')">@csrf<button class="library-button library-button-secondary">Controle afsluiten</button></form></section>@endif
<section class="library-form-card"><p>De categorieën zijn gebaseerd op de voorraadstatus bij aanvang. Controleer ontbrekende boeken handmatig.</p>@foreach(['missing'=>'Nog niet gevonden','seen'=>'Gecontroleerd','loaned'=>'Uitgeleend bij start','all'=>'Alles'] as $k=>$label)<a class="library-button library-button-secondary" href="{{ route('bibliotheek.inventory.show',['run'=>$item->id,'filter'=>$k]) }}">{{ $label }}</a> @endforeach
<table class="library-table"><thead><tr><th>Boek</th><th>Barcode</th><th>Locatie</th><th>Status bij start</th><th>Gescannd op</th></tr></thead><tbody>@foreach($copies as $copy)<tr><td>{{ $copy->title }}</td><td>{{ $copy->barcode }}</td><td>{{ $copy->shelf_location }}</td><td>{{ $copy->initial_status }}</td><td>{{ $copy->seen_at ?: '—' }}</td></tr>@endforeach</tbody></table>{{ $copies->links() }}</section></main>
@endsection
