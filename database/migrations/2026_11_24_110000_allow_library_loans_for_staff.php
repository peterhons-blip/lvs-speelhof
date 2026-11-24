<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('library_loans')) return;

        // Existing loans keep their leerling_id; staff loans use the new staff_user_id.
        if (! \App\Support\LegacyDatabaseSchema::hasColumn('library_loans', 'staff_user_id')) {
            Schema::table('library_loans', function (Blueprint $table) {
                $table->unsignedBigInteger('staff_user_id')->nullable()->after('leerling_id');
                $table->index(['staff_user_id', 'returned_at'], 'library_loans_staff_open_idx');
            });
        }

        // Legacy student ID was required. Staff loans have no leerling_id.
        if (! Schema::hasTable('library_loans')) return;
        if (DB::getDriverName() === 'mysql') {
            // Avoid doctrine/dbal dependency and MariaDB generation_expression introspection.
            DB::statement('ALTER TABLE library_loans MODIFY leerling_id BIGINT UNSIGNED NULL');
        } else {
            Schema::table('library_loans', fn (Blueprint $table) => $table->unsignedBigInteger('leerling_id')->nullable()->change());
        }
    }

    public function down(): void
    {
        // Do not drop the new column automatically: doing so would erase staff loan ownership.
        // A manual data migration is required before rolling back this change.
    }
};
