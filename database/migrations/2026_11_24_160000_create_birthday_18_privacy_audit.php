<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement("CREATE TABLE IF NOT EXISTS `birthday_18_privacy_audit` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `actor_username` VARCHAR(160) NOT NULL,
            `actor_role` VARCHAR(50) NOT NULL,
            `old_value` VARCHAR(1) NOT NULL,
            `new_value` VARCHAR(1) NOT NULL,
            `reason` VARCHAR(1000) NOT NULL,
            `created_at` TIMESTAMP NULL,
            KEY `birthday_18_privacy_audit_date` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    public function down(): void
    {
        DB::statement("DROP TABLE IF EXISTS `birthday_18_privacy_audit`");
    }
};
