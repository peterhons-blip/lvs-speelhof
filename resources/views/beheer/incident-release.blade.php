@extends('layouts.app')
@section('title','Meldingsvrijgave')
@section('breadcrumbs')
@include('partials.breadcrumbs',['items'=>[
    ['label'=>'Dashboard','url'=>route('home')],
    ['label'=>'Meldingsvrijgave'],
]])
@endsection
@section('content')
<style>
.release-page .release-check { appearance:auto; width:20px; height:20px; accent-color:#006e80; cursor:pointer; vertical-align:middle; margin:0; }
.release-page .release-check:focus-visible { outline:3px solid #74bdc9; outline-offset:3px; }
.release-page .release-choice { display:inline-flex; gap:8px; align-items:center; justify-content:center; min-height:36px; cursor:pointer; }
.release-page .release-center { text-align:center; width:160px; }
.release-page .release-category td { background:#edf4f7; font-weight:700; color:#17365d; }
.release-page .release-explanation { background:#eef6f8; border-left:4px solid #008599; padding:14px 18px; border-radius:8px; margin-bottom:20px; }
.release-page .release-explanation p { margin:5px 0; }
.release-page .release-actions { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px; margin-top:20px; }
.release-page .release-count { color:#52677a; font-size:13px; font-weight:400; display:block; margin-top:4px; }
</style>
<main class="library-page release-page">
    <div class="library-page-head">
        <div><p class="portal-eyebrow">PORTAALBEHEER / LVS</p><h1>Meldingsvrijgave</h1><p>Bepaal wie nieuwe LVS-meldingen standaard mag bekijken.</p></div>
    </div>
    <div class="release-explanation">
        <strong>Hoe werkt dit?</strong>
        <p>Vink per meldingssoort aan of een nieuwe melding standaard zichtbaar mag zijn voor de leerling en/of de co-accounts.</p>
        <p>De leerkracht kan deze keuze aanpassen bij het registreren van een melding. Vertrouwelijke meldingen blijven altijd intern. Bestaande meldingen worden niet automatisch aangepast.</p>
        <p>De gebruiker moet daarnaast over het juiste LVS-leesrecht beschikken.</p>
    </div>
    @if(session('success'))<div class="library-message" role="status">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="library-errors" role="alert">{{ $errors->first() }}</div>@endif
    <section class="library-form-card">
        <form method="post" action="{{ route('beheer.incident-release.save') }}" id="release-form">@csrf
            <div class="library-table-wrap"><table class="library-table">
                <thead><tr><th>Meldingssoort</th>
                    @foreach(['student'=>'Leerling','coaccount'=>'Co-account'] as $key=>$label)
                        <th class="release-center">
                            {{ $label }}
                            <label class="release-choice"><input class="release-check" type="checkbox" data-release-all="{{ $key }}"> Alles</label>
                            <span class="release-count" data-release-count="{{ $key }}"></span>
                        </th>
                    @endforeach
                </tr></thead>
                <tbody>
                @foreach($types->groupBy('categorie') as $category=>$rows)
                    <tr class="release-category"><td colspan="3">{{ $category ?: 'Zonder categorie' }}</td></tr>
                    @foreach($rows as $type)
                    <tr><td>{{ $type->naam }}</td>
                        <td class="release-center"><input type="hidden" name="rights[{{ $type->id }}][student]" value="0"><label class="release-choice"><input class="release-check" type="checkbox" data-release="student" name="rights[{{ $type->id }}][student]" value="1" @checked(old('rights.'.$type->id.'.student',$type->visible_student)) aria-label="Leerling: {{ $type->naam }}"></label></td>
                        <td class="release-center"><input type="hidden" name="rights[{{ $type->id }}][coaccount]" value="0"><label class="release-choice"><input class="release-check" type="checkbox" data-release="coaccount" name="rights[{{ $type->id }}][coaccount]" value="1" @checked(old('rights.'.$type->id.'.coaccount',$type->visible_coaccount)) aria-label="Co-account: {{ $type->naam }}"></label></td>
                    </tr>
                    @endforeach
                @endforeach
                </tbody>
            </table></div>
            <div class="release-actions"><span class="library-help">Wijzigingen gelden uitsluitend voor nieuwe meldingen.</span><button class="library-button library-button-primary" type="submit">Instellingen opslaan</button></div>
        </form>
    </section>
</main>
<script>
(() => {
    const form=document.getElementById('release-form');
    for(const all of form.querySelectorAll('[data-release-all]')){
        const key=all.dataset.releaseAll;
        const boxes=Array.from(form.querySelectorAll('[data-release="'+key+'"]'));
        const count=form.querySelector('[data-release-count="'+key+'"]');
        const refresh=()=>{
            const n=boxes.filter(box=>box.checked).length;
            all.checked=boxes.length>0 && n===boxes.length;
            all.indeterminate=n>0 && n<boxes.length;
            all.disabled=boxes.length===0;
            count.textContent=n+' van '+boxes.length+' geselecteerd';
        };
        all.addEventListener('change',()=>{boxes.forEach(box=>box.checked=all.checked);refresh();});
        boxes.forEach(box=>box.addEventListener('change',refresh));
        refresh();
    }
})();
</script>
@endsection
