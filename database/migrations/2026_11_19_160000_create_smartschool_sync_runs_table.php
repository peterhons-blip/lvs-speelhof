<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('smartschool_sync_runs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('school_id');
            $table->string('requested_by', 120);
            $table->string('status', 20)->default('waiting');
            $table->boolean('refresh_scancodes')->default(false);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->text('summary')->nullable();
            $table->timestamps();
            $table->index(['school_id', 'status', 'created_at'], 'ss_sync_runs_school_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('smartschool_sync_runs');
    }
};
