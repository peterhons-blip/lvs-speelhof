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
    <main class="screen lvs-screen">
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
        <div class="card lvs-panel lvs-section-gap">
            <h2>Nieuwe melding</h2>
            <p class="lvs-field-help">Vul alle velden in om de melding te registreren.</p>

            @if($errors->any())
                <div class="lvs-form-alert" role="alert">Controleer de invoervelden hieronder.</div>
            @endif

            <form action="{{ url('/meldingen/add/' . $leerling->id) }}" method="POST" class="melding-form" id="meldingForm">
                @csrf

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

                {{-- Soort --}}
                <label for="soort_id" class="lvs-field-label">Soort</label>
                <select name="soort_id" id="soort_id" required data-old="{{ old('soort_id') }}">
                    <option value="">— Kies eerst een categorie —</option>
                    @foreach($categorieen as $cat)
                        @foreach($cat->soorten as $s)
                            <option value="{{ $s->id }}" data-catid="{{ $cat->id }}" data-student="{{ (int)$s->visible_student }}" data-coaccount="{{ (int)$s->visible_coaccount }}" style="display:none">
                                {{ $s->naam }}
                            </option>
                        @endforeach
                    @endforeach
                </select>
                @error('soort_id')
                <small style="color:#dc2626">{{ $message }}</small>
                @enderror

                {{-- Gebeurd op --}}
                <label for="gebeurdop" class="lvs-field-label">Gebeurd op</label>
                <input
                    type="date"
                    name="gebeurdop"
                    id="gebeurdop"
                    required
                    value="{{ old('gebeurdop', now('Europe/Brussels')->toDateString()) }}"
                >
                @error('gebeurdop')
                    <small style="color:#dc2626">{{ $message }}</small>
                @enderror

                {{-- Comment --}}
                <label for="comment" class="lvs-field-label">Omschrijving</label>
                <textarea name="comment" id="comment" rows="5" required>{{ old('comment') }}</textarea>
                @error('comment')
                <small style="color:#dc2626">{{ $message }}</small>
                @enderror

                <fieldset style="padding:12px;margin:12px 0"><legend>Zichtbaarheid in het portaal</legend>
                    <label><input type="checkbox" id="visible_student" name="visible_student" value="1" @checked(old('visible_student'))> Zichtbaar voor leerling</label>
                    <label><input type="checkbox" id="visible_coaccount" name="visible_coaccount" value="1" @checked(old('visible_coaccount'))> Zichtbaar voor co-accounts</label>
                    <p class="library-help">Vertrouwelijke meldingen worden nooit vrijgegeven.</p></fieldset>
                <div class="card-footer">
                    @if(app(\App\Services\LibraryAccess::class)->has(request(),'lvs.meldingen.confidential.create'))
                        <label><input type="checkbox" name="vertrouwelijk" value="1" @checked(old('vertrouwelijk'))> Vertrouwelijke melding</label>
                        <p class="library-help">Alleen zichtbaar met het afzonderlijke vertrouwelijkheidsrecht. Geen automatische Smartschool-opvolging.</p>
                    @endif
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
        <br>
        <a href="{{ route('meldingen.select-student') }}" class="lvs-link-button lvs-link-button-subtle">← Terug naar leerlingen kiezen</a>
    </main>

    {{-- 4️⃣ JavaScript – filter soorten op gekozen categorie --}}
    <script>
        (function(){
            const catSel = document.getElementById('categorie_id');
            const soortSel = document.getElementById('soort_id');
            const allOpts = Array.from(soortSel.querySelectorAll('option[value]'));
            const oldSoort = soortSel.dataset.old;

            function updateSoorten() {
                const catId = catSel.value;
                soortSel.value = '';
                allOpts.forEach(o => o.style.display = 'none');

                if (catId) {
                    const visible = allOpts.filter(o => o.dataset.catid === catId);
                    visible.forEach(o => o.style.display = '');
                    if (oldSoort) {
                        const old = visible.find(o => o.value === oldSoort);
                        if (old) soortSel.value = old.value;
                    }
                }
            }

            catSel.addEventListener('change', updateSoorten);
            function defaults(){
                const o=soortSel.selectedOptions[0];
                document.getElementById('visible_student').checked=o?.dataset.student==='1';
                document.getElementById('visible_coaccount').checked=o?.dataset.coaccount==='1';
            }
            soortSel.addEventListener('change',defaults);
            catSel.addEventListener('change',defaults);
            updateSoorten(); // init
            @unless(old('soort_id')) defaults(); @endunless
        })();
    </script>
@endsection
