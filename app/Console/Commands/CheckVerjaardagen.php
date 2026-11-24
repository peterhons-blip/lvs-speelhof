<?php

namespace App\Console\Commands;

use App\Models\Leerling;
use App\Models\User;
use App\Services\BirthdaySettings;
use App\Services\SmartschoolSoap;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

class CheckVerjaardagen extends Command
{
    protected $signature = 'verjaardagen:check
        {--test : Alleen tonen, niets versturen}
        {--date= : Datum YYYY-MM-DD voor veilige preview}
        {--only= : leerlingen|users}
        {--to= : Ontvanger voor testpreview, vereist --test}
        {--dummy : Toon voorbeeldpersonen, vereist --test}';

    protected $description = 'Verjaardagsberichten aan leerlingen en personeel volgens beheerinstellingen.';

    public function handle(BirthdaySettings $settingsService): int
    {
        $test = (bool) $this->option('test');
        $dummy = (bool) $this->option('dummy');
        $override = trim((string) $this->option('to'));
        $dateOption = (string) ($this->option('date') ?? '');
        $only = (string) ($this->option('only') ?? '');

        if (! in_array($only, ['', 'leerlingen', 'users'], true)) {
            $this->error('Gebruik --only=leerlingen of --only=users.');
            return self::FAILURE;
        }
        if (! $test && ($dummy || $override !== '' || $dateOption !== '')) {
            $this->error('--dummy, --to en --date kunnen alleen met --test gebruikt worden.');
            return self::FAILURE;
        }
        try {
            $today = $dateOption !== '' ? Carbon::createFromFormat('!Y-m-d', $dateOption, 'Europe/Brussels') : now('Europe/Brussels')->startOfDay();
            if (! $today || ($dateOption !== '' && $today->format('Y-m-d') !== $dateOption)) throw new \InvalidArgumentException('Ongeldige datum');
        } catch (\Throwable $error) {
            $this->error('Ongeldige datum (gebruik YYYY-MM-DD).');
            return self::FAILURE;
        }

        $settings = $settingsService->all();
        if ($settings['enabled'] !== '1') {
            $this->info('Verjaardagsberichten zijn uitgeschakeld in het beheer.');
            return self::SUCCESS;
        }
        if (! $test && (! Schema::hasTable('birthday_deliveries') || ! Schema::hasTable('birthday_settings'))) {
            $this->error('Voer eerst de verjaardagsmigratie uit: verzenden wordt uit veiligheid overgeslagen.');
            return self::FAILURE;
        }
        $date = $today->toDateString();
        $schoolFilter = (int) $settings['school_id'];

        if ($dummy) {
            $this->info('TEST dummy: leerling en medewerker; er wordt niets verstuurd.');
            return self::SUCCESS;
        }

        if (($only === '' || $only === 'leerlingen') && $settings['students_enabled'] === '1') {
            $query = Leerling::query()->where('active', 1)
                ->whereMonth('geboortedatum', $today->month)->whereDay('geboortedatum', $today->day);
            if ($schoolFilter > 0) $query->where('schoolid', $schoolFilter);
            foreach ($query->cursor() as $student) {
                $student->load('school');
                $this->deliver($settingsService, $settings, 'leerling', (int) $student->id,
                    (int) ($student->schoolid ?? 0), $date, $today,
                    $student->geboortedatum, (string) $student->voornaam, (string) $student->naam,
                    (string) ($student->school->schoolnaam ?? 'GO! Atheneum Sint-Truiden'),
                    (string) ($student->gebruikersnaam ?? ''), (string) ($student->email ?? ''),
                    $student->school, $test, $override);
            }
        }

        if (($only === '' || $only === 'users') && $settings['staff_enabled'] === '1') {
            $query = User::query()->whereMonth('geboortedatum', $today->month)
                ->whereDay('geboortedatum', $today->day)
                ->where(function ($q) { $q->whereNull('smartschool_active')->orWhere('smartschool_active', 1); });
            if ($schoolFilter > 0) $query->where('schoolid', $schoolFilter);
            foreach ($query->cursor() as $staff) {
                $staff->load('school');
                $this->deliver($settingsService, $settings, 'user', (int) $staff->id,
                    (int) ($staff->schoolid ?? 0), $date, $today,
                    $staff->geboortedatum, (string) ($staff->voornaam ?? ''), (string) ($staff->name ?? ''),
                    (string) ($staff->school->schoolnaam ?? 'GO! Atheneum Sint-Truiden'),
                    (string) ($staff->smartschool_gebruikersnaam ?? ''), (string) ($staff->email ?? ''),
                    $staff->school, $test, $override);
            }
        }
        $this->info('Verjaardagencontrole voltooid.');
        return self::SUCCESS;
    }

