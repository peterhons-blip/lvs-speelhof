@extends('layouts.app')
@section('title','Klaskoppelingen controleren')
@section('content')
<main class="library-page portal-users-page"><div class="library-page-head"><div><h1>Klaskoppelingen controleren</h1><p>{{ count($preview['rows']) }} koppelingen · {{ count($preview['covered']) }} klassen · niets opgeslagen.</p></div><a class="library-button library-button-secondary" href="{{ route('beheer.classes.index') }}">Terug</a></div>
@if(!empty($preview['notes']))<section class="library-form-card"><h2>Aandachtspunten</h2><p>Controleer deze afwijkingen vóór bevestiging.</p>@foreach($preview['notes'] as $note)<p>{{ $note }}</p>@endforeach</section>@endif
@if($preview['errors'])<section class="library-form-card"><h2>Controle vereist</h2><p>{{ $kind==='teacher' ? 'Klassen met fouten worden overgeslagen; betrouwbare klassen kunnen afzonderlijk worden opgeslagen.' : 'Er worden geen wijzigingen opgeslagen zolang er fouten zijn.' }}</p>@foreach($preview['errors'] as $error)<p>{{ $error }}</p>@endforeach</section>@endif
<section class="library-form-card"><h2>{{ $kind==='teacher'?'Leerkrachten':'Klastitularissen' }}</h2>
@foreach(collect($preview['rows'])->groupBy('class') as $class=>$rows)
<details style="padding:10px 0;border-bottom:1px solid #d5e0e8"><summary style="cursor:pointer">{{ $class }} · {{ $rows->count() }}</summary><p>{{ $rows->sortBy('sort_name',SORT_NATURAL|SORT_FLAG_CASE)->pluck('display_name')->implode(', ') }}</p></details>
@endforeach
@if(($kind==='teacher' || !$preview['errors']) && $preview['rows'])
<form method="post" action="{{ route('beheer.classes.apply') }}" style="margin-top:16px">@csrf<input type="hidden" name="token" value="{{ $token }}"><button class="library-button library-button-primary" onclick="return confirm('Koppelingen voor de getoonde klassen vervangen?')">Koppelingen bevestigen</button></form>
@endif
</section></main>
@endsection
