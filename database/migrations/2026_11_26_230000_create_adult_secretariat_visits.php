<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
return new class extends Migration {
    public function up(): void {
        Schema::create('adult_secretariat_visits', function(Blueprint $t){
            $t->id(); $t->unsignedBigInteger('student_id'); $t->unsignedBigInteger('school_id');
            $t->date('invited_on'); $t->timestamp('completed_at')->nullable(); $t->string('completed_by',120)->nullable();
            $t->date('last_attempt_on')->nullable(); $t->timestamps();
            $t->unique(['student_id','school_id']);
        });
        Schema::create('adult_secretariat_visit_events',function(Blueprint $t){
            $t->id(); $t->foreignId('visit_id')->constrained('adult_secretariat_visits')->cascadeOnDelete();
            $t->string('action',30); $t->string('actor',120); $t->timestamps();
        });
        foreach(['secretariaat','directie'] as $role) DB::table('portal_role_permissions')->insertOrIgnore(['role'=>$role,'permission'=>'lvs.secretariat_visits.manage']);
    }
    public function down(): void {
        DB::table('portal_role_permissions')->where('permission','lvs.secretariat_visits.manage')->delete();
        Schema::dropIfExists('adult_secretariat_visit_events'); Schema::dropIfExists('adult_secretariat_visits');
    }
};
