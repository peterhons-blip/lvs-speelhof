<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Two plain MySQL tables; no changes to historical incident rows.
        DB::statement("CREATE TABLE IF NOT EXISTS `lvs_followup_rules` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `title` VARCHAR(160) NOT NULL,
            `enabled` TINYINT(1) NOT NULL DEFAULT 0,
            `category_id` BIGINT UNSIGNED NULL,
            `type_id` BIGINT UNSIGNED NULL,
            `threshold` SMALLINT UNSIGNED NOT NULL DEFAULT 4,
            `window_days` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            `action` VARCHAR(30) NOT NULL DEFAULT 'review',
            `recipient_student` TINYINT(1) NOT NULL DEFAULT 0,
            `recipient_parents` TINYINT(1) NOT NULL DEFAULT 0,
            `recipient_counselor` TINYINT(1) NOT NULL DEFAULT 0,
            `recipient_mentor` TINYINT(1) NOT NULL DEFAULT 0,
            `counselor_username` VARCHAR(120) NULL,
            `mentor_username` VARCHAR(120) NULL,
            `sender_username` VARCHAR(120) NULL,
            `subject` VARCHAR(200) NULL,
            `body` TEXT NULL,
            `created_by` VARCHAR(120) NULL,
            `updated_by` VARCHAR(120) NULL,
            `created_at` TIMESTAMP NULL,
            `updated_at` TIMESTAMP NULL,
            KEY `lvs_followup_rules_enabled` (`enabled`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        DB::statement("CREATE TABLE IF NOT EXISTS `lvs_followup_events` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `rule_id` BIGINT UNSIGNED NOT NULL,
            `melding_id` BIGINT UNSIGNED NOT NULL,
            `leerling_id` BIGINT UNSIGNED NOT NULL,
            `action` VARCHAR(30) NOT NULL,
            `recipient` VARCHAR(200) NULL,
            `coaccount` TINYINT UNSIGNED NOT NULL DEFAULT 0,
            `status` VARCHAR(25) NOT NULL,
            `error_message` TEXT NULL,
            `resolution_note` VARCHAR(1000) NULL,
            `resolved_by` VARCHAR(120) NULL,
            `resolved_at` TIMESTAMP NULL,
            `created_at` TIMESTAMP NULL,
            `updated_at` TIMESTAMP NULL,
            UNIQUE KEY `lvs_followup_event_unique` (`rule_id`, `melding_id`, `recipient`, `coaccount`),
            KEY `lvs_followup_event_student` (`leerling_id`, `created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        if (\Illuminate\Support\Facades\Schema::hasTable('portal_role_permissions')) {
            foreach (['directie', 'leerlingbegeleiding'] as $role) {
                DB::table('portal_role_permissions')->insertOrIgnore([
                    'role' => $role, 'permission' => 'lvs.followups.view',
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            DB::table('portal_role_permissions')->insertOrIgnore([
                'role' => 'directie', 'permission' => 'lvs.settings.manage',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::statement("DROP TABLE IF EXISTS `lvs_followup_events`");
        DB::statement("DROP TABLE IF EXISTS `lvs_followup_rules`");
    }
};
