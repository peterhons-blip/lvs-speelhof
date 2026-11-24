<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * The school's legacy MySQL server has no information_schema.columns.generation_expression.
     * Laravel 12 Schema::hasColumn() queries that field, so use a compatible metadata query.
     */
    private function existingColumns(): array
    {
        return array_map(
            static fn ($column) => (string) $column->COLUMN_NAME,
            DB::select(
                'SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
                ['library_copies']
            )
        );
    }

    public function up(): void
    {
        $columns = $this->existingColumns();

        if (! in_array('qr_generated_at', $columns, true)) {
            DB::statement('ALTER TABLE `library_copies` ADD COLUMN `qr_generated_at` TIMESTAMP NULL DEFAULT NULL');
        }
        if (! in_array('qr_printed_at', $columns, true)) {
            DB::statement('ALTER TABLE `library_copies` ADD COLUMN `qr_printed_at` TIMESTAMP NULL DEFAULT NULL');
        }
        if (! in_array('qr_printed_by', $columns, true)) {
            DB::statement('ALTER TABLE `library_copies` ADD COLUMN `qr_printed_by` VARCHAR(120) NULL DEFAULT NULL');
        }
    }

    public function down(): void
    {
        $columns = $this->existingColumns();
        foreach (['qr_generated_at', 'qr_printed_at', 'qr_printed_by'] as $name) {
            if (in_array($name, $columns, true)) {
                // Names are fixed literals, not request input.
                DB::statement('ALTER TABLE `library_copies` DROP COLUMN `'.$name.'`');
            }
        }
    }
};
