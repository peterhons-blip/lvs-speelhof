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
        $access->requirePermission($request, 'beheer.scheduled_tasks.view');

        // These are all tasks currently registered in routes/console.php.
        $scheduledTasks = [
            'becoming_18' => ['title' => '18-jarigen en co-accounts', 'type' => 'Leerlingen', 'time' => '01.00',
                'description' => 'Controleert welke leerlingen binnenkort 18 jaar worden of vandaag 18 worden. Verwerkt de voorziene Smartschoolmeldingen en opvolging rond co-accounts bij meerderjarigheid.'],
            'birthdays' => ['title' => 'Verjaardagsberichten', 'type' => 'Leerlingen en personeel', 'time' => '02.00',
                'description' => 'Controleert dagelijks de geboortedatums van leerlingen en personeelsleden. Verstuurt de ingestelde verjaardagsberichten via Smartschool naar de betrokken personen.'],
            'student_sync' => ['title' => 'Leerlingensynchronisatie', 'type' => 'Leerlingen', 'time' => '03.00',
                'description' => 'Haalt de actuele leerlingen en klassen uit Smartschool op, verwerkt nieuwe of gewijzigde gegevens, actualiseert de actieve status en vult beschikbare QR-scancodes aan.'],
            'staff_sync' => ['title' => 'Personeelssynchronisatie', 'type' => 'Personeel', 'time' => '04.00',
                'description' => 'Haalt leerkrachten en administratieve medewerkers uit de Smartschool-groepen op. Maakt ontbrekende accounts aan, werkt bestaande gegevens en standaardrollen bij en markeert verdwenen medewerkers als inactief.'],
            'library_reminders' => ['title' => 'Bibliotheekherinneringen', 'type' => 'Bibliotheek / leerlingen', 'time' => '08.00',
                'description' => 'Controleert dagelijks openstaande bibliotheekuitleningen: herinneringen vooraf, op de inleverdatum, herhalingen na X dagen en indien ingeschakeld eenmalige escalatie naar beleid. Smartschoolverzendingen en drempels zijn instelbaar onder Bibliotheek > Instellingen.'],
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
        $access->requirePermission($request, 'beheer.scheduled_tasks.view');
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
        // An existing but empty log should never result in an unexplained blank page.
        if ($content === false || trim((string) $content) === '') {
            $content = 'Dit logbestand bestaat, maar bevat nog geen uitvoer. '
                .'Een taak kan succesvol lopen zonder tekst naar het log te schrijven. '
                .'Bekijk de uitvoeringsstatus onder Beheer > Geplande taken. '
                .'Let op: lokale logs op localhost zijn niet dezelfde bestanden als op de productieserver.';
        }

        return response((string) $content, 200)
            ->header('Content-Type', 'text/plain; charset=UTF-8')
            ->header('Cache-Control', 'no-store')
            ->header('X-Content-Type-Options', 'nosniff');
    }

}
