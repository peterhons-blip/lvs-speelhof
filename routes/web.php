<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LeerlingenController;
use App\Http\Controllers\MeldingController;
use App\Http\Controllers\Auth\SmartschoolController;
use App\Http\Middleware\EnsureSmartschoolAuthenticated;



// ===== Publiek / SSO =====
Route::get('/', [\App\Http\Controllers\DashboardController::class, 'index'])->name('home');   // <— publieke landing page

Route::get('/login', [SmartschoolController::class, 'redirect'])->name('login');
Route::get('/auth/smartschool/callback', [SmartschoolController::class, 'callback'])->name('smartschool.callback');
Route::get('/logout', [SmartschoolController::class, 'logout'])->name('logout');

// ===== Protected (na SSO) =====
Route::middleware([EnsureSmartschoolAuthenticated::class])->group(function () {
    // Bibliotheek: voorlopig alleen de afgeschermde startpagina.
    Route::view('/bibliotheek', 'bibliotheek.index')->name('bibliotheek.index')->middleware(\App\Http\Middleware\RequirePortalPermission::class.':bibliotheek.view');

    Route::get('/bibliotheek/smartschool-berichten', [\App\Http\Controllers\LibraryMessageController::class, 'index'])
        ->name('bibliotheek.messages.index')
        ->middleware(\App\Http\Middleware\RequirePortalPermission::class.':bibliotheek.messages.manage');
    Route::post('/bibliotheek/smartschool-berichten', [\App\Http\Controllers\LibraryMessageController::class, 'update'])
        ->name('bibliotheek.messages.update')
        ->middleware(\App\Http\Middleware\RequirePortalPermission::class.':bibliotheek.messages.manage');
    Route::get('/bibliotheek/rapportages', [\App\Http\Controllers\LibraryReportController::class, 'index'])
        ->name('bibliotheek.reports.index')
        ->middleware(\App\Http\Middleware\RequirePortalPermission::class.':bibliotheek.loans.view');
    Route::get('/bibliotheek/rapportages/export', [\App\Http\Controllers\LibraryReportController::class, 'export'])
        ->name('bibliotheek.reports.export')
        ->middleware(\App\Http\Middleware\RequirePortalPermission::class.':bibliotheek.loans.view');
    Route::prefix('bibliotheek/uitleningen')->name('bibliotheek.loans.')->group(function () {
        Route::get('/', [\App\Http\Controllers\LibraryLoanController::class, 'index'])->name('index')->middleware(\App\Http\Middleware\RequirePortalPermission::class.':bibliotheek.loans.view');
        Route::get('/nieuw', [\App\Http\Controllers\LibraryLoanController::class, 'create'])->name('create')->middleware(\App\Http\Middleware\RequirePortalPermission::class.':bibliotheek.loans.manage');
        Route::post('/nieuw', [\App\Http\Controllers\LibraryLoanController::class, 'store'])->name('store')->middleware(\App\Http\Middleware\RequirePortalPermission::class.':bibliotheek.loans.manage');
        Route::get('/leerling-scan', [\App\Http\Controllers\LibraryLoanController::class, 'lookupStudent'])->middleware(['throttle:60,1', \App\Http\Middleware\RequirePortalPermission::class.':bibliotheek.loans.manage'])->name('student-scan');
        Route::get('/exemplaar-scan', [\App\Http\Controllers\LibraryLoanController::class, 'lookupCopy'])->middleware(['throttle:90,1', \App\Http\Middleware\RequirePortalPermission::class.':bibliotheek.loans.manage'])->name('copy-scan');
        Route::get('/boeken-zoeken', [\App\Http\Controllers\LibraryLoanController::class, 'searchBooks'])
            ->middleware(['throttle:60,1', \App\Http\Middleware\RequirePortalPermission::class.':bibliotheek.loans.manage'])
            ->name('books-search');
        Route::get('/boeken/{book}/beschikbare-exemplaren', [\App\Http\Controllers\LibraryLoanController::class, 'availableCopies'])
            ->whereNumber('book')
            ->middleware(['throttle:60,1', \App\Http\Middleware\RequirePortalPermission::class.':bibliotheek.loans.manage'])
            ->name('available-copies');
        Route::post('/inleveren-met-barcode', [\App\Http\Controllers\LibraryLoanController::class, 'returnByBarcode'])->name('return-barcode')->middleware(\App\Http\Middleware\RequirePortalPermission::class.':bibliotheek.loans.manage');
        Route::get('/leerlingen-zoeken', [\App\Http\Controllers\LibraryLoanController::class, 'students'])->middleware('throttle:40,1')->name('students')->middleware(\App\Http\Middleware\RequirePortalPermission::class.':bibliotheek.loans.manage');
        Route::get('/personeel-zoeken', [\App\Http\Controllers\LibraryLoanController::class, 'staff'])
            ->middleware(['throttle:40,1', \App\Http\Middleware\RequirePortalPermission::class.':bibliotheek.loans.manage'])
            ->name('staff');
        Route::get('/instellingen', [\App\Http\Controllers\LibraryLoanController::class, 'settings'])->name('settings')->middleware(\App\Http\Middleware\RequirePortalPermission::class.':bibliotheek.settings.manage');
        Route::post('/instellingen', [\App\Http\Controllers\LibraryLoanController::class, 'saveSettings'])->name('settings.save')->middleware(\App\Http\Middleware\RequirePortalPermission::class.':bibliotheek.settings.manage');
        Route::get('/{loan}/herinneringen', [\App\Http\Controllers\LibraryLoanController::class, 'editReminders'])->whereNumber('loan')->name('reminders.edit')->middleware(\App\Http\Middleware\RequirePortalPermission::class.':bibliotheek.loans.manage');
        Route::put('/{loan}/herinneringen', [\App\Http\Controllers\LibraryLoanController::class, 'updateReminders'])->whereNumber('loan')->name('reminders.update')->middleware(\App\Http\Middleware\RequirePortalPermission::class.':bibliotheek.loans.manage');
        Route::post('/{loan}/herinnering-versturen', [\App\Http\Controllers\LibraryLoanController::class, 'sendManualReminder'])
            ->whereNumber('loan')->name('reminders.send')
            ->middleware(['throttle:10,1', \App\Http\Middleware\RequirePortalPermission::class.':bibliotheek.loans.manage']);
        Route::post('/{loan}/inleveren', [\App\Http\Controllers\LibraryLoanController::class, 'returnLoan'])->whereNumber('loan')->name('return')->middleware(\App\Http\Middleware\RequirePortalPermission::class.':bibliotheek.loans.manage');
        Route::post('/{loan}/verlengen', [\App\Http\Controllers\LibraryLoanController::class, 'extend'])->whereNumber('loan')->name('extend')->middleware(\App\Http\Middleware\RequirePortalPermission::class.':bibliotheek.loans.manage');
    });

    Route::prefix('bibliotheek/boeken')->name('bibliotheek.books.')->group(function () {
        Route::get('/', [\App\Http\Controllers\LibraryBookController::class, 'index'])->name('index')->middleware(\App\Http\Middleware\RequirePortalPermission::class.':bibliotheek.books.view');
        Route::get('/nieuw', [\App\Http\Controllers\LibraryBookController::class, 'create'])->name('create')->middleware(\App\Http\Middleware\RequirePortalPermission::class.':bibliotheek.books.manage');
        Route::get('/qr-labels', [\App\Http\Controllers\LibraryBookController::class, 'qrLabels'])
            ->name('qr-labels')->middleware(\App\Http\Middleware\RequirePortalPermission::class.':bibliotheek.books.view');
        Route::post('/qr-labels/status', [\App\Http\Controllers\LibraryBookController::class, 'confirmQrPrinted'])
            ->name('qr-status')->middleware(\App\Http\Middleware\RequirePortalPermission::class.':bibliotheek.books.manage');
        Route::post('/qr-labels/generated', [\App\Http\Controllers\LibraryBookController::class, 'recordQrGenerated'])
            ->name('qr-generated')->middleware(['throttle:30,1', \App\Http\Middleware\RequirePortalPermission::class.':bibliotheek.books.manage']);
        Route::get('/opzoekdienst-test', [\App\Http\Controllers\LibraryBookController::class, 'lookupDiagnostics'])
            ->middleware('throttle:6,1')
            ->middleware(\App\Http\Middleware\RequirePortalPermission::class.':bibliotheek.books.manage')
            ->name('lookup-diagnostics');
        Route::get('/isbn-opzoeken', [\App\Http\Controllers\LibraryBookController::class, 'lookup'])->middleware('throttle:30,1')->name('lookup')->middleware(\App\Http\Middleware\RequirePortalPermission::class.':bibliotheek.books.view');
        Route::get('/zoeken-op-naam', [\App\Http\Controllers\LibraryBookController::class, 'searchExternal'])->middleware('throttle:20,1')->name('search-external')->middleware(\App\Http\Middleware\RequirePortalPermission::class.':bibliotheek.books.view');
        Route::get('/edities', [\App\Http\Controllers\LibraryBookController::class, 'editions'])->middleware('throttle:30,1')->name('editions')->middleware(\App\Http\Middleware\RequirePortalPermission::class.':bibliotheek.books.view');
        Route::get('/werkbeschrijving', [\App\Http\Controllers\LibraryBookController::class, 'workDetails'])->middleware('throttle:30,1')->name('work-details')->middleware(\App\Http\Middleware\RequirePortalPermission::class.':bibliotheek.books.view');
        Route::get('/importhistoriek', [\App\Http\Controllers\LibraryImportController::class, 'history'])->name('import.history')->middleware(\App\Http\Middleware\RequirePortalPermission::class.':bibliotheek.books.view');
        Route::get('/importeren', [\App\Http\Controllers\LibraryImportController::class, 'create'])->name('import.create')->middleware(\App\Http\Middleware\RequirePortalPermission::class.':bibliotheek.books.view');
        Route::post('/importeren', [\App\Http\Controllers\LibraryImportController::class, 'store'])->name('import.store')->middleware(\App\Http\Middleware\RequirePortalPermission::class.':bibliotheek.import.manage');
        Route::get('/importeren/{batch}', [\App\Http\Controllers\LibraryImportController::class, 'show'])->whereNumber('batch')->name('import.show')->middleware(\App\Http\Middleware\RequirePortalPermission::class.':bibliotheek.books.view');
        Route::get('/importeren/{batch}/status', [\App\Http\Controllers\LibraryImportController::class, 'status'])->whereNumber('batch')->name('import.status')->middleware(\App\Http\Middleware\RequirePortalPermission::class.':bibliotheek.books.view');
        Route::post('/importeren/{batch}/stop', [\App\Http\Controllers\LibraryImportController::class, 'stop'])
            ->whereNumber('batch')->name('import.stop')
            ->middleware([\App\Http\Middleware\RequirePortalPermission::class.':bibliotheek.import.manage', 'throttle:10,1']);
        Route::get('/{book}/bewerken', [\App\Http\Controllers\LibraryBookController::class, 'edit'])->whereNumber('book')->name('edit')->middleware(\App\Http\Middleware\RequirePortalPermission::class.':bibliotheek.books.manage');
        Route::put('/{book}', [\App\Http\Controllers\LibraryBookController::class, 'update'])->whereNumber('book')->name('update')->middleware(\App\Http\Middleware\RequirePortalPermission::class.':bibliotheek.books.manage');
        Route::delete('/{book}', [\App\Http\Controllers\LibraryBookController::class, 'destroy'])->whereNumber('book')->name('destroy')->middleware(\App\Http\Middleware\RequirePortalPermission::class.':bibliotheek.books.manage');
        Route::post('/', [\App\Http\Controllers\LibraryBookController::class, 'store'])->name('store')->middleware(\App\Http\Middleware\RequirePortalPermission::class.':bibliotheek.books.manage');
        Route::get('/{book}', [\App\Http\Controllers\LibraryBookController::class, 'show'])->whereNumber('book')->name('show')->middleware(\App\Http\Middleware\RequirePortalPermission::class.':bibliotheek.books.view');
        Route::post('/{book}/exemplaren', [\App\Http\Controllers\LibraryBookController::class, 'addCopy'])->whereNumber('book')->name('copies.store')->middleware(\App\Http\Middleware\RequirePortalPermission::class.':bibliotheek.books.manage');
        Route::get('/{book}/exemplaren/{copy}/bewerken', [\App\Http\Controllers\LibraryBookController::class, 'editCopy'])->whereNumber(['book', 'copy'])->name('copies.edit')->middleware(\App\Http\Middleware\RequirePortalPermission::class.':bibliotheek.books.manage');
        Route::put('/{book}/exemplaren/{copy}', [\App\Http\Controllers\LibraryBookController::class, 'updateCopy'])->whereNumber(['book', 'copy'])->name('copies.update')->middleware(\App\Http\Middleware\RequirePortalPermission::class.':bibliotheek.books.manage');
        Route::delete('/{book}/exemplaren/{copy}', [\App\Http\Controllers\LibraryBookController::class, 'destroyCopy'])->whereNumber(['book', 'copy'])->name('copies.destroy')->middleware(\App\Http\Middleware\RequirePortalPermission::class.':bibliotheek.books.manage');

    });

    Route::get('/beheer/smartschool-test/ontvangers', [\App\Http\Controllers\SmartschoolTestMessageController::class, 'recipients'])
        ->middleware([\App\Http\Middleware\RequirePortalPermission::class.':beheer.smartschool_test.send', 'throttle:45,1'])
        ->name('beheer.smartschool-test.recipients');

    Route::get('/beheer/verzonden-items', [\App\Http\Controllers\SentItemsController::class, 'index'])
        ->middleware(\App\Http\Middleware\RequirePortalPermission::class.':beheer.sent_items.view')
        ->name('beheer.sent-items.index');
    Route::get('/beheer/verjaardagsberichten', [\App\Http\Controllers\BirthdaySettingsController::class, 'index'])
        ->middleware(\App\Http\Middleware\RequirePortalPermission::class.':beheer.birthdays.manage')
        ->name('beheer.birthdays.index');
    Route::post('/beheer/verjaardagsberichten', [\App\Http\Controllers\BirthdaySettingsController::class, 'update'])
        ->middleware(\App\Http\Middleware\RequirePortalPermission::class.':beheer.birthdays.manage')
        ->name('beheer.birthdays.update');
    Route::post('/beheer/verjaardagsberichten/coaccounts', [\App\Http\Controllers\BirthdaySettingsController::class, 'updatePrivacy'])
        ->middleware(\App\Http\Middleware\RequirePortalPermission::class.':beheer.birthdays.manage')
        ->name('beheer.birthdays.privacy.update');
    Route::post('/beheer/verjaardagsberichten/vandaag-18', [\App\Http\Controllers\BirthdaySettingsController::class, 'updateAdult'])
        ->middleware(\App\Http\Middleware\RequirePortalPermission::class.':beheer.birthdays.manage')
        ->name('beheer.birthdays.adult.update');
    Route::post('/beheer/verjaardagsberichten/waarschuwing-18', [\App\Http\Controllers\BirthdaySettingsController::class, 'updateWarning'])
        ->middleware(\App\Http\Middleware\RequirePortalPermission::class.':beheer.birthdays.manage')
        ->name('beheer.birthdays.warning.update');
    Route::post('/beheer/verjaardagsberichten/overzicht-18', [\App\Http\Controllers\BirthdaySettingsController::class, 'updateOverviewRecipients'])
        ->middleware(\App\Http\Middleware\RequirePortalPermission::class.':beheer.birthdays.manage')
        ->name('beheer.birthdays.overview.update');
    Route::get('/beheer', [\App\Http\Controllers\ManagementDashboardController::class, 'index'])->name('beheer.dashboard');

    // Handmatige Smartschool-testzending, uitsluitend na ICT-login en expliciete submit.
    Route::get('/beheer/smartschool-test', [\App\Http\Controllers\SmartschoolTestMessageController::class, 'create'])
        ->middleware(\App\Http\Middleware\RequirePortalPermission::class.':beheer.smartschool_test.send')
        ->name('beheer.smartschool-test.create');
    Route::post('/beheer/smartschool-test', [\App\Http\Controllers\SmartschoolTestMessageController::class, 'send'])
        ->middleware([\App\Http\Middleware\RequirePortalPermission::class.':beheer.smartschool_test.send', 'throttle:5,1'])
        ->name('beheer.smartschool-test.send');

    // Leerlingensynchronisatie handmatig starten via de wachtrij (alleen ICT-beheer).
    Route::get('/beheer/leerlingensync', [\App\Http\Controllers\SmartschoolStudentSyncController::class, 'index'])
        ->middleware(\App\Http\Middleware\RequirePortalPermission::class.':beheer.student_sync.manage')
        ->name('beheer.smartschool-sync.index');
    Route::post('/beheer/leerlingensync', [\App\Http\Controllers\SmartschoolStudentSyncController::class, 'start'])
        ->middleware([\App\Http\Middleware\RequirePortalPermission::class.':beheer.student_sync.manage', 'throttle:3,1'])
        ->name('beheer.smartschool-sync.start');

    // Personeelssynchronisatie: uitsluitend ICT-beheer, preview als standaard.
    Route::get('/beheer/personeelssync', [\App\Http\Controllers\SmartschoolStaffSyncController::class, 'index'])
        ->middleware(\App\Http\Middleware\RequirePortalPermission::class.':beheer.staff_sync.manage')
        ->name('beheer.staff-sync.index');
    Route::post('/beheer/personeelssync', [\App\Http\Controllers\SmartschoolStaffSyncController::class, 'start'])
        ->middleware([\App\Http\Middleware\RequirePortalPermission::class.':beheer.staff_sync.manage', 'throttle:3,1'])
        ->name('beheer.staff-sync.start');

    // Alle geregistreerde nachtelijke taken en hun loghistoriek, uitsluitend voor ICT-beheer.
    Route::get('/beheer/geplande-taken', [\App\Http\Controllers\ScheduledTasksController::class, 'index'])
        ->middleware(\App\Http\Middleware\RequirePortalPermission::class.':beheer.scheduled_tasks.view')
        ->name('beheer.scheduled-tasks.index');
    Route::get('/beheer/geplande-taken/{task}/log', [\App\Http\Controllers\ScheduledTasksController::class, 'log'])
        ->middleware(\App\Http\Middleware\RequirePortalPermission::class.':beheer.scheduled_tasks.view')
        ->where('task', '[a-z0-9_]+')
        ->name('beheer.scheduled-tasks.log');

    // Leerlingenbeheer: bestaande leerlingen bekijken en beperkte gegevens corrigeren, uitsluitend ICT.
    Route::get('/beheer/leerlingen', [\App\Http\Controllers\StudentManagementController::class, 'index'])
        ->middleware(\App\Http\Middleware\RequirePortalPermission::class.':beheer.students.manage')
        ->name('beheer.students.index');
    Route::post('/beheer/leerlingen/bulk', [\App\Http\Controllers\StudentManagementController::class, 'bulkUpdate'])
        ->middleware(\App\Http\Middleware\RequirePortalPermission::class.':beheer.students.manage')
        ->name('beheer.students.bulk');
    Route::get('/beheer/leerlingen/{student}', [\App\Http\Controllers\StudentManagementController::class, 'show'])
        ->whereNumber('student')
        ->middleware(\App\Http\Middleware\RequirePortalPermission::class.':beheer.students.manage')
        ->name('beheer.students.show');
    Route::put('/beheer/leerlingen/{student}', [\App\Http\Controllers\StudentManagementController::class, 'update'])
        ->whereNumber('student')
        ->middleware(\App\Http\Middleware\RequirePortalPermission::class.':beheer.students.manage')
        ->name('beheer.students.update');

    // Centraal portaalbeheer: rechten per rol en medewerker (ICT-coordinator heeft standaard alle rechten).
    Route::prefix('beheer')->name('beheer.')->group(function () {
        Route::get('/gebruikers', [\App\Http\Controllers\PortalPermissionsController::class, 'users'])->middleware(\App\Http\Middleware\RequirePortalPermission::class.':beheer.staff.manage')->name('users.index');
        Route::post('/gebruikers/bulk', [\App\Http\Controllers\PortalPermissionsController::class, 'bulkUpdate'])
            ->middleware(\App\Http\Middleware\RequirePortalPermission::class.':beheer.staff.manage')
            ->name('users.bulk');
        Route::get('/gebruikers-zoeken', [\App\Http\Controllers\PortalPermissionsController::class, 'searchUsers'])->middleware([\App\Http\Middleware\RequirePortalPermission::class.':beheer.staff.manage', 'throttle:45,1'])->name('users.search');
        Route::post('/gebruikers', [\App\Http\Controllers\PortalPermissionsController::class, 'storeUser'])->middleware(\App\Http\Middleware\RequirePortalPermission::class.':beheer.staff.manage')->name('users.store');
        Route::put('/gebruikers/{staff}', [\App\Http\Controllers\PortalPermissionsController::class, 'updateUser'])->whereNumber('staff')->middleware(\App\Http\Middleware\RequirePortalPermission::class.':beheer.staff.manage')->name('users.update');
        Route::patch('/gebruikers/{staff}/aanmelden', [\App\Http\Controllers\PortalPermissionsController::class, 'updateLogin'])->whereNumber('staff')->middleware(\App\Http\Middleware\RequirePortalPermission::class.':beheer.staff.manage')->name('users.login');
        Route::get('/rechten', [\App\Http\Controllers\PortalPermissionsController::class, 'index'])->middleware(\App\Http\Middleware\RequirePortalPermission::class.':beheer.permissions.manage')->name('permissions.index');
        Route::post('/rechten', [\App\Http\Controllers\PortalPermissionsController::class, 'update'])->middleware(\App\Http\Middleware\RequirePortalPermission::class.':beheer.permissions.manage')->name('permissions.update');
    });

    // LVS: all records remain staff-only. Future parent/student access is not active.
    Route::get('/lvs/opvolging', [\App\Http\Controllers\LvsFollowupsController::class, 'index'])
        ->middleware(\App\Http\Middleware\RequirePortalPermission::class.':lvs.followups.view')
        ->name('lvs.followups.index');
    Route::post('/lvs/opvolging/{event}/afhandelen', [\App\Http\Controllers\LvsFollowupsController::class, 'resolve'])
        ->whereNumber('event')
        ->middleware(\App\Http\Middleware\RequirePortalPermission::class.':lvs.followups.view')
        ->name('lvs.followups.resolve');
    Route::get('/lvs/instellingen', [\App\Http\Controllers\LvsSettingsController::class, 'index'])
        ->middleware(\App\Http\Middleware\RequirePortalPermission::class.':lvs.settings.manage')
        ->name('lvs.settings.index');
    Route::post('/lvs/instellingen', [\App\Http\Controllers\LvsSettingsController::class, 'save'])
        ->middleware(\App\Http\Middleware\RequirePortalPermission::class.':lvs.settings.manage')
        ->name('lvs.settings.save');
    Route::post('/lvs/instellingen/{rule}/status', [\App\Http\Controllers\LvsSettingsController::class, 'toggle'])
        ->whereNumber('rule')
        ->middleware(\App\Http\Middleware\RequirePortalPermission::class.':lvs.settings.manage')
        ->name('lvs.settings.toggle');
    Route::get('/lvs', [\App\Http\Controllers\LvsDashboardController::class, 'index'])
        ->middleware(\App\Http\Middleware\RequirePortalPermission::class.':lvs.dashboard.view')
        ->name('lvs.dashboard');
    Route::get('/lvs/klassenraad/export', [\App\Http\Controllers\LvsReportsController::class, 'export'])
        ->middleware(\App\Http\Middleware\RequirePortalPermission::class.':lvs.reports.view')
        ->name('lvs.reports.export');
    Route::get('/lvs/klassenraad', [\App\Http\Controllers\LvsReportsController::class, 'index'])
        ->middleware(\App\Http\Middleware\RequirePortalPermission::class.':lvs.reports.view')
        ->name('lvs.reports.index');

    Route::prefix('leerlingen')->name('leerlingen.')->middleware(
        \App\Http\Middleware\RequirePortalPermission::class.':lvs.view'
    )->group(function () {
        Route::get('/', [LeerlingenController::class, 'index'])->name('index');
        Route::get('/verjaardagen', [LeerlingenController::class, 'verjaardagen'])->name('verjaardagen');
        Route::get('/{id}', [LeerlingenController::class, 'show'])->whereNumber('id')->name('show');
    });

    Route::prefix('meldingen')->name('meldingen.')->group(function () {
        Route::get('/nieuw', [MeldingController::class, 'selectStudent'])
            ->middleware(\App\Http\Middleware\RequirePortalPermission::class.':lvs.meldingen.create')->name('select-student');
        Route::get('/', [MeldingController::class, 'index'])
            ->middleware(\App\Http\Middleware\RequirePortalPermission::class.':lvs.meldingen.view')->name('index');
        Route::get('/add/{id}', [MeldingController::class, 'create'])->whereNumber('id')
            ->middleware(\App\Http\Middleware\RequirePortalPermission::class.':lvs.meldingen.create')->name('create');
        Route::post('/add/{id}', [MeldingController::class, 'store'])->whereNumber('id')
            ->middleware(\App\Http\Middleware\RequirePortalPermission::class.':lvs.meldingen.create')->name('store');
    });
});

// Smartschool diagnostics previously exposed GET routes that could send messages
// or display user details without signing in. These unsafe routes are removed.
// Use the permission-protected Beheer > Smartschool-testbericht instead.

// Development-only helper: never allow a forged Smartschool identity on production.
Route::get('/fake-login', function (\Illuminate\Http\Request $request) {
    abort_unless(app()->environment('local') && in_array($request->ip(), ['127.0.0.1', '::1'], true), 404);

    $request->session()->put('ss_user', [
        'id'                => '143',
        'voornaam'          => 'Peter',
        'naam'              => 'Hons',
        'gebruikersnaam'    => 'peter.hons',
        'platform'          => 'atheneumsinttruiden.smartschool.be',
        'groupId'           => '2',
        'groupname'         => 'ICT-coördinator'
    ]);

    return redirect('/'); // of naar /leerlingen indien je wil
});