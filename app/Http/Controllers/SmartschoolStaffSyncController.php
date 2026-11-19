<?php

namespace App\Http\Controllers;

use App\Jobs\RunSmartschoolStaffSync;
use App\Services\LibraryAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class SmartschoolStaffSyncController extends Controller
{
    public function index(Request $request, LibraryAccess $access): View
    {
        $access->requireManager($request);
        $ready = Schema::hasTable('smartschool_staff_memberships')
            && Schema::hasTable('smartschool_staff_sync_runs')
            && DB::table('groups')->where('groupname', 'administratie')->exists()
            && \App\Support\LegacyDatabaseSchema::hasColumn('users', 'smartschool_active');
        $runs = $ready ? DB::table('smartschool_staff_sync_runs')
            ->where('school_id', 1)->orderByDesc('id')->limit(15)->get() : collect();
        $taskMonitoringEnabled = Schema::hasTable('scheduled_task_runs');
        $lastScheduledRun = $taskMonitoringEnabled
            ? DB::table('scheduled_task_runs')->where('task_key', 'staff_sync')->orderByDesc('id')->first()
            : null;
        $scheduledHistory = $taskMonitoringEnabled
            ? DB::table('scheduled_task_runs')->where('task_key', 'staff_sync')->orderByDesc('id')->limit(10)->get()
            : collect();
        return view('beheer.staff-sync', compact('ready', 'runs', 'taskMonitoringEnabled', 'lastScheduledRun', 'scheduledHistory'));
    }

    public function start(Request $request, LibraryAccess $access): RedirectResponse
    {
        $access->requireManager($request);
        $data = $request->validate([
            'confirm_sync' => ['accepted'],
            'apply_changes' => ['nullable', 'in:1'],
        ]);
        if (! Schema::hasTable('smartschool_staff_memberships')
            || ! Schema::hasTable('smartschool_staff_sync_runs')
            || ! \App\Support\LegacyDatabaseSchema::hasColumn('users', 'smartschool_active')) {
            return back()->withErrors(['sync' => 'Voer eerst de migratie voor personeelssynchronisatie uit.']);
        }
        if (DB::table('smartschool_staff_sync_runs')->where('school_id', 1)
            ->whereIn('status', ['waiting', 'running'])->exists()) {
            return back()->withErrors(['sync' => 'Er loopt al een personeelssynchronisatie.']);
        }

        $id = DB::table('smartschool_staff_sync_runs')->insertGetId([
            'school_id' => 1,
            'requested_by' => $access->username($request),
            'status' => 'waiting',
            'apply_changes' => (int) ($data['apply_changes'] ?? 0),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        try {
            RunSmartschoolStaffSync::dispatch($id);
        } catch (\Throwable $e) {
            DB::table('smartschool_staff_sync_runs')->where('id', $id)->update([
                'status' => 'failed', 'summary' => 'Wachtrij niet beschikbaar.',
                'finished_at' => now(), 'updated_at' => now(),
            ]);
            Log::error('Personeelssync kon niet in wachtrij geplaatst worden', [
                'run_id' => $id, 'error' => $e->getMessage(),
            ]);
            return back()->withErrors(['sync' => 'Wachtrij niet beschikbaar.']);
        }

        return redirect()->route('beheer.staff-sync.index')->with('success',
            ($data['apply_changes'] ?? false) ? 'Personeelssynchronisatie aangevraagd.' : 'Veilige preview aangevraagd.');
    }
}
