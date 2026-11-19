<?php

namespace App\Http\Controllers;

use App\Services\LibraryAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class ScheduledTasksController extends Controller
{
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
}
