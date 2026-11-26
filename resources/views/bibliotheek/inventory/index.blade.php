@extends('layouts.app')
@section('title','Inventariscontrole')
@section('content')
<main class="library-page"><div class="library-page-head"><h1>Inventariscontrole</h1><a href="{{ route('bibliotheek.index') }}">Terug naar bibliotheek</a></div>
@if($errors->any())<div class="library-errors">{{ $errors->first() }}</div>@endif
<section class="library-form-card"><h2>Nieuwe controle</h2><p>Leg de huidige voorraad vast. Uitgeleende boeken worden afzonderlijk geteld; later toegevoegde boeken vallen buiten deze controle.</p><form method="post" action="{{ route('bibliotheek.inventory.store') }}">@csrf <input name="name" required maxlength="120" placeholder="Bijv. Jaarcontrole 2026–2027"><button class="library-button library-button-primary">Start controle</button></form></section>
<section class="library-form-card"><h2>Controles</h2>@forelse($runs as $run)<p><a href="{{ route('bibliotheek.inventory.show',$run->id) }}">{{ $run->name }}</a> · {{ $run->finished_at ? 'Afgesloten' : 'Open' }} · {{ $run->created_at }}</p>@empty<p>Nog geen controles.</p>@endforelse {{ $runs->links() }}</section></main>
@endsection
