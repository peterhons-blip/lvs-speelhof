<?php

namespace App\Http\Controllers;

use App\Services\LibraryAccess;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class ScheduledTasksController extends Controller
{
    private const LOG_FILES = [
        'becoming_18' => 'leerlingen_check.log',
        'birthdays' => 'verjaardagen_check.log',
        'student_sync' => 'smartschool_sync_leerlingen.log',
        'staff_sync' => 'smartschool_sync_personeel.log',
        'library_reminders' => 'bibliotheek_herinneringen.log',
    ];

    public function index(Request $request, LibraryAccess $access): View
    {
        $access->requireManager($request);

        // These are all tasks currently registered in routes/console.php.
        $scheduledTasks = [
            'becoming_18' => ['title' => '18-jarigen en co-accounts', 'type' => 'Leerlingen', 'time' => '01.00',
                'description' => 'Controle op 18-jarigen, waarschuwingen en co-accounts.'],
            'birthdays' => ['title' => 'Verjaardagsberichten', 'type' => 'Leerlingen en personeel', 'time' => '02.00',
                'description' => 'Verjaardagen van leerlingen en medewerkers nakijken en berichten versturen.'],
            'student_sync' => ['title' => 'Leerlingensynchronisatie', 'type' => 'Leerlingen', 'time' => '03.00',
                'description' => 'Leerlingen, klassen en Smartschool-QR-codes bijwerken.'],
            'staff_sync' => ['title' => 'Personeelssynchronisatie', 'type' => 'Personeel', 'time' => '04.00',
                'description' => 'Personeelsaccounts bijwerken en verdwenen medewerkers inactief markeren.'],
            'library_reminders' => ['title' => 'Bibliotheekherinneringen', 'type' => 'Bibliotheek / leerlingen', 'time' => '08.00',
                'description' => 'Vervallende uitleningen controleren; berichten uitsluitend als herinneringen actief zijn.'],
        ];

        $taskMonitoringEnabled = Schema::hasTable('scheduled_task_runs');
        $taskHistory = $taskMonitoringEnabled
            ? DB::table('scheduled_task_runs')->orderByDesc('id')->limit(50)->get()
            : collect();
        $lastTaskRuns = $taskHistory->unique('task_key')->keyBy('task_key');

        return view('beheer.scheduled-tasks', compact(
            'scheduledTasks', 'taskMonitoringEnabled', 'taskHistory', 'lastTaskRuns'
        ));
    }
    public function log(Request $request, LibraryAccess $access, string $task): Response
    {
        $access->requireManager($request);
        abort_unless(array_key_exists($task, self::LOG_FILES), 404);
        $path = storage_path('logs/'.self::LOG_FILES[$task]);
        if (! is_file($path) || ! is_readable($path)) {
            return response('Nog geen leesbaar logbestand beschikbaar voor deze taak.', 200)
                ->header('Content-Type', 'text/plain; charset=UTF-8')
                ->header('Cache-Control', 'no-store');
        }

        // Read a capped tail from a fixed whitelist. Never accept a user-supplied file path.
        $handle = fopen($path, 'rb');
        if ($handle === false) abort(404);
        try {
            $size = filesize($path);
            if ($size === false) $size = 0;
            if ($size > 65536) fseek($handle, -65536, SEEK_END);
            $content = stream_get_contents($handle, 65536);
        } finally {
            fclose($handle);
        }
        return response((string) $content, 200)
            ->header('Content-Type', 'text/plain; charset=UTF-8')
            ->header('Cache-Control', 'no-store')
            ->header('X-Content-Type-Options', 'nosniff');
    }

}
