<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('library_student_cards', function (Blueprint $table) {
            $table->id();
            $table->string('scan_code', 128)->unique();
            $table->unsignedBigInteger('leerling_id')->unique();
            $table->string('linked_by', 120)->nullable();
            $table->timestamps();
            $table->index('leerling_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('library_student_cards');
    }
};
