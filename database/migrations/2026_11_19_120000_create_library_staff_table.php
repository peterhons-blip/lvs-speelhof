<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('portal_staff', function (Blueprint $table) {
            $table->id();
            $table->string('smartschool_username', 120)->unique();
            $table->string('display_name', 160)->nullable();
            $table->string('role', 32);
            $table->boolean('is_active')->default(true);
            $table->string('changed_by', 120)->nullable();
            $table->timestamps();
            $table->index(['role', 'is_active']);
        });

        Schema::create('portal_role_permissions', function (Blueprint $table) {
            $table->id();
            $table->string('role', 32);
            $table->string('permission', 100);
            $table->timestamps();
            $table->unique(['role', 'permission'], 'portal_role_permission_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_role_permissions');
        Schema::dropIfExists('portal_staff');
    }
};
