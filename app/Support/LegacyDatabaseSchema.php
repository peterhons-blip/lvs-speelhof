<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Column checks compatible with the legacy MariaDB version used by LVS.
 * Laravel 12 Schema::hasColumn() queries information_schema.generation_expression,
 * which is unavailable on this server.
 */
final class LegacyDatabaseSchema
{
    public static function hasColumn(string $table, string $column): bool
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return DB::connection()->getSchemaBuilder()->hasColumn($table, $column);
        }
        return DB::table('information_schema.COLUMNS')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->where('COLUMN_NAME', $column)
            ->exists();
    }
}
