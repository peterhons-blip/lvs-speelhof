@extends('layouts.app')
@section('title','Categorieën en meldingssoorten')
@section('breadcrumbs')
@include('partials.breadcrumbs',['items'=>[['label'=>'Dashboard','url'=>route('home')],['label'=>'Categorieën en meldingssoorten']]])
@endsection
@section('content')
<style>
.taxonomy-page .tax-row {display:grid;grid-template-columns:minmax(180px,2fr) minmax(90px,1fr) 85px 100px auto;gap:12px;align-items:end;padding:12px 0;border-bottom:1px solid #e2e8f0}
.taxonomy-page .tax-type {grid-template-columns:minmax(150px,2fr) minmax(150px,1.5fr) 80px 90px 100px 100px auto}
.taxonomy-page .tax-row label {display:flex;flex-direction:column;gap:6px;font-size:13px;font-weight:600}
.taxonomy-page .tax-row input:not([type=checkbox]),.taxonomy-page .tax-row select {width:100%;min-width:0;box-sizing:border-box;padding:9px;border:1px solid #cbd5e1;border-radius:6px;background:white;color:#17365d}
.taxonomy-page .tax-row input[type=checkbox] {appearance:auto;width:20px;height:20px;accent-color:#007b8d;cursor:pointer}
.taxonomy-page .tax-row .tax-check {align-items:center;justify-content:center;min-height:58px}
.taxonomy-page .tax-info {background:#edf5f8;padding:16px;border-radius:9px;margin:16px 0}
.taxonomy-page .tax-new {background:#f0f7f8;padding:14px;border-radius:8px;margin:14px 0}
.taxonomy-page .tax-category {background:#edf4f7;padding:12px;border-radius:7px;margin-top:20px;display:flex;justify-content:space-between;gap:8px}
@media(max-width:1100px){.taxonomy-page .tax-row,.taxonomy-page .tax-type{grid-template-columns:repeat(auto-fit,minmax(135px,1fr))}}
</style>
<main class="library-page taxonomy-page">
<div class="library-page-head"><div><p class="portal-eyebrow">LVS / INSTELLINGEN</p><h1>Categorieën en meldingssoorten</h1><p>Beheer de structuur en standaardvrijgave van nieuwe LVS-meldingen.</p></div></div>
<div class="tax-info"><strong>Hoe werkt dit?</strong><p>Actieve categorieën en soorten verschijnen bij nieuwe meldingen. Lagere volgnummers komen eerst; gelijke nummers worden alfabetisch gesorteerd. Deactiveer in plaats van te verwijderen: bestaande meldingen en opvolgregels blijven behouden.</p><p>Leerling en Co-account bepalen de standaardvrijgave bij nieuwe meldingen. De leerkracht kan afwijken. Vertrouwelijke meldingen blijven intern. Een gebruikte meldingssoort kan niet naar een andere categorie worden verplaatst, om historische rapportages te beschermen.</p></div>
@if(session('success'))<div class="library-message" role="status">{{ session('success') }}</div>@endif
@if($errors->any())<div class="library-errors" role="alert">{{ $errors->first() }}</div>@endif
<section class="library-form-card">
<h2>1. Categorieën</h2>
@foreach($categories as $category)
<form class="tax-row" method="post" action="{{ route('beheer.incident-release.save') }}">@csrf
<input type="hidden" name="kind" value="category"><input type="hidden" name="id" value="{{ $category->id }}">
<label>Naam<input name="naam" value="{{ $category->naam }}" required maxlength="120"></label>
<label>Volgorde<input type="number" name="portal_sort" min="0" max="99999" value="{{ $category->portal_sort }}" required></label>
<label class="tax-check">Actief<input type="hidden" name="portal_active" value="0"><input type="checkbox" name="portal_active" value="1" @checked($category->portal_active)></label>
<span>{{ $types->where('meldingscategorieId',$category->id)->count() }} soorten</span>
<button class="library-button library-button-secondary">Opslaan</button>
</form>
@endforeach
<form class="tax-row tax-new" method="post" action="{{ route('beheer.incident-release.save') }}">@csrf
<input type="hidden" name="kind" value="category"><label>Nieuwe categorie<input name="naam" required maxlength="120" placeholder="Naam categorie"></label><label>Volgorde<input type="number" name="portal_sort" min="0" max="99999" value="0" required></label><label class="tax-check">Actief<input type="hidden" name="portal_active" value="0"><input type="checkbox" name="portal_active" value="1" checked></label><span></span><button class="library-button library-button-primary">Toevoegen</button>
</form>
</section>
<section class="library-form-card">
<h2>2. Meldingssoorten en vrijgave</h2>
@foreach($categories as $category)
<div class="tax-category"><strong>{{ $category->naam }}</strong><span>{{ $category->portal_active?'Actief':'Categorie inactief' }}</span></div>
@foreach($types->where('meldingscategorieId',$category->id) as $type)
<form class="tax-row tax-type" method="post" action="{{ route('beheer.incident-release.save') }}">@csrf
<input type="hidden" name="kind" value="type"><input type="hidden" name="id" value="{{ $type->id }}">
<label>Meldingssoort<input name="naam" value="{{ $type->naam }}" maxlength="120" required></label>
<label>Categorie<select name="category_id">@foreach($categories as $option)<option value="{{ $option->id }}" @selected($option->id==$type->meldingscategorieId)>{{ $option->naam }}</option>@endforeach</select></label>
<label>Volgorde<input type="number" name="portal_sort" min="0" max="99999" value="{{ $type->portal_sort }}" required></label>
@foreach(['portal_active'=>'Actief','visible_student'=>'Leerling','visible_coaccount'=>'Co-account'] as $key=>$label)
<label class="tax-check">{{ $label }}<input type="hidden" name="{{ $key }}" value="0"><input type="checkbox" name="{{ $key }}" value="1" @checked($type->$key)></label>
@endforeach
<button class="library-button library-button-secondary">Opslaan</button>
</form>
@endforeach
@endforeach
@if($categories->isNotEmpty())
<form class="tax-row tax-type tax-new" method="post" action="{{ route('beheer.incident-release.save') }}">@csrf
<input type="hidden" name="kind" value="type"><label>Nieuwe meldingssoort<input name="naam" required maxlength="120" placeholder="Naam soort"></label><label>Categorie<select name="category_id" required>@foreach($categories as $option)<option value="{{ $option->id }}">{{ $option->naam }}</option>@endforeach</select></label><label>Volgorde<input type="number" name="portal_sort" min="0" max="99999" value="0" required></label>
@foreach(['portal_active'=>'Actief','visible_student'=>'Leerling','visible_coaccount'=>'Co-account'] as $key=>$label)
<label class="tax-check">{{ $label }}<input type="hidden" name="{{ $key }}" value="0"><input type="checkbox" name="{{ $key }}" value="1" @checked($key==='portal_active')></label>
@endforeach
<button class="library-button library-button-primary">Toevoegen</button>
</form>
@endif
</section>
</main>
@endsection
