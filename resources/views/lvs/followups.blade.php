@extends('layouts.app')
@section('title', 'LVS · Opvolging')
@section('breadcrumbs')
@include('partials.breadcrumbs', ['items' => [
    ['label' => 'Dashboard', 'url' => route('home')],
    ['label' => 'LVS', 'url' => route('lvs.dashboard')],
    ['label' => 'Opvolging'],
]])
@endsection
@section('content')
<main class="library-page lvs-followups-page">
    <div class="library-page-head">
        <div><p class="portal-eyebrow">LVS / LEERLINGBEGELEIDING</p><h1>Opvolging meldingen</h1>
            <p>Bekijk automatische signalen en sanctievoorstellen. Elke beslissing gebeurt door een bevoegd personeelslid; er worden nooit automatisch sancties opgelegd.</p></div>
        <a class="library-button library-button-secondary" href="{{ route('lvs.dashboard') }}">LVS-dashboard</a>
    </div>
    @if(session('success'))<div class="library-message" role="status">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="library-errors" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    @if(!$ready)
        <section class="library-form-card"><h2>Opvolging nog niet geïnstalleerd</h2>
            <p>Voer eerst de gerichte migratie <code>2026_11_24_182000_create_lvs_followup_rules.php</code> uit.</p></section>
    @else
        <section class="library-form-card">
            <div class="lvs-followups-top">
                <h2>{{ $showAll ? 'Alle behandelingen' : 'Openstaande opvolging' }}</h2>
                <a href="{{ route('lvs.followups.index', $showAll ? [] : ['alles'=>1]) }}" class="library-button library-button-secondary">{{ $showAll ? 'Alleen openstaand' : 'Toon historiek' }}</a>
            </div>
            <p class="library-help">{{ $events->total() }} {{ $showAll ? 'geregistreerde opvolgingen' : 'openstaande opvolgingen' }}. Een sanctievoorstel is uitsluitend een signaal om de situatie te beoordelen.</p>
            <div class="lvs-followups-grid">
                @forelse($events as $entry)
                    <article class="lvs-followup-card">
                        <div class="lvs-followups-top">
                            <div>
                                <strong>{{ $entry->rule_title ?: 'Opvolgregel' }}</strong>
                                <p>{{ $entry->action === 'sanction_proposal' ? 'Sanctievoorstel' : 'Opvolging leerlingbegeleiding' }}
                                    · {{ $entry->created_at }}</p>
                            </div>
                            <span class="lvs-rule-state {{ $entry->status === 'pending' ? 'is-off' : 'is-on' }}">
                                {{ ['pending'=>'Te beoordelen','resolved'=>'Behandeld','dismissed'=>'Niet opgevolgd'][$entry->status] ?? $entry->status }}
                            </span>
                        </div>
                        <p><strong>Leerling:</strong>
                            @if($canSeeStudents)
                                <a class="student-photo-inline" href="{{ route('leerlingen.show', $entry->leerling_id) }}">@include('partials.student-photo',['studentId'=>$entry->leerling_id])<span>{{ $entry->voornaam }} {{ $entry->naam }}</span></a>
                            @else
                                {{ $entry->voornaam }} {{ $entry->naam }}
                            @endif
                            · {{ $entry->klasnaam ?: 'Geen klas' }}
                        </p>
                        @if($entry->status === 'pending')
                            @if(app(\App\Services\LibraryAccess::class)->has(request(),'lvs.followups.resolve'))
<form method="post" action="{{ route('lvs.followups.resolve', $entry->id) }}" class="lvs-followup-resolution">
                                @csrf
                                <label for="followup-note-{{ $entry->id }}">Opvolging / beslissing (verplicht)</label>
                                <textarea id="followup-note-{{ $entry->id }}" name="note" maxlength="1000" minlength="5" rows="3" required placeholder="Welke stappen zijn ondernomen?"></textarea>
                                <div class="lvs-rule-summary-actions">
                                    <button type="submit" name="decision" value="resolved" class="library-button library-button-primary">Markeer als behandeld</button>
                                    <button type="submit" name="decision" value="dismissed" class="library-button library-button-secondary">Geen verdere actie</button>
                                </div>
                            </form>
@endif
                        @else
                            <p><strong>Afgehandeld door:</strong> {{ $entry->resolved_by ?: '—' }}
                                ({{ $entry->resolved_at ?: '—' }})</p>
                            <p><strong>Notitie:</strong> {{ $entry->resolution_note ?: '—' }}</p>
                        @endif
                    </article>
                @empty
                    <p>Er staan geen opvolgingen klaar.</p>
                @endforelse
            </div>
            @if($events->hasPages())<div class="lvs-pagination">{{ $events->links() }}</div>@endif
        </section>
    @endif
</main>
@endsection
