<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        foreach ([
            'overdue_first_days' => '0',
            'overdue_repeat_days' => '0',
            'overdue_escalate_after' => '0',
        ] as $key => $value) {
            DB::table('library_settings')->insertOrIgnore([
                'key' => $key, 'value' => $value,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        DB::statement("CREATE TABLE IF NOT EXISTS `library_loan_escalations` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `loan_id` BIGINT UNSIGNED NOT NULL,
            `reminder_count` INT UNSIGNED NOT NULL DEFAULT 0,
            `status` VARCHAR(20) NOT NULL DEFAULT 'open',
            `resolution_note` VARCHAR(1000) NULL,
            `resolved_by` VARCHAR(120) NULL,
            `resolved_at` TIMESTAMP NULL,
            `created_at` TIMESTAMP NULL,
            `updated_at` TIMESTAMP NULL,
            UNIQUE KEY `library_escalations_unique_loan` (`loan_id`),
            KEY `library_escalations_status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
    public function down(): void
    {
        DB::statement("DROP TABLE IF EXISTS `library_loan_escalations`");
        // Preserve configurable settings if rolled back.
    }
};
