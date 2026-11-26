<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class LibraryEscalationNotifier
{
    public function notify(int $loanId): bool
    {
        $notifier = app(LibraryLoanNotifier::class);
        if (! $notifier->enabled() || $notifier->setting('policy_alert_enabled', '0') !== '1'
            || ! Schema::hasTable('library_loan_escalations')) return false;

        $recipient = trim($notifier->setting('policy_alert_recipient', ''));
        if ($recipient === '') return false;

        $loan = DB::table('library_loan_escalations as e')
            ->join('library_loans as l', 'l.id', '=', 'e.loan_id')
            ->join('library_copies as c', 'c.id', '=', 'l.copy_id')
            ->join('library_books as b', 'b.id', '=', 'c.book_id')
            ->leftJoin('leerlingen as student', 'student.id', '=', 'l.leerling_id')
            ->leftJoin('users as staff', 'staff.id', '=', 'l.staff_user_id')
            ->where('e.loan_id', $loanId)->where('e.status', 'open')
            ->whereNull('l.returned_at')->where('l.due_date', '<', now('Europe/Brussels')->toDateString())
            ->select('e.created_at as escalated_at', 'e.reminder_count', 'l.due_date', 'l.staff_user_id',
                'b.title', 'c.barcode', 'student.schoolid as student_school', 'staff.schoolid as staff_school',
                'student.voornaam as student_first', 'student.naam as student_last',
                'staff.voornaam as staff_first', 'staff.name as staff_last')->first();
        if (! $loan) return false;

        $schoolId = $loan->staff_user_id ? $loan->staff_school : $loan->student_school;
        if (! $schoolId || ! DB::table('users')->where('schoolid', $schoolId)
            ->where('smartschool_gebruikersnaam', $recipient)->where('smartschool_active', 1)->exists()) return false;
        $school = DB::table('scholen')->where('id', $schoolId)->first();
        if (! $school || empty($school->smartschool_wsdl) || empty($school->smartschool_accesscode)) return false;

        // Reuse the existing unique notification key with the fixed escalation date.
        // No second escalation table and no automatic retry after an uncertain delivery.
        $date = \Illuminate\Support\Carbon::parse($loan->escalated_at)->toDateString();
        $key = ['loan_id' => $loanId, 'type' => 'policy_escalation', 'scheduled_for' => $date];
        if (! DB::table('library_loan_notifications')->insertOrIgnore($key + [
            'status' => 'processing', 'created_at' => now(), 'updated_at' => now(),
        ])) return false;

        $first = $loan->staff_user_id ? $loan->staff_first : $loan->student_first;
        $last = $loan->staff_user_id ? $loan->staff_last : $loan->student_last;
        $body = nl2br(e("Beste collega,\n\nNa {$loan->reminder_count} verzonden te-laat-herinneringen staat deze uitlening nog open.\n\n"
            ."Ontlener: {$first} {$last}\nBoek: {$loan->title}\nExemplaarcode: {$loan->barcode}\n"
            ."Inleverdatum: {$loan->due_date}\n\nGelieve dit op te volgen via de bibliotheekmodule.\n\nDe schoolbibliotheek"));
        try {
            $ss = new SmartschoolSoap($school->smartschool_wsdl, $school->smartschool_accesscode);
            $ss->sendMessage($recipient, 'Bibliotheek: opvolging niet-ingeleverd boek', $body, 'bib', false, 0);
            DB::table('library_loan_notifications')->where($key)->update([
                'status' => 'sent', 'sent_at' => now(), 'updated_at' => now(),
            ]);
            return true;
        } catch (\Throwable $error) {
            DB::table('library_loan_notifications')->where($key)->update([
                'status' => 'failed', 'error_message' => mb_substr($error->getMessage(), 0, 1500), 'updated_at' => now(),
            ]);
            Log::error('Bibliotheek beleidssignaal mislukt', ['loan_id' => $loanId, 'error_type' => get_class($error)]);
            return false;
        }
    }
}
