<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // The original LVS database may not have Laravel's queue tables.
        if (! Schema::hasTable('jobs')) {
            Schema::create('jobs', function (Blueprint $table) {
                $table->id();
                $table->string('queue', 120)->index();
                $table->longText('payload');
                $table->unsignedTinyInteger('attempts');
                $table->unsignedInteger('reserved_at')->nullable();
                $table->unsignedInteger('available_at');
                $table->unsignedInteger('created_at');
            });
        }

        Schema::create('library_import_batches', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('total')->default(0);
            $table->timestamps();
        });

        Schema::create('library_import_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('library_import_batches')->cascadeOnDelete();
            $table->string('isbn', 40);
            $table->string('status', 20)->default('wachten');
            $table->string('title')->nullable();
            $table->string('message', 255)->nullable();
            $table->timestamps();
            $table->index(['batch_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('library_import_items');
        Schema::dropIfExists('library_import_batches');
        // Never drop jobs: another module may already use it.
    }
};
