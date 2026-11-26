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
        $overdueEnabled = $notifier->setting('overdue_reminders_enabled', '0') === '1';
        $firstOverdueDays = max(1, (int) $notifier->setting('overdue_first_days', '3'));
        $repeatDays = max(1, (int) $notifier->setting('overdue_repeat_days', '7'));
        $escalationEnabled = $notifier->setting('escalation_enabled', '0') === '1';
        $escalateAfter = max(1, (int) $notifier->setting('escalation_after_reminders', '3'));
        $sent = 0;
        $planned = 0;

        $loans = DB::table('library_loans')->whereNull('returned_at')
            ->orderBy('id')->cursor();

        foreach ($loans as $loan) {
            $days = $loan->reminder_days_before !== null ? (int) $loan->reminder_days_before : $defaultDays;
            $onDue = $loan->remind_on_due_date !== null ? (bool) $loan->remind_on_due_date : $defaultDue;
            $daysLeft = \Illuminate\Support\Carbon::parse($today)->diffInDays(
                \Illuminate\Support\Carbon::parse($loan->due_date), false
            );
            $types = [];
            if ($days > 0 && (int) $daysLeft === $days) $types[] = 'before_due';
            if ($onDue && (int) $daysLeft === 0) $types[] = 'due_today';
            $daysLate = - (int) $daysLeft;
            if ($overdueEnabled && $daysLate >= $firstOverdueDays &&
                ($daysLate - $firstOverdueDays) % $repeatDays === 0) {
                $types[] = 'overdue_repeat';
            }

            // Escalation is based on confirmed sent reminders, not mere attempts.
            if ($escalationEnabled && $daysLate > 0 &&
                \Illuminate\Support\Facades\Schema::hasTable('library_escalations')) {
                $successful = DB::table('library_loan_notifications')
                    ->where('loan_id', $loan->id)
                    ->whereIn('type', ['before_due', 'due_today', 'overdue_repeat', 'manual'])
                    ->where('status', 'sent')->count();
                if ($successful >= $escalateAfter) {
                    if ($dryRun) {
                        $this->line("TEST: escalatie loan={$loan->id} na {$successful} herinneringen (geen bericht)");
                    } else {
                        app(\App\Services\LibraryEscalationNotifier::class)->notify((int) $loan->id);
                    }
                }
            }
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
