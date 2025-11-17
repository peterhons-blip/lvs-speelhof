<?php

use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Log;

// elke nacht om 01:00
Schedule::command('leerlingen:check-18')
    ->dailyAt('01:00')
    //->everyMinute()
    ->timezone('Europe/Brussels')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/leerlingen_check.log'));
