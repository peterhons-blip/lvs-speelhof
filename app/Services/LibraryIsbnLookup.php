<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class LibraryIsbnLookup
{
    /**
     * Public Open Library book metadata. Returns null for unknown/unavailable books.
     * No personal/student data is ever sent to this external service.
     */
    public function find(string $isbn): ?array
    {
        try {
            $response = Http::acceptJson()->timeout(7)->retry(1, 200)
                ->get('https://openlibrary.org/api/books', [
                    'bibkeys' => 'ISBN:'.$isbn,
                    'jscmd' => 'data',
                    'format' => 'json',
                ]);

            if (! $response->successful()) {
                return null;
            }

            $book = $response->json('ISBN:'.$isbn);
            if (! is_array($book) || empty($book['title'])) {
                return null;
            }

            $authors = collect($book['authors'] ?? [])
                ->pluck('name')->filter()->implode(', ');
            $cover = data_get($book, 'cover.medium')
                ?: data_get($book, 'cover.large')
                ?: data_get($book, 'cover.small');

            // Only accept cover assets from Open Library; no arbitrary external image URLs.
            if (! is_string($cover) || ! str_starts_with($cover, 'https://covers.openlibrary.org/')) {
                $cover = null;
            }

            return [
                'isbn' => $isbn,
                'title' => mb_substr((string) $book['title'], 0, 255),
                'subtitle' => mb_substr((string) data_get($book, 'subtitle', ''), 0, 255),
                'authors' => mb_substr($authors, 0, 255),
                'publisher' => mb_substr((string) data_get($book, 'publishers.0.name', ''), 0, 255),
                'published_year' => mb_substr((string) ($book['publish_date'] ?? ''), 0, 20),
                'cover_url' => $cover,
            ];
        } catch (\Throwable $e) {
            report($e);
            return null;
        }
    }
}
