@extends('layouts.app')
@section('title','Rechten per rol')
@section('breadcrumbs')
@include('partials.breadcrumbs', ['items' => [['label'=>'Dashboard','url'=>route('home')],['label'=>'Beheer'],['label'=>'Rollen en rechten']]])
@endsection
@section('content')
<main class="library-page">
    <div class="library-page-head">
        <div><p class="portal-eyebrow">PORTAALBEHEER</p><h1>Rollen en rechten</h1>
        <p>Stel voor elke schoolrol afzonderlijk de rechten per module en per instelling in. Niet aangevinkt betekent geen toegang.</p></div>
        <a class="library-button library-button-secondary" href="{{ route('beheer.users.index') }}">Gebruikersbeheer</a>
    </div>
    @if(session('success'))<div class="library-message" role="status">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="library-errors" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <section class="library-form-card">
        <h2>ICT-coördinator</h2><p>Heeft altijd volledige beheerrechten om te voorkomen dat de toegang tot het portaal per ongeluk verloren gaat. Voor de andere rollen kun je alle rechten afzonderlijk instellen.</p>
    </section>
    @foreach($roles as $role => $title)
        @if($role !== 'ict_coordinator')
        <form class="library-form-card" method="post" action="{{ route('beheer.permissions.update') }}">
            @csrf
            <input type="hidden" name="role" value="{{ $role }}">
            <h2>{{ $title }}</h2>
            <p class="library-help">Vink expliciet aan welke onderdelen deze rol mag openen en welke acties ze mag uitvoeren.</p>
            @foreach($modules as $module)
                <fieldset class="portal-permission-group">
                    <legend>{{ $module['label'] }}</legend>
                    <div class="portal-permission-list">
                    @foreach($module['permissions'] as $permission => $label)
                        <label class="portal-permission-option">
                            <input type="checkbox" name="permissions[]" value="{{ $permission }}" @checked(in_array($permission, $stored[$role] ?? [], true))>
                            <span>{{ $label }}</span>
                        </label>
                    @endforeach
                    </div>
                </fieldset>
            @endforeach
            <button type="submit" class="library-button library-button-primary">Rechten {{ $title }} opslaan</button>
        </form>
        @endif
    @endforeach
</main>
@endsection
