<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!\App\Support\LegacyDatabaseSchema::hasColumn('leerlingen','sync_missing_count')) {
            Schema::table('leerlingen',function(Blueprint $table) {
                $table->unsignedTinyInteger('sync_missing_count')->default(0);
            });
        }
    }
    public function down(): void
    {
        // Preserve reconciliation history during legacy database rollbacks.
    }
};
