<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/** Alert the configured policy Smartschool account at most once per loan. */
class LibraryEscalationNotifier
{
    public function notify(int $loanId): bool
    {
        if (! Schema::hasTable('library_escalations')) return false;
        $notifier = app(LibraryLoanNotifier::class);
        if (! $notifier->enabled() ||
            $notifier->setting('escalation_enabled', '0') !== '1') return false;

        $recipient = trim($notifier->setting('escalation_recipient', ''));
        if ($recipient === '') return false;

        $loan = DB::table('library_loans as l')
            ->join('library_copies as c', 'c.id', '=', 'l.copy_id')
            ->join('library_books as b', 'b.id', '=', 'c.book_id')
            ->leftJoin('leerlingen as student', 'student.id', '=', 'l.leerling_id')
            ->leftJoin('users as staff', 'staff.id', '=', 'l.staff_user_id')
            ->select('l.*', 'b.title', 'c.barcode',
                'student.voornaam as student_first', 'student.naam as student_last',
                'student.schoolid as student_school',
                'staff.voornaam as staff_first', 'staff.name as staff_last',
                'staff.schoolid as staff_school')
            ->where('l.id', $loanId)->whereNull('l.returned_at')->first();
        if (! $loan || $loan->due_date >= now('Europe/Brussels')->toDateString()) return false;
        $schoolId = $loan->staff_user_id ? $loan->staff_school : $loan->student_school;
        $school = DB::table('scholen')->where('id', $schoolId)->first();
        if (! $school || !$school->smartschool_wsdl || !$school->smartschool_accesscode) {
            Log::warning('Bibliotheekbeleid: geen Smartschool-configuratie', ['loan_id' => $loanId]);
            return false;
        }
        $confirmed = DB::table('library_loan_notifications')->where('loan_id', $loanId)
            ->where('status', 'sent')->whereIn('type', [
                'before_due', 'due_today', 'overdue_repeat', 'manual',
            ])->count();
        $minimum = max(1, (int) $notifier->setting('escalation_after_reminders', '3'));
        if ($confirmed < $minimum) return false;

        $claimed = DB::table('library_escalations')->insertOrIgnore([
            'loan_id' => $loanId, 'recipient' => $recipient,
            'status' => 'processing', 'created_at' => now(), 'updated_at' => now(),
        ]);
        if (!$claimed) return false;
        $first = (string) ($loan->staff_user_id ? $loan->staff_first : $loan->student_first);
        $last = (string) ($loan->staff_user_id ? $loan->staff_last : $loan->student_last);
        $subject = 'Bibliotheek: opvolging niet-ingeleverd boek';
        $body = nl2br(e("Beste collega,\n\n"
            ."Er is een uitlening die na {$confirmed} verzonden herinneringen nog openstaat.\n\n"
            ."Ontlener: {$first} {$last}\n"
            ."Boek: {$loan->title}\n"
            ."Exemplaarcode: {$loan->barcode}\n"
            ."Inleverdatum: {$loan->due_date}\n\n"
            ."Gelieve dit op te volgen via de bibliotheekmodule.\n\n"
            ."Met vriendelijke groeten\nDe schoolbibliotheek"));
        try {
            $ss = new SmartschoolSoap($school->smartschool_wsdl, $school->smartschool_accesscode);
            $ss->sendMessage($recipient, $subject, $body, 'bib', false, 0);
            DB::table('library_escalations')->where('loan_id', $loanId)->update([
                'status' => 'sent', 'updated_at' => now(),
            ]);
            return true;
        } catch (\Throwable $e) {
            DB::table('library_escalations')->where('loan_id', $loanId)->update([
                'status' => 'failed',
                'error_message' => mb_substr($e->getMessage(), 0, 1500),
                'updated_at' => now(),
            ]);
            Log::error('Bibliotheekbeleid escalatie mislukt', [
                'loan_id' => $loanId, 'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
