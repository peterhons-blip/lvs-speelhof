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
                    'fields' => 'key,title,author_name,first_publish_year,cover_i,publisher,isbn,subject',
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
                        'work_key' => preg_match('~^/works/OL[0-9]+W$~', (string) ($book['key'] ?? '')) ? $book['key'] : null,
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
