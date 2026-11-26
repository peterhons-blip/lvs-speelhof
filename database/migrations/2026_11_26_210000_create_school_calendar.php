<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Services\SchoolCalendar;
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('school_calendar_closures')) {
            Schema::create('school_calendar_closures', function (Blueprint $t) {
                $t->id(); $t->unsignedSmallInteger('school_year'); $t->string('name',120);
                $t->date('starts_on'); $t->date('ends_on'); $t->string('source',20);
                $t->timestamps(); $t->unique(['school_year','name','source']);
            });
        }
        SchoolCalendar::import(2026);
        foreach (['loan_day_unit'=>'calendar','shift_due_to_schoolday'=>'1','skip_weekends'=>'1','skip_holidays'=>'1','reminder_day_unit'=>'school'] as $key=>$value) {
            DB::table('library_settings')->insertOrIgnore(['key'=>$key,'value'=>$value,'created_at'=>now(),'updated_at'=>now()]);
        }
    }
    public function down(): void { Schema::dropIfExists('school_calendar_closures'); }
};
