<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Audits delivery metadata only. Never persists message contents, attachments,
 * access codes or SOAP requests. Logging must not interrupt existing sending.
 */
class SentItemLog
{
    public function record(string $channel, string $recipient, ?string $sender,
        ?string $subject, string $status, string $source = 'LVS',
        ?int $coaccount = null, ?string $error = null, ?string $recipientType = null): void
    {
        try {
            if (! Schema::hasTable('portal_sent_items')) return;
            DB::table('portal_sent_items')->insert([
                'channel' => mb_substr($channel, 0, 20),
                'source' => mb_substr($source, 0, 100),
                'sender' => $sender !== null ? mb_substr($sender, 0, 255) : null,
                'recipient' => mb_substr($recipient, 0, 320),
                'recipient_type' => $recipientType,
                'subject' => $subject !== null ? mb_substr($subject, 0, 500) : null,
                'coaccount' => $coaccount,
                'status' => $status,
                'error_message' => $error !== null ? mb_substr($error, 0, 1500) : null,
                'created_at' => now(),
            ]);
        } catch (\Throwable $exception) {
            // Delivery may already have succeeded. Never retry or fail a real send
            // because recording its metadata failed.
            Log::warning('Verzendregistratie niet beschikbaar', [
                'channel' => $channel, 'error' => $exception->getMessage(),
            ]);
        }
    }

    public function sourceFromStack(): string
    {
        $classes = array_column(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 18), 'class');
        $mapping = [
            'LibraryLoanNotifier' => 'Bibliotheek',
            'LibraryEscalationNotifier' => 'Bibliotheek',
            'CheckLeeftijdLeerlingen' => '18-jarigen',
            'CheckVerjaardagen' => 'Verjaardagen',
            'SmartschoolTestMessageController' => 'Smartschool-test',
        ];
        foreach ($classes as $class) {
            foreach ($mapping as $needle => $label) {
                if (str_ends_with($class, '\\'.$needle)) return $label;
            }
        }
        return 'LVS';
    }
}
