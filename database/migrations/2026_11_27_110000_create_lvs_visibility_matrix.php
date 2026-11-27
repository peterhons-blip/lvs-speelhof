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
    }
    public function down(): void {Schema::dropIfExists('lvs_visibility_matrix');}
};
