<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('library_loans', function(Blueprint $t){$t->date('reminders_paused_until')->nullable();});
        Schema::create('library_copy_events', function(Blueprint $t){
            $t->id(); $t->foreignId('copy_id')->constrained('library_copies')->restrictOnDelete();
            $t->string('old_status',30); $t->string('new_status',30); $t->string('actor',120)->nullable(); $t->text('note')->nullable(); $t->timestamps();
        });
        Schema::create('library_inventory_runs', function(Blueprint $t){
            $t->id(); $t->string('name',120); $t->string('actor',120)->nullable(); $t->timestamp('finished_at')->nullable(); $t->timestamps();
        });
        Schema::create('library_inventory_items', function(Blueprint $t){
            $t->id(); $t->foreignId('run_id')->constrained('library_inventory_runs')->cascadeOnDelete();
            $t->foreignId('copy_id')->constrained('library_copies')->restrictOnDelete();
            $t->string('initial_status',30); $t->timestamp('seen_at')->nullable(); $t->string('seen_by',120)->nullable();
            $t->unique(['run_id','copy_id']);
        });
    }
    public function down(): void {
        Schema::dropIfExists('library_inventory_items'); Schema::dropIfExists('library_inventory_runs'); Schema::dropIfExists('library_copy_events');
        Schema::table('library_loans',fn(Blueprint $t)=>$t->dropColumn('reminders_paused_until'));
    }
};
