<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('klassen',function(Blueprint $t){
            $t->string('smartschool_class_id',40)->nullable();
            $t->unique(['schoolid','smartschool_class_id'],'klassen_school_ss_class_id_unique');
        });
    }
    public function down(): void {
        Schema::table('klassen',function(Blueprint $t){
            $t->dropUnique('klassen_school_ss_class_id_unique');
            $t->dropColumn('smartschool_class_id');
        });
    }
};
