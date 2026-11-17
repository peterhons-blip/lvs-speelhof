@extends('layouts.app')

@section('title', 'Nieuwe melding')

@section('content')
    <main class="screen lvs-screen">
        @include('partials.breadcrumbs', ['items' => [
            ['label' => 'Dashboard', 'url' => route('home')],
            ['label' => 'LVS', 'url' => route('leerlingen.index')],
            ['label' => $leerling->voornaam . ' ' . $leerling->achternaam, 'url' => route('leerlingen.show', $leerling->id)],
            ['label' => 'Nieuwe melding'],
        ]])

        <div class="lvs-page-heading">
            <div>
                <p class="portal-eyebrow">LEERLINGVOLGSYSTEEM / MELDINGEN</p>
                <h1>Nieuwe melding</h1>
                <p>Registreer een melding voor {{ $leerling->voornaam }} {{ $leerling->achternaam }}.</p>
            </div>
        </div>

        {{-- 1️⃣ Leerling-info --}}
        <div class="card lvs-panel">
            <h2>{{ $leerling->voornaam }} {{ $leerling->achternaam }}</h2>
            <p><b>Klas:</b> {{ optional($leerling->klas)->klasnaam ?? '—' }}</p>
            <p><b>Gebruikersnaam:</b> {{ $leerling->gebruikersnaam }}</p>
            <p><b>Email:</b> {{ $leerling->email ?: '—' }}</p>
            <p><b>Geboortedatum:</b> {{ optional($leerling->geboortedatum)->format('d/m/Y') ?? '—' }}</p>
            <p><b>Leeftijd:</b> {{ optional($leerling->geboortedatum)->age ?? '—' }} jaar</p>
        </div>

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
                            <option value="{{ $s->id }}" data-catid="{{ $cat->id }}" style="display:none">
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

                <div class="card-footer">
                    <button type="submit" class="lvs-link-button lvs-link-button-primary">Melding opslaan</button>
                </div>
            </form>
        </div>

        {{-- 3️⃣ Bestaande meldingen (overzicht) --}}
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
                                {{ $melding->comment }}
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <br>
        <a href="{{ url('/leerlingen/' . $leerling->id) }}" class="lvs-link-button lvs-link-button-subtle">← Terug naar leerling</a>
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
            updateSoorten(); // init
        })();
    </script>
@endsection
