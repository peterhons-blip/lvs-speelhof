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

    /**
     * Search at work level by title or author. A suggested ISBN may belong to
     * another edition: always ask the user to verify the number on their book.
     */
    public function searchByName(string $query, string $type = 'title'): array
    {
        try {
            $parameter = $type === 'author' ? 'author' : 'title';
            $response = Http::acceptJson()->timeout(8)->retry(1, 200)
                ->get('https://openlibrary.org/search.json', [
                    $parameter => $query,
                    'fields' => 'key,title,author_name,first_publish_year,cover_i,publisher,isbn',
                    'limit' => 12,
                    'lang' => 'nl',
                ]);

            if (! $response->successful()) {
                return [];
            }

            return collect($response->json('docs', []))
                ->filter(fn ($book) => is_array($book) && ! empty($book['title']))
                ->take(12)
                ->map(function ($book) {
                    $coverId = $book['cover_i'] ?? null;
                    $isbnOptions = (array) ($book['isbn'] ?? []);
                    $suggestedIsbn = collect($isbnOptions)->first(function ($value) {
                        if (! is_string($value) || ! preg_match('/^\\d{13}$/', $value)) {
                            return false;
                        }
                        $sum = 0;
                        foreach (str_split($value) as $index => $digit) {
                            $sum += (int) $digit * ($index % 2 === 0 ? 1 : 3);
                        }
                        return $sum % 10 === 0;
                    });
                    return [
                        'isbn' => $suggestedIsbn ?: '',
                        'title' => mb_substr((string) $book['title'], 0, 255),
                        'authors' => mb_substr(implode(', ', array_slice((array) ($book['author_name'] ?? []), 0, 5)), 0, 255),
                        'publisher' => mb_substr((string) (($book['publisher'] ?? [])[0] ?? ''), 0, 255),
                        'published_year' => mb_substr((string) ($book['first_publish_year'] ?? ''), 0, 20),
                        'cover_url' => is_numeric($coverId) && (int) $coverId > 0
                            ? 'https://covers.openlibrary.org/b/id/'.(int) $coverId.'-M.jpg'
                            : null,
                    ];
                })
                ->values()->all();
        } catch (\Throwable $e) {
            report($e);
            return [];
        }
    }
}
