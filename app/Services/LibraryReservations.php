<?php
namespace App\Services;

use Illuminate\Support\Facades\DB;

class LibraryReservations
{
    public function active()
    {
        return DB::table('external_library_requests')->where('request_type','reserve')->where('status','approved')
            ->where('reserved_until','>=',now('Europe/Brussels')->toDateString());
    }

    public function available($query,string $column='library_copies.id')
    {
        return $query->whereNotExists(fn($q)=>$q->selectRaw('1')->from('external_library_requests as holds')
            ->whereColumn('holds.reserved_copy_id',$column)->where('holds.request_type','reserve')
            ->where('holds.status','approved')->where('holds.reserved_until','>=',now('Europe/Brussels')->toDateString()));
    }
}
