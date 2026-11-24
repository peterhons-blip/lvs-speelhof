@extends('layouts.app')
@section('title', 'Smartschool-berichten bibliotheek')
@section('breadcrumbs')
    @include('partials.breadcrumbs', ['items' => [
        ['label' => 'Dashboard', 'url' => route('home')],
        ['label' => 'Bibliotheek', 'url' => route('bibliotheek.index')],
        ['label' => 'Smartschool-berichten'],
    ]])
@endsection
@section('content')
<main class="library-page library-form-page">
    <div class="library-page-head">
        <div>
            <p class="portal-eyebrow">BIBLIOTHEEK / SMARTSCHOOL</p>
            <h1>Smartschool-berichten</h1>
            <p>Pas het onderwerp en de tekst aan die leerlingen en personeelsleden ontvangen. Alle berichten vertrekken vanuit account <strong>bib</strong>.</p>
        </div>
        <a class="library-button library-button-secondary" href="{{ route('bibliotheek.index') }}">Terug naar bibliotheek</a>
    </div>
    @if(session('success'))<div class="library-message" role="status">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="library-errors" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <form method="post" action="{{ route('bibliotheek.messages.update') }}" class="library-message-editor">
        @csrf
        <section class="library-form-card">
            <h2>Verzending</h2>
            <div class="library-field">
                <label for="smartschool_enabled">Smartschool-berichten</label>
                <select id="smartschool_enabled" name="smartschool_enabled">
                    <option value="0" @selected(old('smartschool_enabled', $enabled ? '1' : '0') === '0')>Uitgeschakeld</option>
                    <option value="1" @selected(old('smartschool_enabled', $enabled ? '1' : '0') === '1')>Ingeschakeld (afzender bib)</option>
                </select>
                <p class="library-help">Bij ingeschakelde verzending gaan bevestigingen automatisch naar de ontlener bij uitlening en terugbrengen. Herinneringen verlopen volgens de uitleeninstellingen en de geplande taak. Handmatige herinneringen worden alleen verzonden wanneer je op de knop klikt.</p>
            </div>
        </section>
        <section class="library-form-card">
            <h2>Variabelen voor berichten</h2>
            <p>Deze velden worden automatisch ingevuld voor iedere uitlening. Gebruik exact de volgende schrijfwijze:</p>
            <p><code>{voornaam}</code> · <code>{boek}</code> · <code>{exemplaarcode}</code> · <code>{inleverdatum}</code></p>
            <p class="library-help">Je schrijft gewone tekst; nieuwe regels worden behouden. HTML wordt niet uitgevoerd. Het opslaan van berichten verstuurt zelf niets.</p>
        </section>
        @foreach($templates as $type => $template)
            <section class="library-form-card library-message-template">
                <h2>{{ $template['label'] }}</h2>
                <div class="library-field">
                    <label for="subject-{{ $type }}">Onderwerp</label>
                    <input id="subject-{{ $type }}" name="templates[{{ $type }}][subject]" type="text" maxlength="200"
                        required value="{{ old('templates.'.$type.'.subject', $template['subject']) }}">
                </div>
                <div class="library-field">
                    <label for="body-{{ $type }}">Berichttekst</label>
                    <textarea id="body-{{ $type }}" name="templates[{{ $type }}][body]" rows="9"
                        maxlength="5000" required>{{ old('templates.'.$type.'.body', $template['body']) }}</textarea>
                </div>
            </section>
        @endforeach
        <div class="library-message-editor-actions">
            <button type="submit" class="library-button library-button-primary">Alle berichten opslaan</button>
        </div>
    </form>
</main>
@endsection
