<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  if (!Schema::hasTable('portal_user_permissions')) Schema::create('portal_user_permissions',function(Blueprint $t){$t->id();$t->unsignedBigInteger('user_id');$t->string('permission',100);$t->boolean('allowed');$t->timestamps();$t->unique(['user_id','permission']);});
  Schema::table('meldingen',function(Blueprint $t){$t->boolean('vertrouwelijk')->default(false);});
  Schema::create('portal_permission_audit',function(Blueprint $t){$t->id();$t->string('actor',120);$t->string('target',120);$t->text('changes');$t->timestamps();});
 }
 public function down(): void {throw new \RuntimeException('Geen automatische rollback van vertrouwelijkheid: gegevens zouden openbaar worden.');}
};
