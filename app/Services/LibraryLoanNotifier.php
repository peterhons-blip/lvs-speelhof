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
        if (! in_array($type, ['confirmation', 'before_due', 'due_today'], true)) return false;

        $loan = DB::table('library_loans as loans')
            ->join('library_copies as copies', 'copies.id', '=', 'loans.copy_id')
            ->join('library_books as books', 'books.id', '=', 'copies.book_id')
            ->join('leerlingen as leerlingen', 'leerlingen.id', '=', 'loans.leerling_id')
            ->select('loans.*', 'books.title', 'copies.barcode',
                'leerlingen.gebruikersnaam', 'leerlingen.voornaam', 'leerlingen.naam',
                'leerlingen.active', 'leerlingen.schoolid')
            ->where('loans.id', $loanId)->first();

        if (! $loan || ! $loan->gebruikersnaam || (int) $loan->active !== 1) return false;
        if ($type !== 'confirmation' && $loan->returned_at !== null) return false;
        if ($type !== 'confirmation' && $loan->due_date < $scheduledFor) return false;

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
        };

        $intro = match ($type) {
            'confirmation' => 'Je hebt een boek uitgeleend in de schoolbibliotheek.',
            'before_due' => 'Je uitleentermijn loopt binnenkort af.',
            'due_today' => 'Vandaag is de uiterste inleverdatum van je boek.',
        };

        $message = '<p>Beste '.e($loan->voornaam).',</p>'
            .'<p>'.e($intro).'</p>'
            .'<p><strong>Boek:</strong> '.e($loan->title).'<br>'
            .'<strong>Barcode:</strong> '.e($loan->barcode).'<br>'
            .'<strong>Uiterste inleverdatum:</strong> '.e($date).'</p>'
            .'<p>Breng het boek tijdig terug naar de schoolbibliotheek. Heb je vragen? Contacteer het bibliotheekteam.</p>'
            .'<p>Met vriendelijke groeten<br>De schoolbibliotheek</p>';

        try {
            $sender = trim($this->setting('smartschool_sender', 'bib'));
            if ($sender === '') $sender = 'bib';

            // School-specific SOAP endpoint when configured. No co-account messages.
            $school = DB::table('scholen')->where('id', $loan->schoolid)->first();
            if ($school && ! empty($school->smartschool_wsdl) && ! empty($school->smartschool_accesscode)) {
                $ss = new SmartschoolSoap($school->smartschool_wsdl, $school->smartschool_accesscode);
            } else {
                $ss = app(SmartschoolSoap::class);
            }
            $ss->sendMessage($loan->gebruikersnaam, $subject, $message, $sender, false, 0);

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
