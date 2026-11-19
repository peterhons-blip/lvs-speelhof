<?php

namespace App\Http\Controllers;

use App\Jobs\RunSmartschoolStudentSync;
use App\Services\LibraryAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class SmartschoolStudentSyncController extends Controller
{
    public function index(Request $request, LibraryAccess $access): View
    {
        $access->requireManager($request);
        $ready = Schema::hasTable('smartschool_sync_runs')
            && Schema::hasColumn('leerlingen', 'scannable_code');

        $runs = $ready
            ? DB::table('smartschool_sync_runs')->where('school_id', 1)
                ->orderByDesc('id')->limit(12)->get()
            : collect();

        $pupils = DB::table('leerlingen')->where('schoolid', 1)->where('active', 1)->count();
        $codes = Schema::hasColumn('leerlingen', 'scannable_code')
            ? DB::table('leerlingen')->where('schoolid', 1)->where('active', 1)
                ->whereNotNull('scannable_code')->count() : 0;

        return view('beheer.smartschool-sync', compact('ready', 'runs', 'pupils', 'codes'));
    }

    public function start(Request $request, LibraryAccess $access): RedirectResponse
    {
        $access->requireManager($request);
        $data = $request->validate([
            'confirm_sync' => ['accepted'],
            'refresh_scancodes' => ['nullable', 'in:0,1'],
        ]);
        if (! Schema::hasTable('smartschool_sync_runs')
            || ! Schema::hasColumn('leerlingen', 'scannable_code')) {
            return back()->withErrors(['sync' => 'De migraties voor QR-codes en sync-status zijn nog niet uitgevoerd.']);
        }

        $active = DB::table('smartschool_sync_runs')->where('school_id', 1)
            ->whereIn('status', ['waiting', 'running'])->exists();
        if ($active) return back()->withErrors(['sync' =>
            'Er staat al een handmatige synchronisatie gepland of deze is bezig.']);

        $id = DB::table('smartschool_sync_runs')->insertGetId([
            'school_id' => 1,
            'requested_by' => $access->username($request),
            'status' => 'waiting',
            'refresh_scancodes' => (int) ($data['refresh_scancodes'] ?? 0),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            RunSmartschoolStudentSync::dispatch($id);
        } catch (\Throwable $e) {
            DB::table('smartschool_sync_runs')->where('id', $id)->update([
                'status' => 'failed', 'summary' => 'Kon opdracht niet in de wachtrij plaatsen.',
                'finished_at' => now(), 'updated_at' => now(),
            ]);
            \Illuminate\Support\Facades\Log::error('Leerlingensync in wachtrij plaatsen mislukt', [
                'run_id' => $id, 'error' => $e->getMessage(),
            ]);
            return back()->withErrors(['sync' => 'Wachtrij niet beschikbaar. Controleer Laravel-log.']);
        }
        return redirect()->route('beheer.smartschool-sync.index')->with('success',
            'Synchronisatie aangevraagd. De Smartschool-syncworker verwerkt deze opdracht.');
    }
}
