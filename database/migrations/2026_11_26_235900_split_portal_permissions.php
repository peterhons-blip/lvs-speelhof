<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::transaction(function () {
            foreach (config('portal_permission_splits',[]) as $new => $old) {
                foreach (DB::table('portal_role_permissions')->where('permission',$old)->get() as $row) {
                    DB::table('portal_role_permissions')->insertOrIgnore(['role'=>$row->role,'permission'=>$new,'created_at'=>now(),'updated_at'=>now()]);
                }
                foreach (DB::table('portal_user_permissions')->where('permission',$old)->get() as $row) {
                    DB::table('portal_user_permissions')->insertOrIgnore(['user_id'=>$row->user_id,'permission'=>$new,'allowed'=>$row->allowed,'created_at'=>now(),'updated_at'=>now()]);
                }
            }
        });
    }
    public function down(): void
    {
        throw new \RuntimeException('Granular permission migration cannot be reversed safely.');
    }
};
