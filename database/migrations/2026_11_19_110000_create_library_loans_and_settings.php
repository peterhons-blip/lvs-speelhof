<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('library_loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('copy_id')->constrained('library_copies')->restrictOnDelete();
            // De bestaande leerlingen.id komt uit een legacy-databank; geen onveilige FK-casting.
            $table->unsignedBigInteger('leerling_id');
            $table->string('registered_by', 120)->nullable();
            $table->date('loan_date');
            $table->date('due_date');
            $table->dateTime('returned_at')->nullable();
            $table->unsignedTinyInteger('extensions')->default(0);
            $table->timestamps();
            $table->index(['leerling_id', 'returned_at'], 'library_loans_leerling_open_idx');
            $table->index(['returned_at', 'due_date'], 'library_loans_overdue_idx');
        });

        Schema::create('library_settings', function (Blueprint $table) {
            $table->string('key', 80)->primary();
            $table->string('value', 500);
            $table->timestamps();
        });

        foreach ([
            'loan_days' => '21',
            'max_loans' => '3',
            'max_extensions' => '2',
            'extension_days' => '21',
        ] as $key => $value) {
            DB::table('library_settings')->insert([
                'key' => $key, 'value' => $value, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('library_settings');
        Schema::dropIfExists('library_loans');
    }
};
