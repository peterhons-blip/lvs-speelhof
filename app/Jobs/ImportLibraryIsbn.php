<?php

namespace App\Jobs;

use App\Models\LibraryBook;
use App\Models\LibraryCopy;
use App\Services\LibraryIsbnLookup;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class ImportLibraryIsbn implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;
    public int $timeout = 40;
    public array $backoff = [10, 30];

    public function __construct(public int $itemId)
    {
        $this->onConnection('database');
        $this->onQueue('bibliotheek');
    }

    public function handle(LibraryIsbnLookup $lookup): void
    {
        $item = DB::table('library_import_items')->where('id', $this->itemId)->first();
        if (! $item || $item->status !== 'wachten') return;

        // For an existing book no external ISBN lookup is needed.
        // Metadata is fetched outside the transaction to keep locks short.
        $isbn = $item->isbn;
        $metadata = LibraryBook::where('isbn', $isbn)->exists() ? null : $lookup->findWithFallback($isbn);

        // Concurrent jobs may create this book during the lookup; the unique ISBN
        // constraint is authoritative. Retry once against that existing book.
        for ($attempt = 0; $attempt < 2; $attempt++) {
            try {
                $result = DB::transaction(function () use ($isbn, $metadata) {
                    $pending = DB::table('library_import_items')
                        ->where('id', $this->itemId)->lockForUpdate()->first();
                    // Stop action changes waiting items to 'gestopt'. Nothing gets created
                    // for an item cancelled while an external request was in progress.
                    if (! $pending || $pending->status !== 'wachten') return 'gestopt';

                    $book = LibraryBook::where('isbn', $isbn)->first();
                    $existing = $book !== null;
                    if (! $book) {
                        if (! $metadata) {
                            $this->finish('niet_gevonden', 'Geen boekgegevens gevonden bij Open Library of Google Books.');
                            return 'niet_gevonden';
                        }
                        $book = LibraryBook::create([
                            'isbn' => $isbn,
                            'title' => $metadata['title'],
                            'subtitle' => $metadata['subtitle'] ?: null,
                            'authors' => $metadata['authors'] ?: null,
                            'publisher' => $metadata['publisher'] ?: null,
                            'published_year' => $metadata['published_year'] ?: null,
                            'category' => $metadata['category'] ?: null,
                            'description' => $metadata['description'] ?: null,
                            'cover_url' => $metadata['cover_url'],
                        ]);
                    }

                    // Every import line represents one physical copy, even if the
                    // title/ISBN already exists in the catalog or in this batch.
                    $book->copies()->create([
                        'barcode' => LibraryCopy::generateBarcode(),
                        'condition' => 'goed',
                    ]);
                    $this->finish($existing ? 'exemplaar' : 'toegevoegd', $book->title);
                    return 'done';
                }, 3);
                if ($result !== 'gestopt') return;
                return;
            } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
                // Another job registered the ISBN at the same moment. Next attempt
                // uses the existing book and creates only a new physical copy.
                if ($attempt === 1) throw $e;
            }
        }
    }

    public function failed(?\Throwable $exception): void
    {
        $this->finish('fout', 'Technische fout tijdens het importeren.');
    }

    private function finish(string $status, string $message): void
    {
        DB::table('library_import_items')->where('id', $this->itemId)
            ->where('status', 'wachten')
            ->update([
                'status' => $status,
                'title' => in_array($status, ['toegevoegd', 'exemplaar'], true)
                    ? mb_substr($message, 0, 255) : null,
                'message' => mb_substr($message, 0, 255),
                'updated_at' => now(),
            ]);
    }
}
