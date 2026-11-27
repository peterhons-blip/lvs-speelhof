<?php

namespace App\Http\Controllers;

use App\Services\DatabaseIndexAudit;
use App\Services\LibraryAccess;
use App\Services\PerformanceLogReader;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SystemPerformanceController extends Controller
{
    public function index(Request $request, LibraryAccess $access, PerformanceLogReader $reader, DatabaseIndexAudit $audit)
    {
        abort_unless($access->role($request) === 'ict_coordinator' && !$request->session()->has('portal_preview'), 403);
        $rows = $reader->read(storage_path('logs/laravel.log'));
        // Support the standard daily Laravel log channel as well.
        $daily = storage_path('logs/laravel-'.now()->format('Y-m-d').'.log');
        $rows = array_merge($rows, $reader->read($daily));
        usort($rows, fn($a,$b)=>strcmp($b['at'],$a['at']));
        $rows = array_slice($rows,0,2000);
        $period = $request->query('period','60');
        if (!in_array($period,['15','60','1440','all'],true)) $period='60';
        if ($period !== 'all') {
            $cutoff = now()->subMinutes((int)$period)->format('Y-m-d H:i:s');
            $rows = array_values(array_filter($rows,fn($row)=>$row['at'] >= $cutoff));
        }
        $summary = $reader->summarize($rows);
        $recent = array_slice($rows,0,30);
        $version = null;
        $latency = null;
        $size = null;
        $indexes = [];
        $databaseError = false;
        try {
            $version = DB::selectOne('SELECT VERSION() AS version')->version;
            $start = microtime(true);
            for ($i=0;$i<5;$i++) DB::selectOne('SELECT 1');
            $latency = (microtime(true)-$start)*1000/5;
            $size = DB::selectOne('SELECT COALESCE(SUM(DATA_LENGTH + INDEX_LENGTH),0) AS bytes FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()')->bytes;
            $indexes = $audit->inspect();
        } catch (\Throwable $e) {
            $databaseError = true;
        }
        $driver = config('database.default');
        $host = config('database.connections.'.$driver.'.host');
        $local = in_array($host,['127.0.0.1','localhost','::1'],true);
        $disk = @disk_free_space(storage_path());
        $response = response()->view('beheer.performance', compact('rows','summary','recent','period','version','latency','size','indexes','databaseError','local','disk'));
        $response->headers->set('Cache-Control','private, no-store');
        return $response;
    }
}
