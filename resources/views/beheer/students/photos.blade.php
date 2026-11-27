@extends('layouts.app')
@section('title','Leerlingfoto’s importeren')
@section('content')
<main class="library-page portal-users-page">
<div class="library-page-head"><div><p class="portal-eyebrow">BEHEER / LEERLINGEN</p><h1>Leerlingfoto’s importeren</h1><p>Koppel foto's automatisch op basis van het Informat-internnummer.</p></div><a class="library-button library-button-secondary" href="{{ route('beheer.students.index') }}">Terug naar leerlingenbeheer</a></div>
@if($errors->any())<div class="library-errors" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
<section class="library-form-card">
<h2>ZIP-bestand kiezen</h2><p>Bestandsnamen zoals 12345.jpg of 12345.png. Submappen zijn toegestaan. Maximaal 100 MB ZIP, 200 MB uitgepakt, 5 MB per foto. Ook WebP wordt ondersteund.</p>
<form method="post" enctype="multipart/form-data" action="{{ route('beheer.students.photos.upload') }}">
@csrf
<div class="library-field"><label for="archive">ZIP-bestand</label><input id="archive" type="file" name="archive" accept=".zip,application/zip" required></div>
<label><input type="checkbox" name="replace" value="1"> Bestaande foto's vervangen (anders worden ze overgeslagen)</label>
<div style="display:flex;gap:12px;flex-wrap:wrap;margin-top:16px"><button class="library-button library-button-secondary" name="mode" value="preview">Controleren zonder opslaan</button><button class="library-button library-button-primary" name="mode" value="import" onclick="return confirm('Geldige foto’s importeren?')">Foto's importeren</button></div>
</form>
<p>Na een controle kies je hetzelfde ZIP-bestand opnieuw om de import uit te voeren. Onbekende en dubbele nummers worden overgeslagen.</p>
</section>
@if(isset($report))
<section class="library-form-card"><h2>{{ $mode==='preview'?'Controleresultaat (niets opgeslagen)':'Importresultaat' }}</h2>
<dl class="lvs-details-grid">
@foreach(['matched'=>'Geldige koppelingen','unknown'=>'Onbekende nummers','invalid'=>'Ongeldige bestanden','duplicates'=>'Dubbele nummers / conflicten','existing'=>'Bestaande foto overgeslagen','saved'=>'Opgeslagen','errors'=>'Schrijffouten'] as $key=>$label)
<div><dt>{{ $label }}</dt><dd>{{ $report[$key] }}</dd></div>
@endforeach
</dl>
@if($report['examples'])<p>Voorbeelden onbekende nummers: {{ implode(', ',$report['examples']) }}</p>@endif
</section>
@endif
</main>
@endsection
