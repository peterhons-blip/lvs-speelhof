<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        if (!Schema::hasTable('skore_student_teacher_links')) Schema::create('skore_student_teacher_links',function(Blueprint $t){
            $t->id();
            $t->unsignedBigInteger('school_id');
            $t->string('school_year',9);
            $t->unsignedBigInteger('class_id');
            $t->unsignedBigInteger('user_id');
            $t->unsignedBigInteger('student_id');
            $t->text('courses');
            $t->timestamps();
            $t->unique(['school_id','school_year','class_id','user_id','student_id'],'skore_student_teacher_unique');
            $t->index(['school_id','school_year','user_id','class_id'],'skore_staff_class_index');
        });
    }
    public function down(): void { Schema::dropIfExists('skore_student_teacher_links'); }
};
