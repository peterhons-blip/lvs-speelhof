<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Nachtelijke checks
|--------------------------------------------------------------------------
*/

// Leerlingen die 18 worden (privacy + co-accounts)
Schedule::command('monitor:scheduled becoming_18')
    ->dailyAt('01:00')
    ->timezone('Europe/Brussels')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/leerlingen_check.log'));

// Verjaardagen (leerlingen + leerkrachten)
Schedule::command('monitor:scheduled birthdays')
    ->dailyAt('02:00')
    ->timezone('Europe/Brussels')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/verjaardagen_check.log'));

// Leerlingen syncen vanuit Smartschool
Schedule::command('monitor:scheduled student_sync')
    ->dailyAt('03:00')
    ->timezone('Europe/Brussels')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/smartschool_sync_leerlingen.log'));

 
// Bibliotheek: herinnering enkele dagen vooraf en op de uiterste inleverdatum.
// Verzending blijft standaard UIT tot de beheerder dit expliciet inschakelt.
Schedule::command('monitor:scheduled library_reminders')
    ->dailyAt('08:00')
    ->timezone('Europe/Brussels')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/bibliotheek_herinneringen.log'));

// Personeelssync: voorlopig alleen nachtelijke preview, tot de werking is gevalideerd.
// --apply pas na expliciete vrijgave activeren, anders worden geen users gewijzigd.
Schedule::command('smartschool:sync-personeel --schoolid=1')
    ->dailyAt('03:30')
    ->timezone('Europe/Brussels')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/smartschool_sync_personeel.log'));
