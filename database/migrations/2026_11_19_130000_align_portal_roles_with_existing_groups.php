<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Existing group IDs 1-4 (leerkracht/directeur/leerlingbegeleider/ICT-coordinator)
        // must remain unchanged. Only add the three missing groups.
        foreach (['boekhouding', 'secretariaat', 'TAC'] as $name) {
            if (! DB::table('groups')->where('groupname', $name)->exists()) {
                DB::table('groups')->insert([
                    'groupname' => $name, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }

        // portal_staff was introduced by the provisional library feature. The new implementation
        // reads users.groupId instead. Preserve populated provisional data for review.
        if (Schema::hasTable('portal_staff')) {
            if (DB::table('portal_staff')->exists()) {
                if (! Schema::hasTable('portal_staff_legacy')) {
                    Schema::rename('portal_staff', 'portal_staff_legacy');
                }
            } else {
                Schema::drop('portal_staff');
            }
        }
    }

    public function down(): void
    {
        // Do NOT delete groups or move user assignments: other records may now use them.
        // Do NOT revert the role permission matrix, which is the intended new feature.
        if (Schema::hasTable('portal_staff_legacy') && ! Schema::hasTable('portal_staff')) {
            Schema::rename('portal_staff_legacy', 'portal_staff');
        }
    }
};
