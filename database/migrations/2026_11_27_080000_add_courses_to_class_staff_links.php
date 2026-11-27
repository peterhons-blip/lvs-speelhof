<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        if (!\App\Support\LegacyDatabaseSchema::hasColumn('class_staff_links','courses')) Schema::table('class_staff_links',fn(Blueprint $t)=>$t->text('courses')->nullable());
    }
    public function down(): void { Schema::table('class_staff_links',fn(Blueprint $t)=>$t->dropColumn('courses')); }
};
