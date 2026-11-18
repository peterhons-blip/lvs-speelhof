<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LibraryBook extends Model
{
    protected $table = 'library_books';

    protected $fillable = [
        'isbn', 'title', 'subtitle', 'authors', 'publisher',
        'published_year', 'category', 'description', 'cover_url',
    ];

    public function copies(): HasMany
    {
        return $this->hasMany(LibraryCopy::class, 'book_id');
    }
}
