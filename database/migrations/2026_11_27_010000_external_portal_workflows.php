<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        foreach(['meldingen','meldingssoorten'] as $name) {
            Schema::table($name,function(Blueprint $table){
                $table->boolean('visible_student')->default(false);
                $table->boolean('visible_coaccount')->default(false);
            });
        }
        Schema::create('external_library_requests',function(Blueprint $table){
            $table->id(); $table->unsignedBigInteger('leerling_id');
            $table->string('request_type',20); $table->unsignedBigInteger('target_id')->nullable();
            $table->string('principal_type',16); $table->string('status',20)->default('pending');
            $table->text('payload')->nullable(); $table->text('decision_note')->nullable();
            $table->string('decided_by',160)->nullable(); $table->timestamp('decided_at')->nullable();
            $table->timestamps(); $table->index(['leerling_id','status']); $table->index(['request_type','status']);
        });
    }
    public function down(): void {
        Schema::dropIfExists('external_library_requests');
        foreach(['meldingen','meldingssoorten'] as $name) Schema::table($name,fn(Blueprint $t)=>$t->dropColumn(['visible_student','visible_coaccount']));
    }
};
