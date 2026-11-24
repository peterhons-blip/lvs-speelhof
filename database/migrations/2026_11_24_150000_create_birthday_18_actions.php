<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Raw DDL works with the school's legacy MySQL information_schema.
        DB::statement("CREATE TABLE IF NOT EXISTS `birthday_18_actions` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `school_id` BIGINT UNSIGNED NOT NULL,
            `student_id` BIGINT UNSIGNED NOT NULL,
            `target_date` DATE NOT NULL,
            `action_key` VARCHAR(70) NOT NULL,
            `status` VARCHAR(22) NOT NULL DEFAULT 'processing',
            `recipient` VARCHAR(255) NULL,
            `error_message` TEXT NULL,
            `created_at` TIMESTAMP NULL,
            `updated_at` TIMESTAMP NULL,
            UNIQUE KEY `birthday_18_action_unique` (`school_id`, `student_id`, `target_date`, `action_key`),
            KEY `birthday_18_action_date` (`target_date`, `status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    public function down(): void
    {
        DB::statement("DROP TABLE IF EXISTS `birthday_18_actions`");
    }
};
