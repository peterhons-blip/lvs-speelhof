<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LeerlingenController;
use App\Http\Controllers\MeldingController;
use App\Http\Controllers\Auth\SmartschoolController;
use App\Http\Middleware\EnsureSmartschoolAuthenticated;
use App\Services\SmartschoolSoap;



// ===== Publiek / SSO =====
Route::get('/', [\App\Http\Controllers\DashboardController::class, 'index'])->name('home');   // <— publieke landing page

Route::get('/login', [SmartschoolController::class, 'redirect'])->name('login');
Route::get('/auth/smartschool/callback', [SmartschoolController::class, 'callback'])->name('smartschool.callback');
Route::get('/logout', [SmartschoolController::class, 'logout'])->name('logout');

// ===== Protected (na SSO) =====
Route::middleware([EnsureSmartschoolAuthenticated::class])->group(function () {
    // Bibliotheek: voorlopig alleen de afgeschermde startpagina.
    Route::view('/bibliotheek', 'bibliotheek.index')->name('bibliotheek.index')->middleware(\App\Http\Middleware\RequirePortalPermission::class.':bibliotheek.view');

    Route::prefix('bibliotheek/uitleningen')->name('bibliotheek.loans.')->group(function () {
        Route::get('/', [\App\Http\Controllers\LibraryLoanController::class, 'index'])->name('index')->middleware(\App\Http\Middleware\RequirePortalPermission::class.':bibliotheek.loans.view');
        Route::get('/nieuw', [\App\Http\Controllers\LibraryLoanController::class, 'create'])->name('create')->middleware(\App\Http\Middleware\RequirePortalPermission::class.':bibliotheek.loans.manage');
        Route::post('/nieuw', [\App\Http\Controllers\LibraryLoanController::class, 'store'])->name('store')->middleware(\App\Http\Middleware\RequirePortalPermission::class.':bibliotheek.loans.manage');
        Route::get('/leerling-scan', [\App\Http\Controllers\LibraryLoanController::class, 'lookupStudent'])->middleware(['throttle:60,1', \App\Http\Middleware\RequirePortalPermission::class.':bibliotheek.loans.manage'])->name('student-scan');
        Route::post('/leerling-kaart-koppelen', [\App\Http\Controllers\LibraryLoanController::class, 'linkStudentCard'])->middleware(['throttle:20,1', \App\Http\Middleware\RequirePortalPermission::class.':bibliotheek.loans.manage'])->name('student-card.link');
        Route::get('/exemplaar-scan', [\App\Http\Controllers\LibraryLoanController::class, 'lookupCopy'])->middleware(['throttle:90,1', \App\Http\Middleware\RequirePortalPermission::class.':bibliotheek.loans.manage'])->name('copy-scan');
        Route::post('/inleveren-met-barcode', [\App\Http\Controllers\LibraryLoanController::class, 'returnByBarcode'])->name('return-barcode')->middleware(\App\Http\Middleware\RequirePortalPermission::class.':bibliotheek.loans.manage');
        Route::get('/leerlingen-zoeken', [\App\Http\Controllers\LibraryLoanController::class, 'students'])->middleware('throttle:40,1')->name('students')->middleware(\App\Http\Middleware\RequirePortalPermission::class.':bibliotheek.loans.manage');
        Route::get('/instellingen', [\App\Http\Controllers\LibraryLoanController::class, 'settings'])->name('settings')->middleware(\App\Http\Middleware\RequirePortalPermission::class.':bibliotheek.settings.manage');
        Route::post('/instellingen', [\App\Http\Controllers\LibraryLoanController::class, 'saveSettings'])->name('settings.save')->middleware(\App\Http\Middleware\RequirePortalPermission::class.':bibliotheek.settings.manage');
        Route::get('/{loan}/herinneringen', [\App\Http\Controllers\LibraryLoanController::class, 'editReminders'])->whereNumber('loan')->name('reminders.edit')->middleware(\App\Http\Middleware\RequirePortalPermission::class.':bibliotheek.loans.manage');
        Route::put('/{loan}/herinneringen', [\App\Http\Controllers\LibraryLoanController::class, 'updateReminders'])->whereNumber('loan')->name('reminders.update')->middleware(\App\Http\Middleware\RequirePortalPermission::class.':bibliotheek.loans.manage');
        Route::post('/{loan}/inleveren', [\App\Http\Controllers\LibraryLoanController::class, 'returnLoan'])->whereNumber('loan')->name('return')->middleware(\App\Http\Middleware\RequirePortalPermission::class.':bibliotheek.loans.manage');
        Route::post('/{loan}/verlengen', [\App\Http\Controllers\LibraryLoanController::class, 'extend'])->whereNumber('loan')->name('extend')->middleware(\App\Http\Middleware\RequirePortalPermission::class.':bibliotheek.loans.manage');
    });

    Route::prefix('bibliotheek/boeken')->name('bibliotheek.books.')->group(function () {
        Route::get('/', [\App\Http\Controllers\LibraryBookController::class, 'index'])->name('index')->middleware(\App\Http\Middleware\RequirePortalPermission::class.':bibliotheek.books.view');
        Route::get('/nieuw', [\App\Http\Controllers\LibraryBookController::class, 'create'])->name('create')->middleware(\App\Http\Middleware\RequirePortalPermission::class.':bibliotheek.books.manage');
        Route::get('/isbn-opzoeken', [\App\Http\Controllers\LibraryBookController::class, 'lookup'])->middleware('throttle:30,1')->name('lookup')->middleware(\App\Http\Middleware\RequirePortalPermission::class.':bibliotheek.books.view');
        Route::get('/zoeken-op-naam', [\App\Http\Controllers\LibraryBookController::class, 'searchExternal'])->middleware('throttle:20,1')->name('search-external')->middleware(\App\Http\Middleware\RequirePortalPermission::class.':bibliotheek.books.view');
        Route::get('/edities', [\App\Http\Controllers\LibraryBookController::class, 'editions'])->middleware('throttle:30,1')->name('editions')->middleware(\App\Http\Middleware\RequirePortalPermission::class.':bibliotheek.books.view');
        Route::get('/werkbeschrijving', [\App\Http\Controllers\LibraryBookController::class, 'workDetails'])->middleware('throttle:30,1')->name('work-details')->middleware(\App\Http\Middleware\RequirePortalPermission::class.':bibliotheek.books.view');
        Route::get('/importhistoriek', [\App\Http\Controllers\LibraryImportController::class, 'history'])->name('import.history')->middleware(\App\Http\Middleware\RequirePortalPermission::class.':bibliotheek.books.view');
        Route::get('/importeren', [\App\Http\Controllers\LibraryImportController::class, 'create'])->name('import.create')->middleware(\App\Http\Middleware\RequirePortalPermission::class.':bibliotheek.books.view');
        Route::post('/importeren', [\App\Http\Controllers\LibraryImportController::class, 'store'])->name('import.store')->middleware(\App\Http\Middleware\RequirePortalPermission::class.':bibliotheek.import.manage');
        Route::get('/importeren/{batch}', [\App\Http\Controllers\LibraryImportController::class, 'show'])->whereNumber('batch')->name('import.show')->middleware(\App\Http\Middleware\RequirePortalPermission::class.':bibliotheek.books.view');
        Route::get('/importeren/{batch}/status', [\App\Http\Controllers\LibraryImportController::class, 'status'])->whereNumber('batch')->name('import.status')->middleware(\App\Http\Middleware\RequirePortalPermission::class.':bibliotheek.books.view');
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
        ->middleware([\App\Http\Middleware\RequirePortalPermission::class.':beheer.users.manage', 'throttle:45,1'])
        ->name('beheer.smartschool-test.recipients');

    // Handmatige Smartschool-testzending, uitsluitend na ICT-login en expliciete submit.
    Route::get('/beheer/smartschool-test', [\App\Http\Controllers\SmartschoolTestMessageController::class, 'create'])
        ->middleware(\App\Http\Middleware\RequirePortalPermission::class.':beheer.users.manage')
        ->name('beheer.smartschool-test.create');
    Route::post('/beheer/smartschool-test', [\App\Http\Controllers\SmartschoolTestMessageController::class, 'send'])
        ->middleware([\App\Http\Middleware\RequirePortalPermission::class.':beheer.users.manage', 'throttle:5,1'])
        ->name('beheer.smartschool-test.send');

    // Centraal portaalbeheer: rechten per rol en medewerker (ICT-coordinator heeft standaard alle rechten).
    Route::prefix('beheer')->name('beheer.')->group(function () {
        Route::get('/gebruikers', [\App\Http\Controllers\PortalPermissionsController::class, 'users'])->middleware(\App\Http\Middleware\RequirePortalPermission::class.':beheer.users.manage')->name('users.index');
        Route::get('/gebruikers-zoeken', [\App\Http\Controllers\PortalPermissionsController::class, 'searchUsers'])->middleware([\App\Http\Middleware\RequirePortalPermission::class.':beheer.users.manage', 'throttle:45,1'])->name('users.search');
        Route::post('/gebruikers', [\App\Http\Controllers\PortalPermissionsController::class, 'storeUser'])->middleware(\App\Http\Middleware\RequirePortalPermission::class.':beheer.users.manage')->name('users.store');
        Route::put('/gebruikers/{staff}', [\App\Http\Controllers\PortalPermissionsController::class, 'updateUser'])->whereNumber('staff')->middleware(\App\Http\Middleware\RequirePortalPermission::class.':beheer.users.manage')->name('users.update');
        Route::patch('/gebruikers/{staff}/aanmelden', [\App\Http\Controllers\PortalPermissionsController::class, 'updateLogin'])->whereNumber('staff')->middleware(\App\Http\Middleware\RequirePortalPermission::class.':beheer.users.manage')->name('users.login');
        Route::get('/rechten', [\App\Http\Controllers\PortalPermissionsController::class, 'index'])->middleware(\App\Http\Middleware\RequirePortalPermission::class.':beheer.permissions.manage')->name('permissions.index');
        Route::post('/rechten', [\App\Http\Controllers\PortalPermissionsController::class, 'update'])->middleware(\App\Http\Middleware\RequirePortalPermission::class.':beheer.permissions.manage')->name('permissions.update');
    });

    Route::prefix('leerlingen')->name('leerlingen.')->group(function () {
        Route::get('/', [LeerlingenController::class, 'index'])->name('index');
        Route::get('/verjaardagen', [LeerlingenController::class, 'verjaardagen'])->name('verjaardagen');
        Route::get('/{id}', [LeerlingenController::class, 'show'])->whereNumber('id')->name('show');
    });

    Route::prefix('meldingen')->name('meldingen.')->group(function () {
        Route::get('/add/{id}', [MeldingController::class, 'create'])->whereNumber('id')->name('create');
        Route::post('/add/{id}', [MeldingController::class, 'store'])->whereNumber('id')->name('store');
    });
});

// ===== Test route voor Smartschool bericht =====
Route::get('/smartschool/test-bericht', function (SmartschoolSoap $smartschool) {
    $userIdentifier = env('SMARTSCHOOL_TEST_USER');

    if (!$userIdentifier) {
        return 'SMARTSCHOOL_TEST_USER niet ingesteld in .env';
    }

    // We gebruiken bewust dezelfde gebruiker als ontvanger én verzender
    $senderIdentifier = env('SMARTSCHOOL_TEST_SENDER', $userIdentifier);

    $title = 'Testbericht vanuit LVS (met verzender)';
    $body  = "Dit is een testbericht via Webservices V3.\n"
           . "Ontvanger en verzender zijn dezelfde gebruiker.\n"
           . "Verzonden op: " . now()->toDateTimeString();

    try {
        $result = $smartschool->sendMessage(
            $userIdentifier,      // ontvanger
            $title,
            $body,
            $senderIdentifier,    // verzender = bestaande gebruiker
            false                 // copyToLVS
        );

        return 'Testbericht verstuurd. Antwoord van Smartschool: <pre>' . print_r($result, true) . '</pre>';
    } catch (\SoapFault $e) {
        return 'Fout bij versturen naar Smartschool: ' . $e->getMessage();
    }
})->name('smartschool.test-bericht');

// ===== Test route voor Smartschool error codes =====
Route::get('/smartschool/errorcodes', function (SmartschoolSoap $smartschool) {
    $codes = $smartschool->getErrorCodes();

    echo '<pre>';
    print_r($codes);
    echo '</pre>';
});

// ===== Test route voor Smartschool user details =====
Route::get('/smartschool/test-user', function (SmartschoolSoap $smartschool) {
    $id = env('SMARTSCHOOL_TEST_USER');

    try {
        $res = $smartschool->getUserDetails($id);
        return '<pre>' . print_r($res, true) . '</pre>';
    } catch (\SoapFault $e) {
        return 'Fout bij getUserDetails: ' . $e->getMessage();
    }
});

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