<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Smartschool QR code is synced into the existing pupil record.
        // The unique index prevents accidental assignment of one code to two pupils.
        Schema::table('leerlingen', function (Blueprint $table) {
            $table->string('scannable_code', 128)->nullable()->unique('leerlingen_scannable_code_unique');
        });
    }

    public function down(): void
    {
        Schema::table('leerlingen', function (Blueprint $table) {
            $table->dropUnique('leerlingen_scannable_code_unique');
            $table->dropColumn('scannable_code');
        });
    }
};
