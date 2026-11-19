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
            && \App\Support\LegacyDatabaseSchema::hasColumn('leerlingen', 'scannable_code');

        $runs = $ready
            ? DB::table('smartschool_sync_runs')->where('school_id', 1)
                ->orderByDesc('id')->limit(12)->get()
                ->map(function ($run) {
                    $run->display_summary = $this->formatStudentSyncSummary($run->summary);
                    return $run;
                })
            : collect();

        $pupils = DB::table('leerlingen')->where('schoolid', 1)->where('active', 1)->count();
        $codes = \App\Support\LegacyDatabaseSchema::hasColumn('leerlingen', 'scannable_code')
            ? DB::table('leerlingen')->where('schoolid', 1)->where('active', 1)
                ->whereNotNull('scannable_code')->count() : 0;

        $scheduledTasks = [
            'becoming_18' => ['title' => '18-jarigen en co-accounts', 'time' => '01.00',
                'description' => 'Controle op leerlingen die binnenkort 18 worden, Smartschoolmeldingen en co-accounts.'],
            'birthdays' => ['title' => 'Verjaardagsberichten', 'time' => '02.00',
                'description' => 'Verjaardagen van leerlingen en personeel controleren en berichten versturen.'],
            'student_sync' => ['title' => 'Leerlingensynchronisatie', 'time' => '03.00',
                'description' => 'Klassen, actieve leerlingen, wijzigingen en Smartschool-QR-codes bijwerken.'],
            'library_reminders' => ['title' => 'Bibliotheekherinneringen', 'time' => '08.00',
                'description' => 'Vervallende boeken controleren; berichten alleen als de bibliotheekinstelling actief is.'],
        ];
        $taskHistory = collect();
        $taskMonitoringEnabled = Schema::hasTable('scheduled_task_runs');
        if ($taskMonitoringEnabled) {
            $taskHistory = DB::table('scheduled_task_runs')
                ->orderByDesc('id')->limit(30)->get();
        }
        $lastTaskRuns = $taskHistory->unique('task_key')->keyBy('task_key');

        return view('beheer.smartschool-sync', compact(
            'ready', 'runs', 'pupils', 'codes',
            'scheduledTasks', 'taskHistory', 'lastTaskRuns', 'taskMonitoringEnabled'
        ));
    }

    public function start(Request $request, LibraryAccess $access): RedirectResponse
    {
        $access->requireManager($request);
        $data = $request->validate([
            'confirm_sync' => ['accepted'],
            'refresh_scancodes' => ['nullable', 'in:0,1'],
        ]);
        if (! Schema::hasTable('smartschool_sync_runs')
            || ! \App\Support\LegacyDatabaseSchema::hasColumn('leerlingen', 'scannable_code')) {
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
    private function formatStudentSyncSummary(?string $summary): string
    {
        if (! $summary) return 'Nog geen resultaat beschikbaar.';
        if (preg_match('/Processed=(\\d+).*?created=(\\d+).*?updated=(\\d+).*?scancodes_updated=(\\d+).*?scancodes_missing=(\\d+).*?scancodes_conflicts=(\\d+).*?failed_classes=(\\d+).*?empty_classes=(\\d+)/', $summary, $m)) {
            $parts = [
                "{$m[1]} leerlingen verwerkt", "{$m[2]} nieuw", "{$m[3]} bijgewerkt",
                "{$m[4]} QR-codes bijgewerkt"
            ];
            if ((int) $m[5] > 0) $parts[] = "{$m[5]} QR-codes ontbreken";
            if ((int) $m[6] > 0) $parts[] = "{$m[6]} codeconflicten";
            if ((int) $m[7] > 0) $parts[] = "{$m[7]} klassen niet opgehaald";
            if ((int) $m[8] > 0) $parts[] = "{$m[8]} lege klassen; deactivering overgeslagen";
            return implode(' · ', $parts).'.';
        }
        return 'Raadpleeg het technische log voor details.';
    }

}
