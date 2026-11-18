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
            ->withCount(['copies', 'copies as available_copies_count' => fn ($q) => $q->where('status', 'beschikbaar')])
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

    public function create(): View
    {
        return view('bibliotheek.books.create', ['suggestedBarcode' => LibraryCopy::generateBarcode()]);
    }

    public function lookup(Request $request, LibraryIsbnLookup $lookup): JsonResponse
    {
        $isbn = $this->normalizedIsbn((string) $request->query('isbn', ''));
        if (! $this->validIsbn($isbn)) {
            return response()->json(['message' => 'Voer een geldig ISBN-10 of ISBN-13 in.'], 422);
        }

        $book = $lookup->find($isbn);
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
        ]);

        $books = $lookup->searchByName(trim($data['q']), $data['type'] ?? 'title');

        return response()->json(['books' => $books]);
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

        $data = validator($input, [
            'isbn' => ['nullable', 'string', 'max:13', Rule::unique('library_books', 'isbn')],
            'title' => ['required', 'string', 'max:255'],
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
        ])->validate();

        if ($data['isbn'] !== '' && ! $this->validIsbn($data['isbn'])) {
            return back()->withErrors(['isbn' => 'Ongeldig ISBN-controlecijfer.'])->withInput();
        }
        if ($data['isbn'] === '') {
            $data['isbn'] = null;
        }

        $book = DB::transaction(function () use ($data) {
            $book = LibraryBook::create(collect($data)->only([
                'isbn','title','subtitle','authors','publisher','published_year',
                'category','description','cover_url',
            ])->all());

            $book->copies()->create(collect($data)->only([
                'barcode', 'shelf_location', 'condition', 'notes',
            ])->all());

            return $book;
        });

        return redirect()->route('bibliotheek.books.show', $book)->with('success', 'Boek en eerste exemplaar geregistreerd.');
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

            if ($copies->contains(fn ($copy) => $copy->status !== 'beschikbaar')) {
                return back()->withErrors(['delete' => 'Verwijderen is niet mogelijk: minstens één exemplaar is niet beschikbaar of uitgeleend.']);
            }

            // The loan module is still in development. Protect active loans if its table exists.
            if (\Illuminate\Support\Facades\Schema::hasTable('library_loans')
                && \Illuminate\Support\Facades\Schema::hasColumn('library_loans', 'copy_id')
                && \Illuminate\Support\Facades\Schema::hasColumn('library_loans', 'returned_at')
                && DB::table('library_loans')->whereIn('copy_id', $copies->pluck('id'))
                    ->whereNull('returned_at')->exists()) {
                return back()->withErrors(['delete' => 'Verwijderen is niet mogelijk: er staat nog een actieve uitlening open.']);
            }

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

        $book->copies()->create($data);

        return redirect()->route('bibliotheek.books.show', $book)->with('success', 'Extra exemplaar toegevoegd.');
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
