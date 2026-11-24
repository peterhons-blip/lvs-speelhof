@extends('layouts.app')

@section('title', 'LVS · Leerlinggegevens')

@section('breadcrumbs')
@include('partials.breadcrumbs', ['items' => [
        ['label' => 'Dashboard', 'url' => route('home')],
        ['label' => 'LVS', 'url' => route('leerlingen.index')],
        ['label' => $leerling->voornaam . ' ' . $leerling->achternaam],
    ]])
@endsection

@section('content')
<main class="screen lvs-screen">
    <div class="lvs-page-heading">
        <div>
            <p class="portal-eyebrow">LEERLINGVOLGSYSTEEM / LEERLING</p>
            <h1>{{ $leerling->voornaam }} {{ $leerling->achternaam }}</h1>
            <p>Leerlinggegevens en overzicht van de geregistreerde meldingen.</p>
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
                                        </div>
                                        <div class="melding-badges">
                                            <span class="badge badge-soort">{{ optional($melding->soort)->naam ?? '—' }}</span>
                                        </div>
                                    </div>
                                    <p class="melding-comment">{{ $melding->comment }}</p>
                                </article>
                            @endforeach
                        </div>
                    </section>
                @endforeach
            </div>
        @endif
                                <small>Ingegeven op {{ optional($melding->created_at)->format('d/m/Y H:i') ?? '—' }}</small>
                            </div>
                            <div class="melding-badges">
                                <span class="badge badge-cat">{{ optional(optional($melding->soort)->categorie)->naam ?? '—' }}</span>
                                <span class="badge badge-soort">{{ optional($melding->soort)->naam ?? '—' }}</span>
                            </div>
                        </div>
                        <p class="melding-comment">{{ $melding->comment }}</p>
                    </article>
                @endforeach
            </div>
        @endif
    </section>
</main>
@endsection
