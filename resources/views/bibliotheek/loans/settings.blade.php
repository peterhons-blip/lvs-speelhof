@extends('layouts.app')
@section('title', 'Bibliotheekinstellingen')
@section('breadcrumbs')
    @include('partials.breadcrumbs', ['items' => [
        ['label' => 'Dashboard', 'url' => route('home')],
        ['label' => 'Bibliotheek', 'url' => route('bibliotheek.index')],
        ['label' => 'Instellingen'],
    ]])
@endsection
@section('content')
<main class="library-page library-form-page">
    <div class="library-page-head">
        <div><p class="portal-eyebrow">BIBLIOTHEEK / BEHEER</p><h1>Uitleeninstellingen</h1>
        <p>Stel de uitleentermijnen en grenzen in. Nieuwe instellingen gelden voor nieuwe uitleningen en toekomstige verlengingen.</p></div>
        <a href="{{ route('bibliotheek.loans.index') }}" class="library-button library-button-secondary">Naar uitleningen</a>
    </div>
    @if(session('success'))<div class="library-message" role="status">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="library-errors" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <form action="{{ route('bibliotheek.loans.settings.save') }}" method="post" class="library-form-card library-form-grid">
        @csrf
        @foreach([
            'loan_days' => ['Standaard uitleentermijn (dagen)', 21],
            'max_loans' => ['Maximaal openstaande uitleningen per leerling', 3],
            'max_extensions' => ['Maximaal aantal verlengingen', 2],
            'extension_days' => ['Extra dagen per verlenging', 21],
        ] as $key => [$label, $default])
            <div class="library-field">
                <label for="{{ $key }}">{{ $label }}</label>
                <input id="{{ $key }}" name="{{ $key }}" type="number" min="1" max="365" required value="{{ old($key, $settings[$key] ?? $default) }}">
            </div>
        @endforeach
        <div class="library-wide"><button type="submit" class="library-button library-button-primary">Instellingen opslaan</button></div>
    </form>
    <p class="library-help">Smartschool-herinneringen worden pas geactiveerd nadat de afzender en ontvangers per school gecontroleerd zijn. Deze instellingen versturen geen berichten.</p>
</main>
@endsection
