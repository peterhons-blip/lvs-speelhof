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

    public function render(string $text, array $data): string
    {
        // No HTML from editable templates; allow only predefined placeholders.
        $replacements = [];
        foreach (['voornaam', 'naam', 'school', 'leeftijd'] as $key) {
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
