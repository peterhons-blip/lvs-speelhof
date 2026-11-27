<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        if (!\App\Support\LegacyDatabaseSchema::hasColumn('meldingen','confidential_release')) {
            Schema::table('meldingen',fn(Blueprint $t)=>$t->boolean('confidential_release')->default(false));
        }
    }
    public function down(): void {
        if (\App\Support\LegacyDatabaseSchema::hasColumn('meldingen','confidential_release')) {
            Schema::table('meldingen',fn(Blueprint $t)=>$t->dropColumn('confidential_release'));
        }
    }
};
