<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('portal_user_permissions')) return;
        Schema::create('portal_user_permissions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('permission', 100);
            $table->timestamps();
            $table->unique(['user_id', 'permission'], 'portal_user_permissions_user_permission_unique');
            $table->index('permission');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_user_permissions');
    }
};
