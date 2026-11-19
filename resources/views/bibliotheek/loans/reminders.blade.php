@extends('layouts.app')
@section('title', 'Herinneringen per uitlening')
@section('breadcrumbs')
    @include('partials.breadcrumbs', ['items' => [
        ['label' => 'Dashboard', 'url' => route('home')],
        ['label' => 'Bibliotheek', 'url' => route('bibliotheek.index')],
        ['label' => 'Uitleningen', 'url' => route('bibliotheek.loans.index')],
        ['label' => 'Herinneringen'],
    ]])
@endsection
@section('content')
<main class="library-page library-form-page">
    <div class="library-page-head">
        <div><p class="portal-eyebrow">BIBLIOTHEEK / UITLEENBEHEER</p><h1>Herinneringen aanpassen</h1>
        <p>{{ $item->title }} — {{ $item->barcode }}</p></div>
        <a class="library-button library-button-secondary" href="{{ route('bibliotheek.loans.index') }}">Terug naar uitleningen</a>
    </div>
    @if($errors->any())<div class="library-errors" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <form method="post" action="{{ route('bibliotheek.loans.reminders.update', $item->id) }}" class="library-form-card">
        @csrf
        @method('PUT')
        <div class="library-field">
            <label for="due_date">Uiterste inleverdatum</label>
            <input type="date" id="due_date" name="due_date" required min="{{ now('Europe/Brussels')->toDateString() }}"
                   value="{{ old('due_date', $item->due_date) }}">
            <p class="library-help">Hier kun je de inleverdatum voor alleen deze uitlening aanpassen.</p>
        </div>
        <div class="library-field">
            <label for="reminder_days_before">Aantal dagen vooraf</label>
            <input type="number" min="0" max="60" id="reminder_days_before" name="reminder_days_before"
                   value="{{ old('reminder_days_before', $item->reminder_days_before) }}" placeholder="Leeg = globale instelling">
            <p class="library-help">Leeg: globale instelling. Nul: geen voorafgaande herinnering.</p>
        </div>
        <div class="library-field">
            <label for="remind_on_due_date">Bericht op de uiterste inleverdatum</label>
            <select id="remind_on_due_date" name="remind_on_due_date">
                <option value="" @selected(old('remind_on_due_date', $item->remind_on_due_date) === null || old('remind_on_due_date', $item->remind_on_due_date) === '')>Globale instelling</option>
                <option value="1" @selected((string) old('remind_on_due_date', $item->remind_on_due_date) === '1')>Ja</option>
                <option value="0" @selected((string) old('remind_on_due_date', $item->remind_on_due_date) === '0')>Neen</option>
            </select>
        </div>
        <button type="submit" class="library-button library-button-primary">Opslaan</button>
    </form>
</main>
@endsection
