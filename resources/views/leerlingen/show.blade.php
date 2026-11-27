@extends('layouts.app')

@section('title', 'LVS · Leerlinggegevens')

@section('breadcrumbs')
@include('partials.breadcrumbs', ['items' => [
        ['label' => 'Dashboard', 'url' => route('home')],
        ['label' => 'LVS', 'url' => route('lvs.dashboard')],
        ['label' => 'Leerlingen', 'url' => route('leerlingen.index')],
        ['label' => $leerling->voornaam . ' ' . $leerling->achternaam],
    ]])
@endsection

@section('content')
<main class="screen lvs-screen">
    <div class="lvs-page-heading student-photo-inline">
        @include('partials.student-photo',['studentId'=>$leerling->id,'large'=>true])
        <div>
            <p class="portal-eyebrow">LEERLINGVOLGSYSTEEM / LEERLING</p>
            <h1>{{ $leerling->voornaam }} {{ $leerling->achternaam }}</h1>
            <p>Leerlinggegevens en overzicht van de geregistreerde meldingen.</p>
            @if(!$leerling->active)<p><strong>Historisch dossier · inactieve leerling</strong> — alleen raadplegen.</p>@endif
        </div>
    </div>

    @if(session('success'))
        <div class="lvs-success" role="status">{{ session('success') }}</div>
    @endif

    <section class="card lvs-panel">
        <div class="lvs-panel-heading lvs-panel-heading-wrap">
            <h2>Leerlinggegevens</h2>
            @if($canCreate)
                <a href="{{ route('meldingen.create', $leerling->id) }}" class="lvs-link-button lvs-link-button-primary">+ Nieuwe melding</a>
            @endif
        </div>
        <dl class="lvs-details-grid">
            <div><dt>Klas</dt><dd>{{ optional($leerling->klas)->klasnaam ?? '—' }}</dd></div>
            <div><dt>Gebruikersnaam</dt><dd>{{ $leerling->gebruikersnaam ?: '—' }}</dd></div>
            <div><dt>E-mailadres</dt><dd>{{ $leerling->email ?: '—' }}</dd></div>
            <div><dt>Geboortedatum</dt><dd>{{ optional($leerling->geboortedatum)->format('d/m/Y') ?? '—' }}</dd></div>
            <div><dt>Leeftijd</dt><dd>{{ optional($leerling->geboortedatum)->age !== null ? optional($leerling->geboortedatum)->age . ' jaar' : '—' }}</dd></div>
        </dl>
    </section>

    @if($canManageVisit && $leerling->geboortedatum && $leerling->geboortedatum->age >= 18)
    <section class="card lvs-panel lvs-section-gap lvs-secretariat-card">
        <div class="lvs-panel-heading">
            <div>
                <p class="portal-eyebrow">ADMINISTRATIEVE OPVOLGING</p>
                <h2>Secretariaatsbezoek – 18 jaar</h2>
            </div>
            <span class="lvs-visit-status {{ $adultVisit && $adultVisit->completed_at ? 'is-done' : ($adultVisit ? 'is-open' : 'is-new') }}">
                {{ $adultVisit && $adultVisit->completed_at ? 'Afgehandeld' : ($adultVisit ? 'Openstaand' : 'Niet gestart') }}
            </span>
        </div>
        <div class="lvs-visit-content">
            <div>
                <strong>Ondertekening administratieve documenten</strong>
                @if($adultVisit && $adultVisit->completed_at)
                    <p>Langsgekomen op {{ \Carbon\Carbon::parse($adultVisit->completed_at)->format('d/m/Y H:i') }} · {{ $adultVisit->completed_by }}</p>
                @elseif($adultVisit)
                    <p>De leerling moet nog langskomen bij het secretariaat.</p>
                @else
                    <p>Start de opvolging alleen als de leerling nog moet langskomen.</p>
                @endif
            </div>
            <div class="lvs-visit-actions">
                @if($adultVisit)
                    <form method="post" action="{{ route('secretariat-visits.update',$adultVisit->id) }}">
                        @csrf
                        <input type="hidden" name="completed" value="{{ $adultVisit->completed_at ? '0' : '1' }}">
                        <button type="submit" class="lvs-visit-button {{ $adultVisit->completed_at ? 'is-secondary' : '' }}">{{ $adultVisit->completed_at ? 'Heropenen' : '✓ Langsgekomen' }}</button>
                    </form>
                @else
                    <form method="post" action="{{ route('secretariat-visits.register',$leerling->id) }}">
                        @csrf
                        <button type="submit" class="lvs-visit-button">+ Opvolging starten</button>
                    </form>
                @endif
                <a class="lvs-visit-overview" href="{{ route('secretariat-visits.index') }}">Alle bezoeken bekijken <span aria-hidden="true">→</span></a>
            </div>
        </div>
    </section>
    @endif
    @if($canSeeMeldingen)
    <section class="card lvs-panel lvs-section-gap">
        <div class="lvs-panel-heading">
            <div><p class="portal-eyebrow">OPVOLGING</p><h2>Meldingen</h2></div>
            <span class="lvs-count-pill">{{ $meldingen->count() }}</span>
        </div>
        @if($meldingen->isEmpty())
            <p class="lvs-empty">Er zijn nog geen meldingen geregistreerd voor deze leerling.</p>
        @else
            <div class="lvs-category-groups">
                @foreach($meldingGroepen as $categoryName => $items)
                    <section class="lvs-category-group" aria-label="{{ $categoryName }}">
                        <div class="lvs-category-title">
                            <h3>{{ $categoryName }}</h3>
                            <span class="lvs-count-pill">{{ $items->count() }}</span>
                        </div>
                        <div class="meldingen-list">
                            @foreach($items as $melding)
                                <article class="melding-row">
                                    <div class="melding-meta">
                                        <div class="melding-datum">
                                            <strong>Gebeurd op {{ optional($melding->gebeurdop)->format('d/m/Y') ?? '—' }}</strong><br>
                                            <small>Ingegeven op {{ optional($melding->created_at)->format('d/m/Y H:i') ?? '—' }}</small>
                                            @if($melding->auteur_gebruikersnaam ?? null)
                                                <br><small>Door {{ $melding->auteur_gebruikersnaam }}</small>
                                            @endif
                                        </div>
                                        <div class="melding-badges">
                                            <span class="badge badge-soort">{{ optional($melding->soort)->naam ?? '—' }}</span>
                                        </div>
                                    </div>
                                    <p class="melding-comment">@if($melding->vertrouwelijk)<strong>Vertrouwelijk</strong><br>@endif {{ $melding->comment }}</p>
                                </article>
                            @endforeach
                        </div>
                    </section>
                @endforeach
            </div>
        @endif
    </section>
    @endif
</main>
@endsection
