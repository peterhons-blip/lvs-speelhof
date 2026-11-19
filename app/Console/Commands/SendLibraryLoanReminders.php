<?php

namespace App\Console\Commands;

use App\Services\LibraryLoanNotifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SendLibraryLoanReminders extends Command
{
    protected $signature = 'bibliotheek:herinneringen {--test : Toon geplande herinneringen zonder Smartschool-berichten te versturen}';
    protected $description = 'Verstuur unieke Smartschool-herinneringen voor boeken met naderende of huidige inleverdatum';

    public function handle(LibraryLoanNotifier $notifier): int
    {
        if (! Schema::hasTable('library_loans') || ! Schema::hasTable('library_settings')) {
            $this->warn('Bibliotheek-uitleentabellen ontbreken. Voer eerst de gerichte migratie uit.');
            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('test');
        if (! $dryRun && ! $notifier->enabled()) {
            $this->info('Smartschool-herinneringen staan uit in de bibliotheekinstellingen.');
            return self::SUCCESS;
        }

        $today = now('Europe/Brussels')->toDateString();
        $defaultDays = max(0, (int) $notifier->setting('reminder_days_before', '3'));
        $defaultDue = $notifier->setting('remind_on_due_date', '1') === '1';
        $sent = 0;
        $planned = 0;

        $loans = DB::table('library_loans')->whereNull('returned_at')
            ->where('due_date', '>=', $today)->orderBy('id')->cursor();

        foreach ($loans as $loan) {
            $days = $loan->reminder_days_before !== null ? (int) $loan->reminder_days_before : $defaultDays;
            $onDue = $loan->remind_on_due_date !== null ? (bool) $loan->remind_on_due_date : $defaultDue;
            $daysLeft = \Illuminate\Support\Carbon::parse($today)->diffInDays(
                \Illuminate\Support\Carbon::parse($loan->due_date), false
            );
            $types = [];
            if ($days > 0 && (int) $daysLeft === $days) $types[] = 'before_due';
            if ($onDue && (int) $daysLeft === 0) $types[] = 'due_today';

            foreach ($types as $type) {
                $exists = DB::table('library_loan_notifications')
                    ->where('loan_id', $loan->id)->where('type', $type)
                    ->where('scheduled_for', $today)->exists();
                if ($exists) continue;
                $planned++;
                if ($dryRun) {
                    $this->line("TEST: loan={$loan->id} type={$type} vervalt={$loan->due_date}");
                } elseif ($notifier->send((int) $loan->id, $type, $today)) {
                    $sent++;
                }
            }
        }

        $this->info($dryRun ? "TEST klaar: {$planned} herinneringen gevonden, 0 verzonden." :
            "Klaar: {$sent} van {$planned} herinneringen verzonden.");
        return self::SUCCESS;
    }
}
