<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! \App\Support\LegacyDatabaseSchema::hasColumn('users', 'smartschool_active')) {
            Schema::table('users', function (Blueprint $table) {
                $table->boolean('smartschool_active')->nullable()->default(null);
            });
        }
    }

    public function down(): void
    {
        if (\App\Support\LegacyDatabaseSchema::hasColumn('users', 'smartschool_active')) {
            Schema::table('users', fn (Blueprint $table) => $table->dropColumn('smartschool_active'));
        }
    }
};
