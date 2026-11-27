<div class="class-roster">
@forelse($students as $student)
<div class="class-roster-row student-photo-inline" data-pupil-row>
    @include('partials.student-photo',['studentId'=>$student->id])
    <div class="my-pupil-info"><a href="{{ route('leerlingen.show',$student->id) }}"><strong>{{ $student->naam }} {{ $student->voornaam }}</strong></a>
    @php($subjects=json_decode($courses->get($student->id,'[]'),true)?:[])
    @if($subjects)<small>{{ implode(' · ',$subjects) }}</small>@endif
    </div>
    @if($canCreate)<a class="my-pupil-action" href="{{ route('meldingen.create',$student->id) }}">+ Melding</a>@endif
</div>
@empty
<p>Geen individuele lesleerlingen gevonden. Controleer de Skore-synchronisatie.</p>
@endforelse
</div>
