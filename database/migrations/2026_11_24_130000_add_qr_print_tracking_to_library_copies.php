<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasColumn('library_copies', 'qr_printed_at')) {
            Schema::table('library_copies', function (Blueprint $table) {
                $table->timestamp('qr_printed_at')->nullable();
            });
        }
        if (! Schema::hasColumn('library_copies', 'qr_printed_by')) {
            Schema::table('library_copies', function (Blueprint $table) {
                $table->string('qr_printed_by', 120)->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::table('library_copies', function (Blueprint $table) {
            $table->dropColumn(['qr_printed_at', 'qr_printed_by']);
        });
    }
};
