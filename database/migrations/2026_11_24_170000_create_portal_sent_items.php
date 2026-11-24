<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Compatible with the older MySQL server used by the school portal.
        DB::statement("CREATE TABLE IF NOT EXISTS `portal_sent_items` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `channel` VARCHAR(20) NOT NULL,
            `source` VARCHAR(100) NOT NULL DEFAULT 'LVS',
            `sender` VARCHAR(255) NULL,
            `recipient` VARCHAR(320) NOT NULL,
            `recipient_type` VARCHAR(15) NULL,
            `subject` VARCHAR(500) NULL,
            `coaccount` TINYINT UNSIGNED NULL,
            `status` VARCHAR(20) NOT NULL,
            `error_message` TEXT NULL,
            `created_at` TIMESTAMP NULL,
            KEY `portal_sent_items_date_idx` (`created_at`),
            KEY `portal_sent_items_channel_idx` (`channel`, `created_at`),
            KEY `portal_sent_items_recipient_idx` (`recipient`(100))
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    public function down(): void
    {
        DB::statement("DROP TABLE IF EXISTS `portal_sent_items`");
    }
};
