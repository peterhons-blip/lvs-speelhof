<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LibraryLoanNotifier
{
    public function enabled(): bool
    {
        return DB::table('library_settings')->where('key', 'smartschool_enabled')->value('value') === '1';
    }

    public function setting(string $key, string $default): string
    {
        return (string) (DB::table('library_settings')->where('key', $key)->value('value') ?? $default);
    }

    /**
     * Send at most once per loan, notification type and scheduled date.
     * Failed/uncertain delivery is logged but never retried automatically to avoid duplicate messages.
     */
    public function send(int $loanId, string $type, string $scheduledFor): bool
    {
        if (! $this->enabled()) return false;
        if (! in_array($type, ['confirmation', 'before_due', 'due_today', 'manual', 'returned'], true)) return false;

        $loan = DB::table('library_loans as loans')
            ->join('library_copies as copies', 'copies.id', '=', 'loans.copy_id')
            ->join('library_books as books', 'books.id', '=', 'copies.book_id')
            ->leftJoin('leerlingen as leerlingen', 'leerlingen.id', '=', 'loans.leerling_id')
            ->leftJoin('users as staff', 'staff.id', '=', 'loans.staff_user_id')
            ->select('loans.*', 'books.title', 'copies.barcode',
                'leerlingen.gebruikersnaam', 'leerlingen.voornaam', 'leerlingen.naam',
                'leerlingen.active', 'leerlingen.schoolid',
                'staff.smartschool_gebruikersnaam as staff_username',
                'staff.voornaam as staff_voornaam', 'staff.name as staff_naam',
                'staff.schoolid as staff_schoolid', 'staff.smartschool_active as staff_active')
            ->where('loans.id', $loanId)->first();

        if (! $loan) return false;
        $isStaff = $loan->staff_user_id !== null;
        $recipient = $isStaff ? (string) ($loan->staff_username ?? '') : (string) ($loan->gebruikersnaam ?? '');
        $firstName = $isStaff ? (string) ($loan->staff_voornaam ?? '') : (string) ($loan->voornaam ?? '');
        $schoolId = $isStaff ? $loan->staff_schoolid : $loan->schoolid;
        if ($recipient === '' || ! $schoolId) return false;
        if ($isStaff) {
            if ($loan->staff_active !== null && (int) $loan->staff_active === 0) return false;
        } elseif ((int) $loan->active !== 1) {
            return false;
        }
        if (in_array($type, ['before_due', 'due_today', 'manual'], true) && $loan->returned_at !== null) return false;
        if (in_array($type, ['before_due', 'due_today'], true) && $loan->due_date < $scheduledFor) return false;
        if ($type === 'returned' && $loan->returned_at === null) return false;

        $inserted = DB::table('library_loan_notifications')->insertOrIgnore([
            'loan_id' => $loanId, 'type' => $type, 'scheduled_for' => $scheduledFor,
            'status' => 'processing', 'created_at' => now(), 'updated_at' => now(),
        ]);

        if ($inserted !== 1) return false;

        $date = \Illuminate\Support\Carbon::parse($loan->due_date)->format('d/m/Y');
        $subject = match ($type) {
            'confirmation' => 'Bibliotheek: bevestiging uitlening',
            'before_due' => 'Bibliotheek: herinnering inleverdatum',
            'due_today' => 'Bibliotheek: vandaag boek inleveren',
            'manual' => 'Bibliotheek: herinnering boek terugbrengen',
            'returned' => 'Bibliotheek: bevestiging inlevering',
        };

        $intro = match ($type) {
            'confirmation' => 'Je hebt een boek uitgeleend in de schoolbibliotheek.',
            'before_due' => 'Je uitleentermijn loopt binnenkort af.',
            'due_today' => 'Vandaag is de uiterste inleverdatum van je boek.',
            'manual' => 'Dit is een herinnering om je boek tijdig terug te brengen.',
            'returned' => 'Bedankt! Je hebt dit boek teruggebracht naar de schoolbibliotheek.',
        };

        $message = '<p>Beste '.e($firstName).',</p>'
            .'<p>'.e($intro).'</p>'
            .'<p><strong>Boek:</strong> '.e($loan->title).'<br>'
            .'<strong>Barcode:</strong> '.e($loan->barcode).'<br>'
            .'<strong>Uiterste inleverdatum:</strong> '.e($date).'</p>'
            .($type === 'returned'
                ? '<p>Deze uitlening is afgesloten. Heb je vragen? Contacteer het bibliotheekteam.</p>'
                : '<p>Breng het boek tijdig terug naar de schoolbibliotheek. Heb je vragen? Contacteer het bibliotheekteam.</p>')
            .'<p>Met vriendelijke groeten<br>De schoolbibliotheek</p>';

        try {
            $sender = trim($this->setting('smartschool_sender', 'bib'));
            if ($sender === '') $sender = 'bib';

            // School-specific SOAP endpoint when configured. No co-account messages.
            $school = DB::table('scholen')->where('id', $schoolId)->first();
            if (! $school || empty($school->smartschool_wsdl) || empty($school->smartschool_accesscode)) {
                throw new \RuntimeException('Smartschool-webservices ontbreken voor de school van deze ontlener.');
            }
            $ss = new SmartschoolSoap($school->smartschool_wsdl, $school->smartschool_accesscode);
            $ss->sendMessage($recipient, $subject, $message, $sender, false, 0);

            DB::table('library_loan_notifications')
                ->where('loan_id', $loanId)->where('type', $type)->where('scheduled_for', $scheduledFor)
                ->update(['status' => 'sent', 'sent_at' => now(), 'updated_at' => now()]);
            return true;
        } catch (\Throwable $error) {
            DB::table('library_loan_notifications')
                ->where('loan_id', $loanId)->where('type', $type)->where('scheduled_for', $scheduledFor)
                ->update([
                    'status' => 'failed',
                    'error_message' => mb_substr($error->getMessage(), 0, 1500),
                    'updated_at' => now(),
                ]);
            Log::error('Bibliotheek Smartschool-bericht mislukt', [
                'loan_id' => $loanId, 'type' => $type, 'error' => $error->getMessage(),
            ]);
            return false;
        }
    }
}
