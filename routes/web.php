<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LeerlingenController;
use App\Http\Controllers\MeldingController;
use App\Http\Controllers\Auth\SmartschoolController;
use App\Http\Middleware\EnsureSmartschoolAuthenticated;
use App\Services\SmartschoolSoap;



// ===== Publiek / SSO =====
Route::view('/', 'home')->name('home');   // <— publieke landing page

Route::get('/login', [SmartschoolController::class, 'redirect'])->name('login');
Route::get('/auth/smartschool/callback', [SmartschoolController::class, 'callback'])->name('smartschool.callback');
Route::get('/logout', [SmartschoolController::class, 'logout'])->name('logout');

// ===== Protected (na SSO) =====
Route::middleware([EnsureSmartschoolAuthenticated::class])->group(function () {
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

Route::get('/fake-login', function (\Illuminate\Http\Request $request) {

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