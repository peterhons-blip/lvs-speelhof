<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LibraryCopy extends Model
{
    protected $table = 'library_copies';

    protected $fillable = ['barcode', 'shelf_location', 'condition', 'notes'];

    public function book(): BelongsTo
    {
        return $this->belongsTo(LibraryBook::class, 'book_id');
    }
}
