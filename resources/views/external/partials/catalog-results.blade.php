<div class="library-table-wrap"><table class="library-table personal-catalog-table">
<thead><tr><th>Boek</th><th>Auteur</th>
@if($can('library.availability.view'))
<th>Beschikbaarheid</th>
@endif
<th>Acties</th></tr></thead><tbody>
@forelse($books as $book)
<tr>
<td><a class="personal-catalog-title" href="{{ route($portalRoute,['section'=>'book','book'=>$book->id,'q'=>request('q'),'page'=>request('page')]) }}">{{ $book->title }}</a><small class="personal-catalog-isbn">{{ $book->isbn }}</small></td>
<td>{{ $book->authors ?: '—' }}</td>
@if($can('library.availability.view'))
<td><span class="personal-catalog-stock {{ $book->available>0?'is-available':'is-unavailable' }}">{{ $book->available>0?$book->available.' beschikbaar':'Niet beschikbaar' }}</span></td>
@endif
<td><div class="personal-catalog-actions">
<a class="library-button library-button-secondary personal-catalog-detail" href="{{ route($portalRoute,['section'=>'book','book'=>$book->id,'q'=>request('q'),'page'=>request('page')]) }}">Details <span aria-hidden="true">→</span></a>
@if($can('library.reserve'))
<form method="post" action="{{ route($submitRoute) }}">
@csrf
<input type="hidden" name="type" value="reserve"><input type="hidden" name="target_id" value="{{ $book->id }}">
<button class="library-button library-button-primary" type="submit">{{ session('portal_preview')?'Reserveren (test)':'Reserveren' }}</button>
</form>
@endif
</div></td>
</tr>
@empty
<tr><td colspan="{{ 3 + (int)$can('library.availability.view') }}">Geen boeken gevonden.</td></tr>
@endforelse
</tbody></table></div>
@include('partials.portal-pagination',['paginator'=>$books])
