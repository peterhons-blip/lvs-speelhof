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
        if (! $item || $item->status !== 'wachten') {
            return;
        }

        $isbn = $item->isbn;
        if (LibraryBook::where('isbn', $isbn)->exists()) {
            $this->finish('dubbel', 'ISBN staat al in de catalogus.');
            return;
        }

        $metadata = $lookup->find($isbn);
        if (! $metadata) {
            $this->finish('niet_gevonden', 'Geen boekgegevens gevonden bij Open Library.');
            return;
        }

        try {
            $created = DB::transaction(function () use ($isbn, $metadata) {
                if (LibraryBook::where('isbn', $isbn)->exists()) {
                    return false;
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
                $book->copies()->create([
                    'barcode' => LibraryCopy::generateBarcode(),
                    'condition' => 'goed',
                ]);
                return true;
            });

            $status = $created ? 'toegevoegd' : 'dubbel';
            $this->finish($status, $status === 'toegevoegd' ? $metadata['title'] : 'ISBN bestaat al.');
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            $this->finish('dubbel', 'ISBN is ondertussen al geregistreerd.');
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
                'title' => $status === 'toegevoegd' ? mb_substr($message, 0, 255) : null,
                'message' => mb_substr($message, 0, 255),
                'updated_at' => now(),
            ]);
    }
}
