<?php

namespace App\Http\Controllers;

use App\Models\LibraryBook;
use App\Models\LibraryCopy;
use App\Services\LibraryIsbnLookup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LibraryBookController extends Controller
{
    public function index(Request $request): View
    {
        $query = trim((string) $request->query('q', ''));
        $books = LibraryBook::query()
            ->withCount(['copies', 'copies as available_copies_count' => fn ($q) => app(\App\Services\LibraryReservations::class)->available($q->where('status', 'beschikbaar'))])
            ->when($query !== '', function ($q) use ($query) {
                $q->where(function ($sub) use ($query) {
                    $sub->where('title', 'like', '%'.$query.'%')
                        ->orWhere('isbn', 'like', '%'.$query.'%')
                        ->orWhere('authors', 'like', '%'.$query.'%')
                        ->orWhereHas('copies', fn ($copies) => $copies->where('barcode', 'like', '%'.$query.'%'));
                });
            })
            ->orderBy('title')
            ->paginate(15)->withQueryString();

        return view('bibliotheek.books.index', compact('books', 'query'));
    }

    public function qrLabels(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        $bookId = $request->query('book');
        $copyId = $request->query('copy');
        if ($copyId !== null && (! ctype_digit((string) $copyId) || (int) $copyId < 1)) abort(404);
        $qrStatus = (string) $request->query('qr_status', 'all');
        if (! in_array($qrStatus, ['all', 'unprinted', 'printed', 'not_generated', 'generated_unprinted'], true)) $qrStatus = 'all';
        $todayOnly = $request->boolean('today');
        $sort = $request->query('sort') === 'registration' ? 'registration' : 'title';
        $mode = (string) $request->query('select', 'all');
        if (! in_array($mode, ['all', 'latest5', 'next21', 'selected'], true)) $mode = 'all';
        if ($bookId !== null && (! ctype_digit((string) $bookId) || (int) $bookId < 1)) abort(404);

        $query = DB::table('library_copies as copies')
            ->join('library_books as books', 'books.id', '=', 'copies.book_id');
        if ($copyId !== null) {
            $query->where('copies.id', (int) $copyId);
        }
        if ($bookId !== null) {
            $query->where('books.id', (int) $bookId);
        } elseif ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('books.title', 'like', '%'.$search.'%')
                    ->orWhere('books.isbn', 'like', '%'.$search.'%')
                    ->orWhere('books.authors', 'like', '%'.$search.'%')
                    ->orWhere('copies.barcode', 'like', '%'.$search.'%');
            });
        }

        if ($todayOnly) $query->whereDate('copies.created_at', now()->toDateString());
        if ($qrStatus === 'unprinted') $query->whereNull('copies.qr_printed_at');
        if ($qrStatus === 'printed') $query->whereNotNull('copies.qr_printed_at');
        if ($qrStatus === 'not_generated') $query->whereNull('copies.qr_generated_at');
        if ($qrStatus === 'generated_unprinted') {
            $query->whereNotNull('copies.qr_generated_at')->whereNull('copies.qr_printed_at');
        }

        if ($mode === 'latest5') {
            $query->orderByDesc('copies.created_at')->orderByDesc('copies.id');
        } elseif ($mode === 'next21' || $sort === 'registration') {
            $query->orderBy('copies.id');
        } else {
            $query->orderBy('books.title')->orderBy('copies.barcode');
        }

        $total = (clone $query)->count();
        $copies = $query->limit($mode === 'latest5' ? 5 : ($mode === 'next21' ? 21 : 500))
            ->get(['copies.id', 'copies.barcode', 'books.title', 'copies.created_at', 'copies.qr_printed_at', 'copies.qr_generated_at']);

        return view('bibliotheek.books.qr-labels', compact('copies', 'total', 'search', 'bookId', 'copyId', 'mode', 'qrStatus', 'todayOnly', 'sort'));
    }

    public function recordQrGenerated(Request $request): JsonResponse
    {
        app(\App\Services\LibraryAccess::class)->requirePermission($request, 'bibliotheek.qr.track');
        $data = $request->validate([
            'copies' => ['required', 'array', 'min:1', 'max:500'],
            'copies.*' => ['required', 'integer', 'min:1', 'distinct'],
        ]);
        $count = DB::table('library_copies')->whereIn('id', array_map('intval', $data['copies']))
            ->whereNull('qr_generated_at')
            ->update(['qr_generated_at' => now(), 'updated_at' => now()]);
        return response()->json(['recorded' => $count]);
    }

    public function confirmQrPrinted(Request $request): RedirectResponse
    {
        app(\App\Services\LibraryAccess::class)->requirePermission($request, 'bibliotheek.qr.track');
        $data = $request->validate([
            'copies' => ['required', 'array', 'min:1', 'max:500'],
            'copies.*' => ['required', 'integer', 'min:1', 'distinct'],
            'printed' => ['required', 'in:0,1'],
        ]);
        $ids = array_map('intval', $data['copies']);
        $printed = $data['printed'] === '1';
        $values = [
            'qr_printed_at' => $printed ? now() : null,
            'qr_printed_by' => $printed
                ? app(\App\Services\LibraryAccess::class)->username($request) : null,
            'updated_at' => now(),
        ];
        $values['qr_generated_at'] = $printed
            ? DB::raw('COALESCE(qr_generated_at, CURRENT_TIMESTAMP)') : null;
        $count = DB::table('library_copies')->whereIn('id', $ids)->update($values);

        return back()->with('success', $count.' exemplaren '.($printed
            ? 'gemarkeerd als voorzien van QR-etiket.' : 'waarvan de volledige QR-status is gewist.'));
    }

    public function create(Request $request): View
    {
        $today = now()->toDateString();
        return view('bibliotheek.books.create', [
            'suggestedBarcode' => LibraryCopy::generateBarcode(),
            'todayCopies' => LibraryCopy::whereDate('created_at', $today)->count(),
            'todayBooks' => LibraryBook::whereDate('created_at', $today)->count(),
            'todayUnprinted' => LibraryCopy::whereDate('created_at', $today)->whereNull('qr_printed_at')->count(),
        ]);
    }

    /**
     * Limited connectivity check for librarians debugging book lookups on live.
     * Reports only HTTP status/availability, never API credentials.
     */
    public function lookupDiagnostics(Request $request): JsonResponse
    {
        app(\App\Services\LibraryAccess::class)->requirePermission($request, 'bibliotheek.lookup.diagnostics');
        $results = [];
        foreach ([
            'Open Library' => ['https://openlibrary.org/search.json', ['title' => 'test', 'limit' => 1]],
            'Google Books' => ['https://www.googleapis.com/books/v1/volumes', ['q' => 'intitle:test', 'maxResults' => 1]],
        ] as $provider => [$url, $params]) {
            try {
                if ($provider === 'Google Books' && config('services.google_books.api_key')) $params['key'] = config('services.google_books.api_key');
                $client = $provider === 'Open Library'
                    ? LibraryIsbnLookup::openLibraryHttp(15)
                    : \Illuminate\Support\Facades\Http::acceptJson()->timeout(10);
                $response = $client->get($url, $params);
                $results[$provider] = [
                    'reachable' => $response->successful(),
                    'status' => $response->status(),
                    'message' => $response->successful() ? 'Bereikbaar' : 'HTTP-fout bij de externe dienst',
                ];
            } catch (\Throwable $error) {
                \Illuminate\Support\Facades\Log::warning('Bibliotheek live opzoektest mislukt', [
                    'provider' => $provider, 'error_type' => get_class($error),
                ]);
                $results[$provider] = [
                    'reachable' => false, 'status' => null,
                    'message' => 'Niet bereikbaar vanaf deze server (controleer verbinding, DNS, TLS en firewall).',
                ];
            }
        }
        return response()->json(['providers' => $results]);
    }

    public function lookup(Request $request, LibraryIsbnLookup $lookup): JsonResponse
    {
        $isbn = $this->normalizedIsbn((string) $request->query('isbn', ''));
        if (! $this->validIsbn($isbn)) {
            return response()->json(['message' => 'Voer een geldig ISBN-10 of ISBN-13 in.'], 422);
        }

        $existing = LibraryBook::where('isbn', $isbn)->first();
        if ($existing) {
            return response()->json([
                'book' => $existing->only(['isbn', 'title', 'subtitle', 'authors', 'publisher',
                    'published_year', 'category', 'description', 'cover_url']) + ['source' => 'Eigen bibliotheek'],
                'existing_book' => true,
            ]);
        }

        $book = $lookup->findEnriched($isbn);
        if ($book === null) {
            return response()->json(['message' => 'Geen gegevens gevonden. Vul het boek handmatig in.'], 404);
        }

        return response()->json(['book' => $book]);
    }

    public function searchExternal(Request $request, LibraryIsbnLookup $lookup): JsonResponse
    {
        $data = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:120'],
            'type' => ['nullable', Rule::in(['title', 'author'])],
            'source' => ['nullable', Rule::in(['auto', 'openlibrary', 'google'])],
        ]);

        $source = $data['source'] ?? 'auto';
        $type = $data['type'] ?? 'title';
        $query = trim($data['q']);
        $books = $source === 'google' ? [] : $lookup->searchByName($query, $type);
        if ($source === 'google' || ($source === 'auto' && $books === [])) {
            $books = $lookup->searchGoogleBooksByName($query, $type);
        }

        return response()->json([
            'books' => $books,
            'source' => $books !== [] ? (($books[0]['source'] ?? 'Open Library')) : null,
        ]);
    }

    public function editions(Request $request, LibraryIsbnLookup $lookup): JsonResponse
    {
        $data = $request->validate([
            'key' => ['required', 'string', 'regex:~^/works/OL[0-9]+W$~'],
            'offset' => ['nullable', 'integer', 'min:0', 'max:10000'],
        ]);

        return response()->json($lookup->editions($data['key'], (int) ($data['offset'] ?? 0)));
    }

    public function workDetails(Request $request, LibraryIsbnLookup $lookup): JsonResponse
    {
        $data = $request->validate([
            'key' => ['required', 'string', 'regex:~^/works/OL[0-9]+W$~'],
        ]);

        return response()->json($lookup->workDetails($data['key']));
    }

    public function store(Request $request): RedirectResponse
    {
        $input = $request->all();
        $input['isbn'] = $this->normalizedIsbn((string) ($input['isbn'] ?? ''));
        $input['barcode'] = trim((string) ($input['barcode'] ?? ''));
        if ($input['barcode'] === '') {
            $input['barcode'] = LibraryCopy::generateBarcode();
        }

        $isbn = $input['isbn'];
        if ($isbn !== '' && ! $this->validIsbn($isbn)) {
            return back()->withErrors(['isbn' => 'Ongeldig ISBN-controlecijfer.'])->withInput();
        }
        $existing = $isbn !== '' && LibraryBook::where('isbn', $isbn)->exists();

        $rules = [
            'isbn' => ['nullable', 'string', 'max:13'],
            'title' => [$existing ? 'nullable' : 'required', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'authors' => ['nullable', 'string', 'max:255'],
            'publisher' => ['nullable', 'string', 'max:255'],
            'published_year' => ['nullable', 'string', 'max:20'],
            'category' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'cover_url' => ['nullable', 'string', 'max:500', 'url', 'starts_with:https://covers.openlibrary.org/'],
            'barcode' => ['required', 'string', 'max:60', Rule::unique('library_copies', 'barcode')],
            'shelf_location' => ['nullable', 'string', 'max:120'],
            'condition' => ['required', Rule::in(['nieuw', 'goed', 'gebruikt', 'beschadigd'])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
        // Existing editions keep their stored metadata; only validate copy fields.
        if ($existing) {
            foreach (['title', 'subtitle', 'authors', 'publisher', 'published_year',
                'category', 'description', 'cover_url'] as $field) unset($rules[$field]);
        }
        $data = validator($input, $rules)->validate();

        $data['isbn'] = $isbn !== '' ? $isbn : null;

        [$book, $copy, $newBook] = DB::transaction(function () use ($data, $existing) {
            $book = $data['isbn'] !== null
                ? LibraryBook::where('isbn', $data['isbn'])->first() : null;

            if (! $book) {
                if ($existing || trim((string) ($data['title'] ?? '')) === '') {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'title' => 'Het bestaande boek is niet meer beschikbaar. Vul de boekgegevens opnieuw in.',
                    ]);
                }
                $metadata = collect($data)->only([
                    'title', 'subtitle', 'authors', 'publisher', 'published_year',
                    'category', 'description', 'cover_url',
                ])->all();
                $book = $data['isbn'] !== null
                    ? LibraryBook::firstOrCreate(['isbn' => $data['isbn']], $metadata)
                    : LibraryBook::create(['isbn' => null] + $metadata);
                $newBook = $book->wasRecentlyCreated;
            } else {
                $newBook = false;
            }

            $copy = $book->copies()->create(collect($data)->only([
                'barcode', 'shelf_location', 'condition', 'notes',
            ])->all());

            return [$book, $copy, $newBook];
        });

        if ($request->boolean('next_book')) {
            return redirect()->route('bibliotheek.books.create')
                ->with('success', ($newBook ? 'Nieuw boek geregistreerd: ' : 'Extra exemplaar geregistreerd: ').$book->title)
                ->with('last_barcode', $copy->barcode);
        }

        return redirect()->route('bibliotheek.books.show', $book)
            ->with('success', $newBook ? 'Boek en eerste exemplaar geregistreerd.' : 'Extra exemplaar toegevoegd aan het bestaande boek.')
            ->with('new_copy_id', $copy->id);
    }

    public function show(LibraryBook $book): View
    {
        $book->load(['copies' => fn ($q) => $q->orderBy('barcode')]);
        return view('bibliotheek.books.show', [
            'book' => $book,
            'suggestedBarcode' => LibraryCopy::generateBarcode(),
        ]);
    }

    public function edit(LibraryBook $book): View
    {
        return view('bibliotheek.books.edit', compact('book'));
    }

    public function update(Request $request, LibraryBook $book): RedirectResponse
    {
        $input = $request->all();
        $input['isbn'] = $this->normalizedIsbn((string) ($input['isbn'] ?? ''));
        $data = validator($input, [
            'isbn' => ['nullable', 'string', 'max:13', Rule::unique('library_books', 'isbn')->ignore($book->id)],
            'title' => ['required', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'authors' => ['nullable', 'string', 'max:255'],
            'publisher' => ['nullable', 'string', 'max:255'],
            'published_year' => ['nullable', 'string', 'max:20'],
            'category' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'cover_url' => ['nullable', 'string', 'max:500', 'url', 'starts_with:https://covers.openlibrary.org/'],
        ])->validate();

        if ($data['isbn'] !== '' && ! $this->validIsbn($data['isbn'])) {
            return back()->withErrors(['isbn' => 'Ongeldig ISBN-controlecijfer.'])->withInput();
        }
        if ($data['isbn'] === '') {
            $data['isbn'] = null;
        }

        $book->update($data);
        return redirect()->route('bibliotheek.books.show', $book)->with('success', 'Boekgegevens aangepast.');
    }

    public function destroy(LibraryBook $book): RedirectResponse
    {
        return DB::transaction(function () use ($book) {
            // Lock copy rows so an active loan cannot be overlooked on deletion.
            $copies = $book->copies()->lockForUpdate()->get();

            if ($copies->contains(fn ($copy) => $copy->status !== 'beschikbaar' || app(\App\Services\LibraryReservations::class)->active()->where('reserved_copy_id',$copy->id)->exists())) {
                return back()->withErrors(['delete' => 'Verwijderen is niet mogelijk: minstens één exemplaar is niet beschikbaar of uitgeleend.']);
            }

            // Historiek behouden: ook een teruggebracht exemplaar houdt zijn uitleenregistraties.
            if (\Illuminate\Support\Facades\Schema::hasTable('library_loans')
                && $copies->isNotEmpty()
                && DB::table('library_loans')->whereIn('copy_id', $copies->pluck('id'))->exists()) {
                return back()->withErrors(['delete' => 'Verwijderen niet mogelijk: het boek heeft een uitleenhistoriek.']);
            }

            if (DB::table('library_copy_events')->whereIn('copy_id',$copies->pluck('id'))->exists() || DB::table('library_inventory_items')->whereIn('copy_id',$copies->pluck('id'))->exists()) return back()->withErrors(['delete'=>'Exemplaren hebben een historiek. Gebruik afvoeren.']);
            $book->delete();
            return redirect()->route('bibliotheek.books.index')->with('success', 'Boek en bijbehorende exemplaren verwijderd.');
        });
    }

    public function addCopy(Request $request, LibraryBook $book): RedirectResponse
    {
        $input = $request->all();
        $input['barcode'] = trim((string) ($input['barcode'] ?? ''));
        if ($input['barcode'] === '') {
            $input['barcode'] = LibraryCopy::generateBarcode();
        }

        $data = validator($input, [
            'barcode' => ['required', 'string', 'max:60', Rule::unique('library_copies', 'barcode')],
            'shelf_location' => ['nullable', 'string', 'max:120'],
            'condition' => ['required', Rule::in(['nieuw', 'goed', 'gebruikt', 'beschadigd'])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ])->validate();

        $copy = $book->copies()->create($data);

        return redirect()->route('bibliotheek.books.show', $book)
            ->with('success', 'Extra exemplaar toegevoegd.')
            ->with('new_copy_id', $copy->id);
    }

    public function editCopy(LibraryBook $book, LibraryCopy $copy): View
    {
        $copy = $book->copies()->whereKey($copy->id)->firstOrFail();

        return view('bibliotheek.books.edit-copy', compact('book', 'copy'));
    }

    public function updateCopy(Request $request, LibraryBook $book, LibraryCopy $copy): RedirectResponse
    {
        $copy = $book->copies()->whereKey($copy->id)->firstOrFail();

        $data = $request->validate([
            'barcode' => ['required', 'string', 'max:60', Rule::unique('library_copies', 'barcode')->ignore($copy->id)],
            'shelf_location' => ['nullable', 'string', 'max:120'],
            'condition' => ['required', Rule::in(['nieuw', 'goed', 'gebruikt', 'beschadigd'])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        // The loan state is never editable here; it belongs to the lending workflow.
        $copy->update($data);

        return redirect()->route('bibliotheek.books.show', $book)
            ->with('success', 'Exemplaar '.$copy->barcode.' aangepast.');
    }

    public function copyStatus(Request $request, LibraryBook $book, LibraryCopy $copy): RedirectResponse
    {
        $data=$request->validate(['status'=>'required|in:beschikbaar,beschadigd,verloren,afgevoerd','note'=>'required|string|max:2000']);
        return DB::transaction(function() use($request,$book,$copy,$data){
            $copy=$book->copies()->whereKey($copy->id)->lockForUpdate()->firstOrFail();
            if (DB::table('library_loans')->where('copy_id',$copy->id)->whereNull('returned_at')->exists() || $copy->status==='uitgeleend') {
                return back()->withErrors(['status'=>'Er staat nog een uitlening open. Sluit die eerst correct af; de ontlener blijft anders uit beeld.']);
            }
            if ($copy->status===$data['status']) return back()->with('success','Status is ongewijzigd.');
            DB::table('library_copy_events')->insert(['copy_id'=>$copy->id,'old_status'=>$copy->status,'new_status'=>$data['status'],'actor'=>app(\App\Services\LibraryAccess::class)->username($request),'note'=>$data['note'],'created_at'=>now(),'updated_at'=>now()]);
            $values=['status'=>$data['status'],'updated_at'=>now()];
            if ($data['status']==='beschadigd') $values['condition']='beschadigd';
            DB::table('library_copies')->where('id',$copy->id)->update($values);
            return back()->with('success','Exemplaarstatus aangepast; historiek behouden.');
        });
    }

    public function destroyCopy(LibraryBook $book, LibraryCopy $copy): RedirectResponse
    {
        return DB::transaction(function () use ($book, $copy) {
            // Never delete a copy belonging to another book; lock it before checking availability.
            $copy = $book->copies()->whereKey($copy->id)->lockForUpdate()->firstOrFail();

            if ($copy->status !== 'beschikbaar' || app(\App\Services\LibraryReservations::class)->active()->where('reserved_copy_id',$copy->id)->exists()) {
                return back()->withErrors([
                    'delete' => 'Dit exemplaar kan niet verwijderd worden omdat het niet beschikbaar of uitgeleend is.',
                ]);
            }

            // Bewaar uitleenhistoriek, zonder incompatibele Schema::hasColumn()-aanroepen.
            if (\Illuminate\Support\Facades\Schema::hasTable('library_loans')) {
                if (DB::table('library_loans')->where('copy_id', $copy->id)
                    ->whereNull('returned_at')->exists()) {
                    return back()->withErrors([
                        'delete' => 'Dit exemplaar kan niet verwijderd worden: er staat nog een actieve uitlening open.',
                    ]);
                }

                // Keep the historical lending records intact.
                if (DB::table('library_loans')->where('copy_id', $copy->id)->exists()) {
                    return back()->withErrors([
                        'delete' => 'Dit exemplaar heeft een uitleenhistoriek en kan daarom niet definitief verwijderd worden.',
                    ]);
                }
            }

            if (DB::table('library_copy_events')->where('copy_id',$copy->id)->exists() || DB::table('library_inventory_items')->where('copy_id',$copy->id)->exists()) return back()->withErrors(['delete'=>'Exemplaar heeft een inventaris- of statushistoriek. Gebruik afvoeren.']);
            $barcode = $copy->barcode;
            $copy->delete();

            return redirect()->route('bibliotheek.books.show', $book)
                ->with('success', 'Exemplaar '.$barcode.' verwijderd. Het boek en de andere exemplaren blijven behouden.');
        });
    }

    private function normalizedIsbn(string $value): string
    {
        return strtoupper(preg_replace('/[\s-]+/', '', trim($value)) ?? '');
    }

    private function validIsbn(string $isbn): bool
    {
        if (preg_match('/^\d{9}[\dX]$/', $isbn)) {
            $sum = 0;
            foreach (str_split($isbn) as $index => $digit) {
                $sum += (10 - $index) * ($digit === 'X' ? 10 : (int) $digit);
            }
            return $sum % 11 === 0;
        }

        if (preg_match('/^\d{13}$/', $isbn)) {
            $sum = 0;
            foreach (str_split($isbn) as $index => $digit) {
                $sum += (int) $digit * ($index % 2 === 0 ? 1 : 3);
            }
            return $sum % 10 === 0;
        }

        return false;
    }
}
