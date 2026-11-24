<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // MariaDB-friendly metadata check; do not modify historical records.
        $exists = DB::select("SHOW COLUMNS FROM `meldingen` LIKE 'auteur_gebruikersnaam'");
        if (count($exists) === 0) {
            DB::statement("ALTER TABLE `meldingen` ADD COLUMN `auteur_gebruikersnaam` VARCHAR(160) NULL");
        }
    }

    public function down(): void
    {
        // Existing incidents remain; revert the new metadata field if explicitly rolled back.
        $exists = DB::select("SHOW COLUMNS FROM `meldingen` LIKE 'auteur_gebruikersnaam'");
        if (count($exists) > 0) {
            DB::statement("ALTER TABLE `meldingen` DROP COLUMN `auteur_gebruikersnaam`");
        }
    }
};
