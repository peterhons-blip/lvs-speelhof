<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BirthdaySettings
{
    public static function defaults(): array
    {
        return [
            'enabled' => '1',
            'students_enabled' => '1',
            'staff_enabled' => '1',
            'smartschool_students' => '1',
            'smartschool_staff' => '1',
            'email_students' => '0',
            'email_staff' => '0',
            'school_id' => '0',
            'sender' => '',
            'warning_enabled' => '1',
            'warning_days' => '7',
            'warning_student' => '1',
            'warning_coaccounts' => '1',
            'warning_sender' => '',
            'warning_subject' => 'Belangrijk: je wordt binnenkort 18 — co-accounts worden uitgeschakeld',
            'warning_body_student' => '',
            'warning_body_coaccount' => '',
            'subject_students' => 'Proficiat met je verjaardag! 🎉',
            'subject_staff' => 'Proficiat met je verjaardag! 🎉',
            'body_students' => "Beste {voornaam} {naam},\n\nProficiat met je verjaardag! 🎉\nWe wensen je een fijne dag toe.\n\nMet vriendelijke groeten\n{school}",
            'body_staff' => "Beste {voornaam} {naam},\n\nProficiat met je verjaardag! 🎉\nWe wensen je een fijne dag toe.\n\nMet vriendelijke groeten\n{school}",
        ];
    }

    public function all(): array
    {
        $defaults = self::defaults();
        if (! Schema::hasTable('birthday_settings')) return $defaults;
        return array_replace($defaults, DB::table('birthday_settings')->pluck('setting_value', 'setting_key')->all());
    }

    /**
     * Read-only overview of the actual recipient accounts for one birthday date.
     * This never calls Smartschool or the mailer.
     */
    public function preview(string $date, ?array $settings = null): array
    {
        $settings ??= $this->all();
        $day = \Carbon\Carbon::parse($date, 'Europe/Brussels')->startOfDay();
        $school = (int) ($settings['school_id'] ?? 0);
        $rows = collect();

        $students = \App\Models\Leerling::query()
            ->where('active', 1)->whereMonth('geboortedatum', $day->month)
            ->whereDay('geboortedatum', $day->day);
        if ($school > 0) $students->where('schoolid', $school);
        foreach ($students->orderBy('naam')->orderBy('voornaam')->get() as $student) {
            $rows->push([
                'type' => 'leerling', 'id' => $student->id,
                'name' => trim(($student->voornaam ?? '').' '.($student->naam ?? '')),
                'school_id' => (int) ($student->schoolid ?? 0),
                'birthday' => $student->geboortedatum,
                'smartschool' => trim((string) ($student->gebruikersnaam ?? '')),
                'email' => trim((string) ($student->email ?? '')),
            ]);
        }

        $staff = \App\Models\User::query()
            ->whereMonth('geboortedatum', $day->month)
            ->whereDay('geboortedatum', $day->day)
            ->where(function ($q) { $q->whereNull('smartschool_active')->orWhere('smartschool_active', 1); });
        if ($school > 0) $staff->where('schoolid', $school);
        foreach ($staff->orderBy('name')->orderBy('voornaam')->get() as $person) {
            $rows->push([
                'type' => 'user', 'id' => $person->id,
                'name' => trim(($person->voornaam ?? '').' '.($person->name ?? '')),
                'school_id' => (int) ($person->schoolid ?? 0),
                'birthday' => $person->geboortedatum,
                'smartschool' => trim((string) ($person->smartschool_gebruikersnaam ?? '')),
                'email' => trim((string) ($person->email ?? '')),
            ]);
        }

        $registered = Schema::hasTable('birthday_deliveries')
            ? DB::table('birthday_deliveries')->where('birthday_date', $date)->get()
                ->keyBy(fn ($entry) => implode('|', [$entry->school_id, $entry->recipient_type, $entry->recipient_id, $entry->channel]))
            : collect();
        $mailerConfigured = ! in_array(config('mail.default'), ['log', 'array'], true);
        $entries = [];

        foreach ($rows as $person) {
            $age = $person['birthday'] ? (int) \Carbon\Carbon::parse($person['birthday'])->diffInYears($day) : null;
            $suffix = $person['type'] === 'leerling' ? 'students' : 'staff';
            foreach (['smartschool', 'email'] as $channel) {
                $target = $person[$channel];
                $status = 'Zou verzonden worden';
                if (($settings['enabled'] ?? '1') !== '1') $status = 'Algemeen uitgeschakeld';
                elseif (($settings[$suffix.'_enabled'] ?? '1') !== '1') $status = 'Doelgroep uitgeschakeld';
                elseif (($settings[$channel.'_'.$suffix] ?? '0') !== '1') $status = 'Kanaal uitgeschakeld';
                elseif ($age === 18) $status = '18-jarigenprocedure';
                elseif ($target === '' || ($channel === 'email' && ! filter_var($target, FILTER_VALIDATE_EMAIL))) $status = 'Geen geldig adres';
                elseif ($channel === 'email' && ! $mailerConfigured) $status = 'E-mailserver niet ingesteld';
                else {
                    $key = implode('|', [$person['school_id'], $person['type'], $person['id'], $channel]);
                    $existing = $registered->get($key);
                    if ($existing) {
                        $status = match ($existing->status) {
                            'sent' => 'Al verzonden',
                            'failed' => 'Eerdere poging mislukt',
                            default => 'Al in verwerking',
                        };
                    }
                }
                $entries[] = [
                    'name' => $person['name'], 'type' => $person['type'] === 'leerling' ? 'Leerling' : 'Personeel',
                    'school_id' => $person['school_id'], 'age' => $age,
                    'channel' => $channel, 'target' => $target, 'status' => $status,
                    'will_send' => $status === 'Zou verzonden worden',
                ];
            }
        }

        return [
            'rows' => collect($entries),
            'student_count' => $rows->where('type', 'leerling')->count(),
            'staff_count' => $rows->where('type', 'user')->count(),
        ];
    }

    public function render(string $text, array $data): string
    {
        // No HTML from editable templates; allow only predefined placeholders.
        $replacements = [];
        foreach (['voornaam', 'naam', 'school', 'leeftijd', 'dagen'] as $key) {
            $replacements['{'.$key.'}'] = (string) ($data[$key] ?? '');
        }
        return strtr($text, $replacements);
    }

    public function claim(int $schoolId, string $type, int $id, string $date, string $channel): bool
    {
        return DB::table('birthday_deliveries')->insertOrIgnore([
            'school_id' => $schoolId,
            'recipient_type' => $type,
            'recipient_id' => $id,
            'birthday_date' => $date,
            'channel' => $channel,
            'status' => 'processing',
            'created_at' => now(),
            'updated_at' => now(),
        ]) === 1;
    }

    public function finish(int $schoolId, string $type, int $id, string $date, string $channel, bool $success, ?string $error = null): void
    {
        DB::table('birthday_deliveries')
            ->where('school_id', $schoolId)
            ->where('recipient_type', $type)
            ->where('recipient_id', $id)
            ->where('birthday_date', $date)
            ->where('channel', $channel)
            ->update([
                'status' => $success ? 'sent' : 'failed',
                'error_message' => $error ? mb_substr($error, 0, 1500) : null,
                'updated_at' => now(),
            ]);
    }
}
