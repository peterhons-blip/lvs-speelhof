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
        if (! in_array($type, ['confirmation', 'before_due', 'due_today', 'manual', 'returned', 'extended', 'overdue'], true)) return false;

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
        if (in_array($type, ['before_due', 'due_today', 'manual', 'extended', 'overdue'], true) && $loan->returned_at !== null) return false;
        if ($type === 'overdue' && $loan->due_date >= $scheduledFor) return false;
        if (in_array($type, ['before_due', 'due_today'], true) && $loan->due_date < $scheduledFor) return false;
        if ($type === 'returned' && $loan->returned_at === null) return false;

        $inserted = DB::table('library_loan_notifications')->insertOrIgnore([
            'loan_id' => $loanId, 'type' => $type, 'scheduled_for' => $scheduledFor,
            'status' => 'processing', 'created_at' => now(), 'updated_at' => now(),
        ]);

        if ($inserted !== 1) return false;

        $defaults = self::defaultTemplates();
        $template = $defaults[$type];
        if (\Illuminate\Support\Facades\Schema::hasTable('library_message_templates')) {
            $custom = DB::table('library_message_templates')->where('type', $type)->first();
            if ($custom) $template = ['subject' => $custom->subject, 'body' => $custom->body];
        }

        $date = \Illuminate\Support\Carbon::parse($loan->due_date)->format('d/m/Y');
        $replacements = [
            '{voornaam}' => $firstName,
            '{boek}' => (string) $loan->title,
            '{exemplaarcode}' => (string) $loan->barcode,
            '{inleverdatum}' => $date,
        ];
        $subject = strtr($template['subject'], $replacements);
        // Plain-text templates, never trust content as HTML. Convert newlines after escaping.
        $message = nl2br(e(strtr($template['body'], $replacements)));


        try {
            // Always send library messages under the dedicated Smartschool 'bib' account.
            $sender = 'bib';

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
    public static function defaultTemplates(): array
    {
        $base = "Beste {voornaam},\n\n";
        $details = "Boek: {boek}\nExemplaarcode: {exemplaarcode}\nUiterste inleverdatum: {inleverdatum}\n\n";
        $footer = "Met vriendelijke groeten\nDe schoolbibliotheek";
        return [
            'confirmation' => [
                'label' => 'Bij uitlening',
                'subject' => 'Bibliotheek: bevestiging uitlening',
                'body' => $base."Je hebt een boek uitgeleend in de schoolbibliotheek.\n\n".$details.
                    "Breng het boek tijdig terug naar de schoolbibliotheek.\n\n".$footer,
            ],
            'before_due' => [
                'label' => 'Herinnering vooraf',
                'subject' => 'Bibliotheek: herinnering inleverdatum',
                'body' => $base."Je uitleentermijn loopt binnenkort af.\n\n".$details.
                    "Breng het boek tijdig terug naar de schoolbibliotheek.\n\n".$footer,
            ],
            'due_today' => [
                'label' => 'Op de inleverdatum',
                'subject' => 'Bibliotheek: vandaag boek inleveren',
                'body' => $base."Vandaag is de uiterste inleverdatum van je boek.\n\n".$details.
                    "Breng het boek terug naar de schoolbibliotheek.\n\n".$footer,
            ],
            'manual' => [
                'label' => 'Handmatige herinnering',
                'subject' => 'Bibliotheek: herinnering boek terugbrengen',
                'body' => $base."Dit is een herinnering om je boek terug te brengen.\n\n".$details.$footer,
            ],
            'extended' => [
                'label' => 'Bij verlenging',
                'subject' => 'Bibliotheek: uitleentermijn verlengd',
                'body' => $base."Je uitleentermijn is verlengd.\n\n".$details.
                    "De nieuwe inleverdatum staat hierboven. Breng het boek uiterlijk op die datum terug.\n\n".$footer,
            ],
            'overdue' => [
                'label' => 'Te laat ingeleverd (herhaald)',
                'subject' => 'Bibliotheek: boek nog niet ingeleverd',
                'body' => $base."De inleverdatum van je boek is voorbij.\n\n".$details.
                    "Breng het boek zo snel mogelijk terug naar de schoolbibliotheek.\n\n".$footer,
            ],
            'returned' => [
                'label' => 'Bij terugbrengen',
                'subject' => 'Bibliotheek: bevestiging inlevering',
                'body' => $base."Bedankt! Je hebt dit boek teruggebracht naar de schoolbibliotheek.\n\n".
                    "Boek: {boek}\nExemplaarcode: {exemplaarcode}\n\n".$footer,
            ],
        ];
    }

}
