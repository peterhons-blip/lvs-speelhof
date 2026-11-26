<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('external_library_requests',function(Blueprint $t){
            $t->unsignedBigInteger('leerling_id')->nullable()->change();
            $t->unsignedBigInteger('staff_user_id')->nullable();
            $t->index(['staff_user_id','status'],'library_requests_staff_idx');
        });
    }
    public function down(): void {
        if (\Illuminate\Support\Facades\DB::table('external_library_requests')->whereNotNull('staff_user_id')->exists()) throw new \RuntimeException('Personeelsaanvragen bestaan; rollback zou gegevens verliezen.');
        Schema::table('external_library_requests',function(Blueprint $t){
            $t->dropIndex('library_requests_staff_idx');
            $t->dropColumn('staff_user_id');
            $t->unsignedBigInteger('leerling_id')->nullable(false)->change();
        });
    }
};
