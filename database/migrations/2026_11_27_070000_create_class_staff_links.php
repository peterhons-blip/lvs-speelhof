<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        if (!Schema::hasTable('class_staff_links')) Schema::create('class_staff_links',function(Blueprint $t){
            $t->id();
            $t->unsignedBigInteger('school_id');
            $t->unsignedBigInteger('class_id');
            $t->unsignedBigInteger('user_id');
            $t->string('school_year',9);
            $t->string('kind',16);
            $t->string('source',20);
            $t->timestamps();
            $t->unique(['school_id','school_year','class_id','user_id','kind'],'class_staff_unique');
            $t->index(['school_id','school_year','user_id']);
        });
    }
    public function down(): void { Schema::dropIfExists('class_staff_links'); }
};
