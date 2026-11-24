<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('library_message_templates')) return;

        Schema::create('library_message_templates', function (Blueprint $table) {
            $table->string('type', 30)->primary();
            $table->string('subject', 200);
            $table->text('body');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('library_message_templates');
    }
};
