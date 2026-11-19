<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! DB::table('groups')->where('groupname', 'administratie')->exists()) {
            DB::table('groups')->insert([
                'groupname' => 'administratie',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if (! Schema::hasTable('smartschool_staff_sync_runs')) {
            Schema::create('smartschool_staff_sync_runs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('school_id');
                $table->string('requested_by', 120);
                $table->string('status', 20)->default('waiting');
                $table->boolean('apply_changes')->default(false);
                $table->text('summary')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('finished_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('smartschool_staff_memberships')) {
            Schema::create('smartschool_staff_memberships', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('schoolid');
                $table->string('gebruikersnaam', 120);
                $table->timestamp('last_seen_at')->nullable();
                $table->timestamps();
                $table->unique(['schoolid', 'gebruikersnaam'], 'staff_school_username_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('smartschool_staff_memberships');
        Schema::dropIfExists('smartschool_staff_sync_runs');
        // Groep administratie bewaren: er kunnen intussen gebruikers aan gekoppeld zijn.
    }
};
