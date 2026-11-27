@extends('layouts.app')

@section('title', 'Nieuwe melding')

@section('breadcrumbs')
@include('partials.breadcrumbs', ['items' => [
            ['label' => 'Dashboard', 'url' => route('home')],
            ['label' => 'LVS', 'url' => route('lvs.dashboard')],
            ['label' => 'Leerlingen', 'url' => route('leerlingen.index')],
            ['label' => $leerling->voornaam . ' ' . $leerling->achternaam, 'url' => route('leerlingen.show', $leerling->id)],
            ['label' => 'Nieuwe melding'],
        ]])
@endsection

@section('content')
    <main class="screen lvs-screen lvs-incident-create">
            <div class="lvs-page-heading">
            <div>
                <p class="portal-eyebrow">LEERLINGVOLGSYSTEEM / MELDINGEN</p>
                <h1>Nieuwe melding</h1>
                <p>Registreer een melding voor {{ $leerling->voornaam }} {{ $leerling->achternaam }}.</p>
            </div>
        </div>

        {{-- Compact overzicht van de leerling bij wie de melding hoort. --}}
        <section class="card lvs-panel lvs-context-panel lvs-photo-context">
            <div class="student-photo-inline">
                @include('partials.student-photo',['studentId'=>$leerling->id,'large'=>true])
                <div class="lvs-context-info">
                <p class="lvs-context-label">MELDING VOOR</p>
                <h2>{{ $leerling->voornaam }} {{ $leerling->achternaam }}</h2>
                <p>{{ optional($leerling->klas)->klasnaam ?? 'Geen klas' }} · {{ $leerling->gebruikersnaam ?: 'Geen gebruikersnaam' }}</p>
            </div>
            </div>
            <a href="{{ route('leerlingen.show', $leerling->id) }}" class="lvs-context-link">Bekijk leerlinggegevens →</a>
        </section>

        {{-- 2️⃣ Nieuwe melding invullen --}}
        <div class="card lvs-panel lvs-section-gap lvs-incident-form-card">
            <h2>Gegevens van de melding</h2>
            <p class="lvs-field-help">Kies een categorie en soort, en beschrijf kort wat er gebeurd is.</p>

            @if($errors->any())
                <div class="lvs-form-alert" role="alert">Controleer de invoervelden hieronder.</div>
            @endif

            <form action="{{ url('/meldingen/add/' . $leerling->id) }}" method="POST" class="melding-form" id="meldingForm">
                @csrf

                <div class="lvs-incident-fields">
                <div class="lvs-incident-field">
                {{-- Categorie --}}
                <label for="categorie_id" class="lvs-field-label">Categorie</label>
                <select name="categorie_id" id="categorie_id" required>
                    <option value="">— Kies categorie —</option>
                    @foreach($categorieen as $cat)
                        <option value="{{ $cat->id }}" @selected(old('categorie_id') == $cat->id)>
                            {{ $cat->naam }}
                        </option>
                    @endforeach
                </select>
                @error('categorie_id')
                <small style="color:#dc2626">{{ $message }}</small>
                @enderror

                </div><div class="lvs-incident-field">
                {{-- Soort --}}
                <label for="soort_id" class="lvs-field-label">Soort</label>
                <select name="soort_id" id="soort_id" required data-old="{{ old('soort_id') }}">
                    <option value="">— Kies eerst een categorie —</option>
                    @foreach($categorieen as $cat)
                        @foreach($cat->soorten as $s)
                            <option value="{{ $s->id }}" data-catid="{{ $cat->id }}" data-student="{{ (int)$s->visible_student }}" hidden disabled>
                                {{ $s->naam }}
                            </option>
                        @endforeach
                    @endforeach
                </select>
                @error('soort_id')
                <small style="color:#dc2626">{{ $message }}</small>
                @enderror

                </div><div class="lvs-incident-field lvs-incident-date">
                {{-- Gebeurd op --}}
                <label for="gebeurdop" class="lvs-field-label">Gebeurd op</label>
                <input
                    type="date"
                    name="gebeurdop"
                    id="gebeurdop"
                    required max="{{ now('Europe/Brussels')->toDateString() }}"
                    value="{{ old('gebeurdop', now('Europe/Brussels')->toDateString()) }}"
                >
                @error('gebeurdop')
                    <small style="color:#dc2626">{{ $message }}</small>
                @enderror

                </div><div class="lvs-incident-field lvs-incident-description">
                {{-- Comment --}}
                <label for="comment" class="lvs-field-label">Omschrijving</label>
                <textarea name="comment" id="comment" rows="5" minlength="5" required placeholder="Beschrijf feitelijk wat er gebeurd is…">{{ old('comment') }}</textarea>
                @error('comment')
                <small style="color:#dc2626">{{ $message }}</small>
                @enderror

                </div></div>
                <fieldset class="lvs-incident-visibility">
                    <legend>Zichtbaarheid in het portaal</legend>
                    <p class="lvs-field-help">Bepaal wie deze melding in het eigen portaal kan raadplegen.</p>
                    <input type="hidden" name="visible_student" value="0">
                    <input type="hidden" name="visible_coaccount" value="0">
                    <div class="lvs-incident-options">
                        <label class="lvs-incident-option"><input type="checkbox" id="visible_student" name="visible_student" value="1" @checked(old('visible_student',0))><span><strong>Leerling</strong><small>Zichtbaar in het leerlingenportaal</small></span></label>
                        <label class="lvs-incident-option"><input type="checkbox" id="visible_coaccount" name="visible_coaccount" value="1" @checked(old('visible_coaccount',1))><span><strong>Co-accounts</strong><small>Zichtbaar in het ouderportaal · standaard aan</small></span></label>
                    </div>
                    @if(app(\App\Services\LibraryAccess::class)->has(request(),'lvs.meldingen.confidential.create'))
                    <label class="lvs-incident-option lvs-incident-confidential"><input type="checkbox" id="vertrouwelijk" name="vertrouwelijk" value="1" @checked(old('vertrouwelijk'))><span><strong>Vertrouwelijke melding</strong><small>Alleen voor medewerkers met het afzonderlijke vertrouwelijkheidsrecht. Niet zichtbaar voor leerling of co-accounts. Geen automatische Smartschool-opvolging.</small></span></label>
                    @endif
                    <p class="lvs-field-help" id="incident-visibility-note" role="status">Vertrouwelijke meldingen worden nooit vrijgegeven.</p>
                </fieldset>
                <div class="lvs-incident-actions">
                    <a href="{{ route('meldingen.select-student') }}" class="lvs-link-button lvs-link-button-subtle">Annuleren</a>
                    <button type="submit" class="lvs-link-button lvs-link-button-primary">Melding opslaan</button>
                </div>
            </form>
        </div>

        {{-- Bestaande meldingen: uitsluitend voor rollen met leesrecht. --}}
        @if($canSeeMeldingen)
        <div class="card lvs-panel lvs-section-gap">
            <h2>Meldingen voor deze leerling</h2>

            @if($meldingen->isEmpty())
                <p class="muted">Nog geen meldingen gevonden.</p>
            @else
                <div class="meldingen-list">
                    @foreach($meldingen as $melding)
                        <div class="melding-row">
                            <div class="melding-meta">
                                <div class="melding-datum">
                                    <strong>Gebeurt op:</strong>
                                    {{ optional($melding->gebeurdop)->format('d/m/Y') ?? '—' }}
                                    <br>
                                    <small class="muted">
                                        Ingegeven op {{ optional($melding->created_at)->format('d/m/Y H:i') }}
                                    </small>
                                </div>
                                <div class="melding-badges">
                                    <span class="badge badge-cat">
                                        {{ optional(optional($melding->soort)->categorie)->naam ?? '—' }}
                                    </span>
                                    <span class="badge badge-soort">
                                        {{ optional($melding->soort)->naam ?? '—' }}
                                    </span>
                                </div>
                            </div>
                            <div class="melding-comment">
                                @if($melding->vertrouwelijk)<strong>Vertrouwelijk</strong><br>@endif
                                {{ $melding->comment }}
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        @endif

    </main>

@endsection

@push('scripts')
<script src="{{ asset('js/incident-form.js') }}?v=1" defer></script>
@endpush
