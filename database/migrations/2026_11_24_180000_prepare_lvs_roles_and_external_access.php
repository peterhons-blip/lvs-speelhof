<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Existing staff could access the existing LVS routes before permission checks.
        // Preserve that access explicitly; never grant it to future external identities.
        if (Schema::hasTable('portal_role_permissions')) {
            $roles = [
                'leerkracht', 'administratie', 'directie', 'leerlingbegeleiding',
                'secretariaat', 'boekhouding', 'tac',
            ];
            foreach ($roles as $role) {
                foreach (['lvs.view', 'lvs.dashboard.view', 'lvs.meldingen.view', 'lvs.meldingen.create'] as $permission) {
                    DB::table('portal_role_permissions')->insertOrIgnore([
                        'role' => $role, 'permission' => $permission,
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            }
            foreach (['directie', 'leerlingbegeleiding'] as $role) {
                DB::table('portal_role_permissions')->insertOrIgnore([
                    'role' => $role, 'permission' => 'lvs.reports.view',
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }

        // No login, account link or permission is created for parents or students.
        // Future activation requires verified identity, student linkage and new policies.
        DB::statement("CREATE TABLE IF NOT EXISTS `lvs_external_access` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `principal_type` VARCHAR(16) NOT NULL,
            `principal_identifier` VARCHAR(160) NOT NULL,
            `leerling_id` BIGINT UNSIGNED NOT NULL,
            `verified_at` TIMESTAMP NULL,
            `enabled` TINYINT(1) NOT NULL DEFAULT 0,
            `created_at` TIMESTAMP NULL,
            `updated_at` TIMESTAMP NULL,
            UNIQUE KEY `lvs_external_access_unique` (`principal_type`, `principal_identifier`, `leerling_id`),
            KEY `lvs_external_access_leerling` (`leerling_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    public function down(): void
    {
        DB::statement("DROP TABLE IF EXISTS `lvs_external_access`");
        // Do not revoke permissions here: admins may have changed them in the UI.
    }
};
