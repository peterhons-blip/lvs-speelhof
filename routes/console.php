<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Nachtelijke checks
|--------------------------------------------------------------------------
*/

// Leerlingen die 18 worden (privacy + co-accounts)
Schedule::command('leerlingen:check-18')
    ->dailyAt('01:00')
    ->timezone('Europe/Brussels')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/leerlingen_check.log'));

// Verjaardagen (leerlingen + leerkrachten)
Schedule::command('verjaardagen:check')
    ->dailyAt('02:00')
    ->timezone('Europe/Brussels')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/verjaardagen_check.log'));


// Leerlingen syncen vanuit Smartschool
//Schedule::command('smartschool:sync-leerlingen --school=1')
 //   ->dailyAt('03:00')
 //   ->timezone('Europe/Brussels')
  //  ->withoutOverlapping()
  //  ->appendOutputTo(storage_path('logs/smartschool_sync_leerlingen.log'));
