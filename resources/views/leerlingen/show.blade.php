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
    <div class="lvs-page-heading student-photo-inline lvs-dossier-heading">
        @include('partials.student-photo',['studentId'=>$leerling->id,'large'=>true])
        <div class="lvs-dossier-heading-info">
            <p class="portal-eyebrow">LEERLINGVOLGSYSTEEM / LEERLING</p>
            <h1>{{ $leerling->voornaam }} {{ $leerling->achternaam }}</h1>
            <p>{{ $leerling->klas?->klasnaam ?? 'Geen klas' }} · {{ $leerling->geboortedatum ? $leerling->geboortedatum->age.' jaar' : 'Leeftijd onbekend' }}</p>
            @if($mentors->isNotEmpty())<p>Klastitularis(sen): {{ $mentors->implode(', ') }}</p>@endif
            @if($canCreate)<a href="{{ route('meldingen.create',$leerling->id) }}" class="lvs-link-button lvs-link-button-primary">+ Nieuwe melding</a>@endif
            @if(!$leerling->active)<p><strong>Historisch dossier · inactieve leerling</strong> — alleen raadplegen.</p>@endif
        </div>
    </div>

    @if(session('success'))
        <div class="lvs-success" role="status">{{ session('success') }}</div>
    @endif

    <div class="lvs-dossier-tabs" data-dossier-tabs>
        @php($tabs=['algemeen'=>'Algemeen']+($leerling->active?['leerkrachten'=>'Leerkrachten en vakken']:[])+($canSeeMeldingen?['meldingen'=>'Meldingen ('.$meldingen->count().')']:[])+($canSeeFollowups?['opvolging'=>'Opvolging ('.$followups->where('action','review')->count().')','sancties'=>'Sanctievoorstellen ('.$followups->where('action','sanction_proposal')->count().')']:[])+($canManageVisit && $leerling->geboortedatum && $leerling->geboortedatum->age>=18?['administratie'=>'Administratie 18+']:[]))
        <nav class="lvs-student-tabs lvs-dossier-nav" aria-label="Onderdelen leerlingendossier">
            @foreach($tabs as $key=>$label)
            <a href="#{{ $key }}" data-dossier-tab="{{ $key }}" @if($loop->first)class="is-selected" aria-current="true"@endif>{{ $label }}</a>
            @endforeach
        </nav>
        <div id="algemeen" data-dossier-panel="algemeen">
    <section class="card lvs-panel">
        <div class="lvs-panel-heading lvs-panel-heading-wrap">
            <h2>Leerlinggegevens</h2>
        </div>
        <dl class="lvs-details-grid">
            <div><dt>Klas</dt><dd>{{ optional($leerling->klas)->klasnaam ?? '—' }}</dd></div>
            <div><dt>Gebruikersnaam</dt><dd>{{ $leerling->gebruikersnaam ?: '—' }}</dd></div>
            <div><dt>E-mailadres</dt><dd>{{ $leerling->email ?: '—' }}</dd></div>
            <div><dt>Geboortedatum</dt><dd>{{ optional($leerling->geboortedatum)->format('d/m/Y') ?? '—' }}</dd></div>
            <div><dt>Leeftijd</dt><dd>{{ optional($leerling->geboortedatum)->age !== null ? optional($leerling->geboortedatum)->age . ' jaar' : '—' }}</dd></div>
            <div><dt>Klastitularis(sen)</dt><dd>{{ $mentors->implode(', ') ?: '—' }}</dd></div>
        </dl>
    </section>


        </div>
        @if($leerling->active)
        <div id="leerkrachten" data-dossier-panel="leerkrachten">
            <section class="card lvs-panel"><h2>Klastitularissen</h2><p>{{ $mentors->implode(', ') ?: 'Geen actuele titulariskoppeling beschikbaar.' }}</p>
            <h2>Vakleerkrachten</h2><p class="lvs-field-help">Actuele individuele lesrelaties volgens Skore.</p>
            <div class="class-staff-grid">
                @forelse($teachers as $teacher)
                <div class="class-staff-item"><strong>{{ trim($teacher->voornaam.' '.$teacher->name) ?: $teacher->smartschool_gebruikersnaam }}</strong><small>{{ implode(' · ',json_decode($teacher->courses??'[]',true)?:[]) ?: 'Geen vakinformatie' }}</small></div>
                @empty<p class="lvs-empty">Geen actuele individuele lesrelaties beschikbaar.</p>@endforelse
            </div></section>
        </div>
        @endif
        @if($canSeeMeldingen)
        <div id="meldingen" data-dossier-panel="meldingen">
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

        </div>
        @endif
        @if($canSeeFollowups)
        @foreach(['opvolging'=>'review','sancties'=>'sanction_proposal'] as $key=>$action)
        <div id="{{ $key }}" data-dossier-panel="{{ $key }}">
            <section class="card lvs-panel">
                <h2>{{ $key==='sancties'?'Sanctievoorstellen':'Opvolging' }}</h2>
                <p class="lvs-field-help">{{ $key==='sancties'?'Dit zijn voorstellen, geen opgelegde sancties.':'Geregistreerde signalen en beslissingen voor deze leerling.' }}</p>
                @if(!$followupsReady)<p>Opvolging is nog niet beschikbaar.</p>@else
                @forelse($followups->where('action',$action) as $entry)
                <article class="lvs-followup-card lvs-section-gap">
                    <div class="lvs-followups-top"><strong>{{ $entry->rule_title ?: 'Opvolgregel' }}</strong><span class="lvs-rule-state {{ $entry->status==='pending'?'is-off':'is-on' }}">{{ ['pending'=>'Te beoordelen','resolved'=>'Behandeld','dismissed'=>'Niet opgevolgd'][$entry->status]??$entry->status }}</span></div>
                    <p>{{ $entry->created_at }}</p>
                    @if($entry->status!=='pending')
                    <p><strong>Beslissing:</strong> {{ $entry->resolution_note ?: '—' }}</p><small>{{ $entry->resolved_by }} · {{ $entry->resolved_at }}</small>
                    @elseif($leerling->active && app(\App\Services\LibraryAccess::class)->has(request(),'lvs.followups.resolve'))
                    <form method="post" action="{{ route('lvs.followups.resolve',$entry->id) }}" class="lvs-followup-resolution">@csrf
                        <label for="dossier-note-{{ $entry->id }}">Opvolging / beslissing</label><textarea id="dossier-note-{{ $entry->id }}" name="note" minlength="5" maxlength="1000" required rows="3"></textarea>
                        <div class="lvs-rule-summary-actions"><button type="submit" name="decision" value="resolved" class="library-button library-button-primary">Markeer als behandeld</button><button type="submit" name="decision" value="dismissed" class="library-button library-button-secondary">Geen verdere actie</button></div>
                    </form>
                    @endif
                </article>
                @empty<p class="lvs-empty">Geen geregistreerde {{ $key==='sancties'?'sanctievoorstellen':'opvolgingen' }}.</p>@endforelse
                @endif
            </section>
        </div>
        @endforeach
        @endif
        @if($canManageVisit && $leerling->geboortedatum && $leerling->geboortedatum->age>=18)
        <div id="administratie" data-dossier-panel="administratie">
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

        </div>
        @endif
    </div>
</main>
@endsection
@push('scripts')
<script src="{{ asset('js/student-dossier.js') }}?v=1" defer></script>
@endpush
