<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('library_books', function (Blueprint $table) {
            $table->id();
            $table->string('isbn', 13)->nullable()->unique();
            $table->string('title');
            $table->string('subtitle')->nullable();
            $table->string('authors')->nullable();
            $table->string('publisher')->nullable();
            $table->string('published_year', 20)->nullable();
            $table->string('category')->nullable();
            $table->text('description')->nullable();
            $table->string('cover_url', 500)->nullable();
            $table->timestamps();

            // Geen index op volledige titel (255 x utf8mb4 > 767 bytes op oudere MySQL).
            // De zoekfunctie gebruikt LIKE met een begin-wildcard, waarvoor die index niet helpt.
        });

        Schema::create('library_copies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_id')->constrained('library_books')->cascadeOnDelete();
            $table->string('barcode', 60)->unique();
            $table->string('shelf_location', 120)->nullable();
            $table->string('condition', 30)->default('goed');
            $table->string('status', 30)->default('beschikbaar');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['book_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('library_copies');
        Schema::dropIfExists('library_books');
    }
};
