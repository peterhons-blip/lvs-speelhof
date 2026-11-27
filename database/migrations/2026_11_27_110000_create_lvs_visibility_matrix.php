<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        if(!Schema::hasTable('lvs_visibility_matrix'))Schema::create('lvs_visibility_matrix',function(Blueprint $t){
            $t->id();
            $t->unsignedBigInteger('school_id');
            $t->string('relation',40);
            $t->boolean('own')->default(false);
            $t->boolean('ordinary')->default(false);
            $t->boolean('confidential')->default(false);
            $t->timestamps();
            $t->unique(['school_id','relation']);
        });
        // Preserve existing My Classes access for roles which could already open pupils.
        if(Schema::hasTable('portal_role_permissions')){
            $roles=\Illuminate\Support\Facades\DB::table('portal_role_permissions')->where('permission','lvs.view')->pluck('role');
            foreach($roles as $role)\Illuminate\Support\Facades\DB::table('portal_role_permissions')->insertOrIgnore(['role'=>$role,'permission'=>'lvs.my_classes.view','created_at'=>now(),'updated_at'=>now()]);
        }
    }
    public function down(): void {Schema::dropIfExists('lvs_visibility_matrix');}
};
