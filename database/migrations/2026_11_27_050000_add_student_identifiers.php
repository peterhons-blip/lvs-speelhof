<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Support\LegacyDatabaseSchema;

return new class extends Migration {
    public function up(): void
    {
        foreach (['stamnummer','InformatInternNr'] as $column) {
            if (!LegacyDatabaseSchema::hasColumn('leerlingen',$column)) {
                Schema::table('leerlingen',function(Blueprint $table) use ($column) {
                    $table->string($column,100)->nullable()->index();
                });
            }
        }
    }

    public function down(): void
    {
        // Existing legacy columns and imported identifiers must not be destroyed.
    }
};
