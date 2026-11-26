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
                'category' => $this->genreFromSubjects($book['subjects'] ?? []),
                'description' => $this->descriptionText($book['notes'] ?? ($book['excerpts'][0]['text'] ?? null)),
            ];
        } catch (\Throwable $e) {
            report($e);
            return null;
        }
    }

    /**
     * Google Books fallback when Open Library does not know a valid ISBN.
     * We use only the exact ISBN response, never another edition's metadata.
     */
    public function findGoogle(string $isbn): ?array
    {
        try {
            $params = ['q' => 'isbn:'.$isbn, 'maxResults' => 10, 'printType' => 'books'];
            $apiKey = trim((string) config('services.google_books.key', ''));
            if ($apiKey !== '') $params['key'] = $apiKey;
            $response = Http::acceptJson()->timeout(8)
                ->get('https://www.googleapis.com/books/v1/volumes', $params);
            if (! $response->successful()) {
                \Illuminate\Support\Facades\Log::warning('Google Books ISBN-verzoek mislukt', [
                    'status' => $response->status(), 'isbn' => $isbn,
                ]);
                return null;
            }
            foreach ((array) $response->json('items', []) as $item) {
                $info = (array) ($item['volumeInfo'] ?? []);
                $identifiers = (array) ($info['industryIdentifiers'] ?? []);
                $match = collect($identifiers)->contains(function ($id) use ($isbn) {
                    $value = strtoupper(preg_replace('/[^0-9X]/', '', (string) ($id['identifier'] ?? '')) ?? '');
                    return $value === $isbn;
                });
                if (! $match || empty($info['title'])) continue;
                // Third-party thumbnails are not auto-saved: the model currently
                // permits only trusted Open Library cover URLs.
                return [
                    'isbn' => $isbn,
                    'title' => mb_substr((string) $info['title'], 0, 255),
                    'subtitle' => mb_substr((string) ($info['subtitle'] ?? ''), 0, 255),
                    'authors' => mb_substr(implode(', ', (array) ($info['authors'] ?? [])), 0, 255),
                    'publisher' => mb_substr((string) ($info['publisher'] ?? ''), 0, 255),
                    'published_year' => mb_substr((string) ($info['publishedDate'] ?? ''), 0, 20),
                    'cover_url' => null,
                    'category' => $this->genreFromSubjects((array) ($info['categories'] ?? [])),
                    'description' => mb_substr(strip_tags((string) ($info['description'] ?? '')), 0, 5000),
                    'source' => 'Google Books',
                ];
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Google Books niet bereikbaar', [
                'isbn' => $isbn, 'error' => $e->getMessage(),
            ]);
        }
        return null;
    }

    public function findWithFallback(string $isbn): ?array
    {
        $book = $this->find($isbn);
        if ($book !== null) return $book + ['source' => 'Open Library'];
        return $this->findGoogle($isbn);
    }

    public function searchGoogleByName(string $query, string $type = 'title'): array
    {
        try {
            $prefix = $type === 'author' ? 'inauthor:' : 'intitle:';
            $params = ['q' => $prefix.$query, 'maxResults' => 12, 'printType' => 'books'];
            $apiKey = trim((string) config('services.google_books.key', ''));
            if ($apiKey !== '') $params['key'] = $apiKey;
            $response = Http::acceptJson()->timeout(8)
                ->get('https://www.googleapis.com/books/v1/volumes', $params);
            if (! $response->successful()) return [];
            return collect($response->json('items', []))->filter(fn($item) => is_array($item))
                ->map(function ($item) {
                    $info = (array) ($item['volumeInfo'] ?? []);
                    if (empty($info['title'])) return null;
                    $id = collect((array) ($info['industryIdentifiers'] ?? []))
                        ->first(fn($value) => ($value['type'] ?? '') === 'ISBN_13');
                    return [
                        'work_key' => null,
                        'edition_count' => 0,
                        'isbn' => (string) ($id['identifier'] ?? ''),
                        'category' => $this->genreFromSubjects((array) ($info['categories'] ?? [])),
                        'title' => mb_substr((string) $info['title'], 0, 255),
                        'authors' => mb_substr(implode(', ', (array) ($info['authors'] ?? [])), 0, 255),
                        'publisher' => mb_substr((string) ($info['publisher'] ?? ''), 0, 255),
                        'published_year' => mb_substr((string) ($info['publishedDate'] ?? ''), 0, 20),
                        'cover_url' => null,
                        'source' => 'Google Books',
                    ];
                })->filter()->values()->all();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Google Books zoeken mislukt', ['error' => $e->getMessage()]);
            return [];
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
                    'fields' => 'key,title,author_name,first_publish_year,cover_i,publisher,isbn,subject,edition_count',
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
                        if (! is_string($value) || ! preg_match('/^\d{13}$/', $value)) {
                            return false;
                        }
                        $sum = 0;
                        foreach (str_split($value) as $index => $digit) {
                            $sum += (int) $digit * ($index % 2 === 0 ? 1 : 3);
                        }
                        return $sum % 10 === 0;
                    });
                    return [
                        'work_key' => preg_match('~^/?works/OL[0-9]+W$~', (string) ($book['key'] ?? ''))
                            ? '/'.ltrim((string) $book['key'], '/') : null,
                        'edition_count' => (int) ($book['edition_count'] ?? 0),
                        'isbn' => $suggestedIsbn ?: '',
                        'category' => $this->genreFromSubjects($book['subject'] ?? []),
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



    /**
     * Fetch edition-specific metadata. A work may have many translations and covers.
     * Keep pagination so users can browse every edition without assuming a cover/ISBN match.
     */
    public function editions(string $workKey, int $offset = 0): array
    {
        $limit = 100;
        try {
            $response = Http::acceptJson()->timeout(12)
                ->get('https://openlibrary.org'.$workKey.'/editions.json', [
                    'limit' => $limit,
                    'offset' => $offset,
                ]);

            if (! $response->successful()) {
                return ['editions' => [], 'total' => 0, 'offset' => $offset, 'limit' => $limit, 'error' => true];
            }

            $editions = collect($response->json('entries', []))
                ->filter(fn ($edition) => is_array($edition) && ! empty($edition['title']))
                ->map(function ($edition) {
                    $isbn = collect(array_merge((array) ($edition['isbn_13'] ?? []), (array) ($edition['isbn_10'] ?? [])))
                        ->first(fn ($value) => is_string($value) && $this->isValidIsbn($value)) ?: '';
                    $coverId = collect((array) ($edition['covers'] ?? []))
                        ->first(fn ($value) => is_numeric($value) && (int) $value > 0);
                    $langCodes = collect((array) ($edition['languages'] ?? []))
                        ->map(function ($language) {
                            $key = is_array($language) ? ($language['key'] ?? '') : '';
                            return is_string($key) ? basename($key) : '';
                        })->filter()->unique()->values()->all();

                    return [
                        'title' => mb_substr((string) $edition['title'], 0, 255),
                        'subtitle' => mb_substr((string) ($edition['subtitle'] ?? ''), 0, 255),
                        'publisher' => mb_substr((string) (($edition['publishers'] ?? [])[0] ?? ''), 0, 255),
                        'published_year' => mb_substr((string) ($edition['publish_date'] ?? ''), 0, 20),
                        'isbn' => $isbn,
                        'cover_url' => $coverId
                            ? 'https://covers.openlibrary.org/b/id/'.(int) $coverId.'-M.jpg'
                            : null,
                        'languages' => $langCodes,
                        'description' => $this->descriptionText($edition['description'] ?? null),
                    ];
                })->values()->all();

            return [
                'editions' => $editions,
                'total' => (int) $response->json('size', count($editions)),
                'offset' => $offset,
                'limit' => $limit,
            ];
        } catch (\Throwable $e) {
            report($e);
            return ['editions' => [], 'total' => 0, 'offset' => $offset, 'limit' => $limit, 'error' => true];
        }
    }

    private function isValidIsbn(string $isbn): bool
    {
        if (preg_match('/^\d{13}$/', $isbn)) {
            $sum = 0;
            foreach (str_split($isbn) as $i => $digit) {
                $sum += (int) $digit * ($i % 2 === 0 ? 1 : 3);
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

    /**
     * Retrieve a description for a selected work, not for all search results.
     * The work key is validated by the controller, and the HTTP host is fixed.
     */
    public function workDetails(string $workKey): array
    {
        try {
            $response = Http::acceptJson()->timeout(7)
                ->get('https://openlibrary.org'.$workKey.'.json');

            if (! $response->successful()) {
                return ['description' => '', 'category' => '', 'subtitle' => ''];
            }

            $data = $response->json();
            if (! is_array($data)) {
                return ['description' => '', 'category' => ''];
            }

            return [
                'description' => $this->descriptionText($data['description'] ?? null),
                'subtitle' => mb_substr((string) ($data['subtitle'] ?? ''), 0, 255),
                'category' => $this->genreFromSubjects(is_array($data['subjects'] ?? null) ? $data['subjects'] : []),
            ];
        } catch (\Throwable $e) {
            report($e);
            return ['description' => '', 'category' => ''];
        }
    }

    private function descriptionText(mixed $value): string
    {
        $text = is_array($value) ? ($value['value'] ?? '') : $value;
        return is_string($text) ? mb_substr(trim($text), 0, 5000) : '';
    }

    /**
     * Convert Open Library subjects into one readable library genre.
     * Subjects can be very specific, so a recognized genre takes priority.
     */
    private function genreFromSubjects(array $subjects): string
    {
        $subjects = collect($subjects)->map(function ($subject) {
            if (is_array($subject)) {
                return (string) ($subject['name'] ?? '');
            }
            return is_string($subject) ? $subject : '';
        })->filter()->values();

        $genres = [
            'Fantasy' => ['fantasy', 'fantasie', 'magie', 'magic', 'tovenaars', 'wizards'],
            'Sciencefiction' => ['science fiction', 'science-fiction', 'sci-fi', 'dystopia'],
            'Thriller' => ['thriller', 'suspense', 'spionage'],
            'Detective / misdaad' => ['detective', 'mystery', 'crime fiction', 'misdaad', 'murder'],
            'Horror' => ['horror', 'ghost stories', 'griezel'],
            'Romantiek' => ['romance', 'romantic fiction', 'liefdesverhalen'],
            'Historische roman' => ['historical fiction', 'historische roman'],
            'Avontuur' => ['adventure', 'avonturen'],
            'Stripverhaal' => ['comics', 'graphic novels', 'stripverhalen'],
            'Poëzie' => ['poetry', 'poems', 'gedichten', 'poëzie'],
            'Biografie' => ['biography', 'autobiography', 'biografie', 'memoir'],
            'Geschiedenis' => ['history', 'geschiedenis'],
            'Wetenschap' => ['science', 'wetenschap'],
            'Educatief' => ['education', 'educational', 'onderwijs'],
            'Kinder- en jeugdboeken' => ['juvenile fiction', 'children', 'kinderboeken', 'jeugdboeken'],
            'Roman' => ['fiction', 'novels', 'romans'],
        ];

        foreach ($genres as $genre => $keywords) {
            foreach ($subjects as $subject) {
                $text = mb_strtolower($subject);
                foreach ($keywords as $keyword) {
                    if (str_contains($text, $keyword)) {
                        return $genre;
                    }
                }
            }
        }

        return '';
    }
}
