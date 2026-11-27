<div class="class-roster">
@forelse($students as $student)
    <div class="class-roster-row student-photo-inline">
        @include('partials.student-photo',['studentId'=>$student->id])
        <strong>{{ $student->naam }} {{ $student->voornaam }}</strong>
        @if($canOpen)<a href="{{ route('beheer.students.show',$student->id) }}">Beheer</a>@endif
        @if($canLvs)<a href="{{ route('leerlingen.show',$student->id) }}">LVS-dossier</a>@endif
    </div>
@empty
    <p>Geen actieve leerlingen.</p>
@endforelse
</div>
