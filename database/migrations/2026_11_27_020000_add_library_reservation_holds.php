<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('external_library_requests',function(Blueprint $t){
            $t->unsignedBigInteger('reserved_copy_id')->nullable();
            $t->date('reserved_until')->nullable();
            $t->index(['reserved_copy_id','status','reserved_until'],'external_reservation_copy_idx');
        });
    }
    public function down(): void {
        Schema::table('external_library_requests',function(Blueprint $t){
            $t->dropIndex('external_reservation_copy_idx');
            $t->dropColumn(['reserved_copy_id','reserved_until']);
        });
    }
};
