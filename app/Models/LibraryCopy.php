<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class LibraryCopy extends Model
{
    protected $table = 'library_copies';

    protected $fillable = ['barcode', 'shelf_location', 'condition', 'notes'];

    /** Generate a compact inventory code without relying on MAX(id) and risking concurrent collisions. */
    public static function generateBarcode(): string
    {
        do {
            $barcode = 'BIB-'.Str::upper(Str::random(10));
        } while (static::query()->where('barcode', $barcode)->exists());

        return $barcode;
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(LibraryBook::class, 'book_id');
    }
}
