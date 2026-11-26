<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        foreach (['meldingscategorien','meldingssoorten'] as $name) {
            Schema::table($name,function(Blueprint $table){
                $table->boolean('portal_active')->default(true);
                $table->unsignedInteger('portal_sort')->default(0);
            });
        }
    }
    public function down(): void {
        foreach (['meldingscategorien','meldingssoorten'] as $name) {
            Schema::table($name,fn(Blueprint $table)=>$table->dropColumn(['portal_active','portal_sort']));
        }
    }
};
