<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement("CREATE TABLE IF NOT EXISTS `birthday_settings` (
            `setting_key` VARCHAR(90) NOT NULL PRIMARY KEY,
            `setting_value` TEXT NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        DB::statement("CREATE TABLE IF NOT EXISTS `birthday_deliveries` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `school_id` BIGINT UNSIGNED NOT NULL,
            `recipient_type` VARCHAR(20) NOT NULL,
            `recipient_id` BIGINT UNSIGNED NOT NULL,
            `birthday_date` DATE NOT NULL,
            `channel` VARCHAR(20) NOT NULL,
            `status` VARCHAR(20) NOT NULL DEFAULT 'processing',
            `error_message` TEXT NULL,
            `created_at` TIMESTAMP NULL,
            `updated_at` TIMESTAMP NULL,
            UNIQUE KEY `birthday_delivery_unique` (`school_id`, `recipient_type`, `recipient_id`, `birthday_date`, `channel`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    public function down(): void
    {
        DB::statement("DROP TABLE IF EXISTS `birthday_deliveries`");
        DB::statement("DROP TABLE IF EXISTS `birthday_settings`");
    }
};
