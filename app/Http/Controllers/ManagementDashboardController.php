<?php

namespace App\Http\Controllers;

use App\Services\LibraryAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class ManagementDashboardController extends Controller
{
    public function index(Request $request, LibraryAccess $access): View
    {
        abort_unless($access->isManager($request), 403);

        $sections = [
            ['permission' => 'beheer.staff.manage', 'route' => 'beheer.users.index', 'title' => 'Personeelsbeheer', 'description' => 'Medewerkers, schoolrollen en aanmeldtoegang beheren.', 'icon' => 'users'],
            ['permission' => 'beheer.students.manage', 'route' => 'beheer.students.index', 'title' => 'Leerlingenbeheer', 'description' => 'Leerlingen zoeken, gegevens bekijken en wijzigen.', 'icon' => 'graduation-cap'],
            ['permission' => 'beheer.permissions.manage', 'route' => 'beheer.permissions.index', 'title' => 'Rollen en rechten', 'description' => 'Toegangsrechten per schoolrol instellen.', 'icon' => 'shield-check'],
            ['permission' => 'beheer.smartschool_test.send', 'route' => 'beheer.smartschool-test.create', 'title' => 'Smartschool-testbericht', 'description' => 'Een testbericht via Smartschool versturen.', 'icon' => 'mail'],
            ['permission' => 'beheer.student_sync.manage', 'route' => 'beheer.smartschool-sync.index', 'title' => 'Leerlingensynchronisatie', 'description' => 'Leerlinggegevens handmatig synchroniseren.', 'icon' => 'refresh-cw'],
            ['permission' => 'beheer.staff_sync.manage', 'route' => 'beheer.staff-sync.index', 'title' => 'Personeelssynchronisatie', 'description' => 'Personeelsgegevens handmatig synchroniseren.', 'icon' => 'refresh-cw'],
            ['permission' => 'beheer.birthdays.manage', 'route' => 'beheer.birthdays.index', 'title' => 'Verjaardagsberichten', 'description' => 'Ontvangers, Smartschool, e-mail en felicitatieteksten instellen.', 'icon' => 'cake'],
            ['permission' => 'beheer.scheduled_tasks.view', 'route' => 'beheer.scheduled-tasks.index', 'title' => 'Geplande taken', 'description' => 'Nachtelijke taken, laatste uitvoeringen en logs bekijken.', 'icon' => 'clock'],
        ];
        $sections = array_values(array_filter($sections,
            fn ($section) => $access->has($request, $section['permission'])));

        $stats = [];
        if ($access->has($request, 'beheer.staff.manage') && Schema::hasTable('users')) {
            $activeStaff = DB::table('users')->where('schoolid', 1)
                ->where(function ($q) { $q->whereNull('smartschool_active')->orWhere('smartschool_active', 1); });
            $stats[] = [
                'label' => 'Personeelsaccounts in Smartschool',
                'value' => (clone $activeStaff)->count(),
                'detail' => 'Waarvan '.(clone $activeStaff)->where('allowed_login', 1)->count().' met LVS-aanmeldtoegang',
            ];
        }
        if ($access->has($request, 'beheer.students.manage') && Schema::hasTable('leerlingen')) {
            $stats[] = [
                'label' => 'Actieve leerlingen',
                'value' => DB::table('leerlingen')->where('schoolid', 1)->where('active', 1)->count(),
                'detail' => 'Volgens de actuele LVS-databank',
            ];
        }

        $recentTasks = collect();
        if ($access->has($request, 'beheer.scheduled_tasks.view') && Schema::hasTable('scheduled_task_runs')) {
            $titles = [
                'becoming_18' => '18-jarigen en co-accounts',
                'birthdays' => 'Verjaardagsberichten',
                'student_sync' => 'Leerlingensynchronisatie',
                'staff_sync' => 'Personeelssynchronisatie',
                'library_reminders' => 'Bibliotheekherinneringen',
            ];
            $recentTasks = DB::table('scheduled_task_runs')->orderByDesc('id')->limit(50)->get()
                ->unique('task_key')->take(5)
                ->map(function ($run) use ($titles) {
                    $run->title = $titles[$run->task_key] ?? $run->task_key;
                    return $run;
                })->values();
        }

        return view('beheer.dashboard', compact('sections', 'stats', 'recentTasks'));
    }
}
