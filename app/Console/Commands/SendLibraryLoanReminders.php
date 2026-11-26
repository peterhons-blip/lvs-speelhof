<?php

namespace App\Console\Commands;

use App\Services\LibraryLoanNotifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SendLibraryLoanReminders extends Command
{
    protected $signature = 'bibliotheek:herinneringen {--test : Toon geplande herinneringen zonder Smartschool-berichten te versturen}';
    protected $description = 'Verstuur unieke Smartschool-herinneringen voor boeken met naderende, huidige of verstreken inleverdatum';

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
        if (!Schema::hasTable('school_calendar_closures')) {
            $this->warn('Schoolkalender ontbreekt; herinneringen uit veiligheid overgeslagen.');
            return self::SUCCESS;
        }
        $calendar = app(\App\Services\LibraryCalendar::class);
        $todayDate = \Carbon\CarbonImmutable::parse($today,'Europe/Brussels');
        if ($reason = $calendar->closed($todayDate)) {
            $this->info('Geen bibliotheekherinneringen verstuurd: '.$reason.'.');
            return self::SUCCESS;
        }
        $defaultDays = max(0, (int) $notifier->setting('reminder_days_before', '3'));
        $defaultDue = $notifier->setting('remind_on_due_date', '1') === '1';
        $firstOverdue = max(0, min(365, (int) $notifier->setting('overdue_first_days', '0')));
        $repeatEvery = max(0, min(365, (int) $notifier->setting('overdue_repeat_days', '0')));
        $escalateAfter = max(0, min(100, (int) $notifier->setting('overdue_escalate_after', '0')));
        $sent = 0;
        $planned = 0;

        $loans = DB::table('library_loans')->whereNull('returned_at')
            ->orderBy('id')->cursor();

        foreach ($loans as $loan) {
            $days = $loan->reminder_days_before !== null ? (int) $loan->reminder_days_before : $defaultDays;
            $onDue = $loan->remind_on_due_date !== null ? (bool) $loan->remind_on_due_date : $defaultDue;
            $due = \Carbon\CarbonImmutable::parse($loan->due_date,'Europe/Brussels');
            $types = [];
            if ($days > 0 && $todayDate->equalTo($calendar->reminderDate($due,$days)) && $todayDate->greaterThanOrEqualTo(\Carbon\CarbonImmutable::parse($loan->loan_date,'Europe/Brussels'))) $types[] = 'before_due';
            if ($onDue && $todayDate->equalTo($due)) $types[] = 'due_today';
            $overdueDays = $calendar->distance($due,$todayDate);
            if ($firstOverdue > 0 && $todayDate->greaterThan($due) && $overdueDays >= $firstOverdue) {
                $last = DB::table('library_loan_notifications')->where('loan_id',$loan->id)
                    ->where('type','overdue')->where('status','sent')
                    ->where('scheduled_for','>',$due->toDateString())->max('scheduled_for');
                if (!$last || ($repeatEvery > 0 && $calendar->distance(\Carbon\CarbonImmutable::parse($last,'Europe/Brussels'),$todayDate) >= $repeatEvery)) {
                    $types[] = 'overdue';
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
            if ($escalateAfter > 0 && $overdueDays > 0 &&
                \Illuminate\Support\Facades\Schema::hasTable('library_loan_escalations')) {
                $successfulOverdue = DB::table('library_loan_notifications')
                    ->where('loan_id', $loan->id)
                    ->where('type', 'overdue')->where('status', 'sent')->where('scheduled_for','>',$due->toDateString())->count();
                $queued = DB::table('library_loan_escalations')
                    ->where('loan_id', $loan->id)->exists();
                if ($successfulOverdue >= $escalateAfter && !$queued) {
                    if ($dryRun) {
                        $this->line("TEST: loan={$loan->id} escalatie bij {$successfulOverdue} verzonden herinneringen");
                    } else {
                        DB::table('library_loan_escalations')->insertOrIgnore([
                            'loan_id' => $loan->id, 'reminder_count' => $successfulOverdue,
                            'status' => 'open', 'created_at' => now(), 'updated_at' => now(),
                        ]);
                    }
                }
                if (! $dryRun) app(\App\Services\LibraryEscalationNotifier::class)->notify((int) $loan->id);
            }
        }

        $this->info($dryRun ? "TEST klaar: {$planned} herinneringen gevonden, 0 verzonden." :
            "Klaar: {$sent} van {$planned} herinneringen verzonden.");
        return self::SUCCESS;
    }
}