    private function deliver(BirthdaySettings $service, array $settings, string $type, int $id,
        int $schoolId, string $date, Carbon $today, $birthdate, string $first, string $last,
        string $schoolName, string $username, string $email, $school, bool $test, string $override): void
    {
        $age = $birthdate ? (int) Carbon::parse($birthdate)->diffInYears($today) : null;
        if ($age === 18) return; // 18-jarigen: aparte privacy- en co-accountprocedure
        $suffix = $type === 'leerling' ? 'students' : 'staff';
        $vars = ['voornaam' => $first, 'naam' => $last, 'school' => $schoolName, 'leeftijd' => $age ?? ''];
        $subject = $service->render($settings['subject_'.$suffix], $vars);
        $bodyText = $service->render($settings['body_'.$suffix], $vars);
        $sender = trim($settings['sender']);
        if ($sender === '') $sender = trim((string) ($school->smartschool_verzender_beleid ?? ''));
        if ($sender === '') $sender = trim((string) ($school->smartschool_verzender ?? ''));
        if ($sender === '') $sender = config('services.smartschool.sender') ?: env('SMARTSCHOOL_SENDER_USER') ?: 'lvs';

        foreach (['smartschool', 'email'] as $channel) {
            if ($settings[$channel.'_'.$suffix] !== '1') continue;
            $recipient = $channel === 'smartschool' ? $username : $email;
            if ($test && $override !== '' && $channel === 'smartschool') $recipient = $override;
            if (trim($recipient) === '' || ($channel === 'email' && ! filter_var($recipient, FILTER_VALIDATE_EMAIL))) {
                $this->warn('Overgeslagen: '.$type.' #'.$id.' mist geldig '.$channel.'-adres.');
                continue;
            }
            if ($test) {
                $this->info('TEST: '.$type.' #'.$id.' via '.$channel.' (er is niets verstuurd).');
                continue;
            }
            if ($channel === 'email' && in_array(config('mail.default'), ['log', 'array'], true)) {
                $this->warn('E-mail uitgeschakeld: configureer eerst een echte mailtransport.');
                continue;
            }
            if (! $service->claim($schoolId, $type, $id, $date, $channel)) {
                $this->line('Overgeslagen: al verwerkt voor '.$type.' #'.$id.' via '.$channel.'.');
                continue;
            }
            try {
                if ($channel === 'smartschool') {
                    app(SmartschoolSoap::class)->sendMessage($recipient, $subject,
                        nl2br(e($bodyText)), $sender, false, 0);
                } else {
                    Mail::raw($bodyText, static function ($message) use ($recipient, $subject) {
                        $message->to($recipient)->subject($subject);
                    });
                }
                $service->finish($schoolId, $type, $id, $date, $channel, true);
                $this->info(ucfirst($type).': verstuurd naar '.$recipient.' via '.$channel);
            } catch (\Throwable $error) {
                // Uncertain outcomes must never retry automatically, to prevent duplicate birthday messages.
                $service->finish($schoolId, $type, $id, $date, $channel, false, $error->getMessage());
                Log::error('Verjaardagsbericht mislukt', ['type' => $type, 'id' => $id,
                    'schoolid' => $schoolId, 'channel' => $channel, 'error' => $error->getMessage()]);
                $this->error('Verzenden mislukt voor '.$type.' #'.$id.' via '.$channel.'.');
            }
        }
    }
}
