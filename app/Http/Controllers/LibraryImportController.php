<?php

namespace App\Http\Controllers;

use App\Jobs\ImportLibraryIsbn;
use App\Models\LibraryBook;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class LibraryImportController extends Controller
{
    public function create(): View
    {
        return view('bibliotheek.books.import.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $input = $request->validate([
            'isbns' => ['required', 'string', 'max:15000'],
        ]);

        $lines = preg_split('/[\r\n,;\t]+/', $input['isbns']);
        $entries = collect($lines)->map(fn ($line) => strtoupper(preg_replace('/[\s-]+/', '', trim($line))))
            ->filter()->values();

        if ($entries->isEmpty() || $entries->count() > 150) {
            return back()->withErrors(['isbns' => 'Voer 1 tot maximaal 150 ISBN-nummers in.'])->withInput();
        }

        $unique = $entries->unique()->values();
        $invalid = $unique->filter(fn ($isbn) => ! $this->validIsbn($isbn));
        if ($invalid->isNotEmpty()) {
            return back()->withErrors(['isbns' => 'Ongeldige ISBN-nummers: '.$invalid->take(8)->implode(', ').'.'])->withInput();
        }

        $user = (string) data_get(session('ss_user'), 'gebruikersnaam', data_get(session('ss_user'), 'id', ''));
        if ($user === '') {
            abort(403);
        }

        $ids = DB::transaction(function () use ($unique, $user) {
            $batchId = DB::table('library_import_batches')->insertGetId([
                'initiated_by' => $user, 'total' => $unique->count(), 'created_at' => now(), 'updated_at' => now(),
            ]);
            $ids = [];
            foreach ($unique as $isbn) {
                $ids[] = DB::table('library_import_items')->insertGetId([
                    'batch_id' => $batchId, 'isbn' => $isbn, 'status' => 'wachten',
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            return ['batchId' => $batchId, 'ids' => $ids];
        });

        foreach ($ids['ids'] as $id) {
            ImportLibraryIsbn::dispatch($id);
        }

        $duplicates = $entries->count() - $unique->count();
        return redirect()->route('bibliotheek.books.import.show', $ids['batchId'])
            ->with('success', 'Import gestart voor '.$unique->count().' unieke ISBN-nummers.'
                .($duplicates ? ' '.$duplicates.' dubbele invoerregels overgeslagen.' : ''));
    }

    public function show(Request $request, int $batch): View
    {
        $batchRecord = $this->ownedBatch($request, $batch);
        $items = DB::table('library_import_items')->where('batch_id', $batch)->orderBy('id')->get();
        $counts = $items->countBy('status');
        return view('bibliotheek.books.import.show', [
            'batch' => $batchRecord, 'items' => $items, 'counts' => $counts,
        ]);
    }

    public function status(Request $request, int $batch): \Illuminate\Http\JsonResponse
    {
        $this->ownedBatch($request, $batch);
        $counts = DB::table('library_import_items')->where('batch_id', $batch)
            ->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $pending = (int) ($counts['wachten'] ?? 0);
        return response()->json(['pending' => $pending, 'counts' => $counts]);
    }

    private function ownedBatch(Request $request, int $id): object
    {
        $batch = DB::table('library_import_batches')->where('id', $id)->first();
        $user = (string) data_get($request->session()->get('ss_user'), 'gebruikersnaam',
            data_get($request->session()->get('ss_user'), 'id', ''));
        abort_unless($batch && $user !== '' && $batch->initiated_by === $user, 404);
        return $batch;
    }

    private function validIsbn(string $isbn): bool
    {
        if (preg_match('/^\d{13}$/', $isbn)) {
            $sum = 0;
            foreach (str_split($isbn) as $i => $digit) {
                $sum += (int) $digit * ($i % 2 ? 3 : 1);
            }
            return $sum % 10 === 0;
        }

        if (preg_match('/^\d{9}[\dX]$/', $isbn)) {
            $sum = 0;
            foreach (str_split($isbn) as $i => $digit) {
                $sum += (10 - $i) * ($digit === 'X' ? 10 : (int) $digit);
            }
            return $sum % 11 === 0;
        }

        return false;
    }
}
