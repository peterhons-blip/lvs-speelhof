<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('portal_student_permissions',function(Blueprint $table){
            $table->id();
            $table->unsignedBigInteger('leerling_id');
            $table->string('permission',100);
            $table->boolean('allowed');
            $table->timestamps();
            $table->unique(['leerling_id','permission']);
        });
    }
    public function down(): void { Schema::dropIfExists('portal_student_permissions'); }
};
