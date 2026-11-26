<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement("CREATE TABLE IF NOT EXISTS `library_escalations` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `loan_id` BIGINT UNSIGNED NOT NULL,
            `recipient` VARCHAR(160) NOT NULL,
            `status` VARCHAR(20) NOT NULL DEFAULT 'processing',
            `error_message` TEXT NULL,
            `created_at` TIMESTAMP NULL,
            `updated_at` TIMESTAMP NULL,
            UNIQUE KEY `library_escalations_unique` (`loan_id`),
            KEY `library_escalations_status` (`status`,`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    public function down(): void
    {
        DB::statement("DROP TABLE IF EXISTS `library_escalations`");
    }
};
