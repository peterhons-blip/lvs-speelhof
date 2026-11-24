<?php

namespace App\Services;

use App\Models\Melding;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Processes rules on a newly registered incident, never on historical edits/imports.
 * Missing tables cause a no-op. Sends are claimed before contacting Smartschool;
 * uncertain remote outcomes are not retried blindly.
 */
class LvsFollowupEngine
{
    public function evaluate(Melding $incident): void
    {
        try {
            if (! Schema::hasTable('lvs_followup_rules') ||
                ! Schema::hasTable('lvs_followup_events')) return;

            $type = DB::table('meldingssoorten')->where('id', $incident->meldingssoortId)->first();
            if (! $type) return;
            $student = DB::table('leerlingen')->where('id', $incident->leerlingId)->first();
            if (! $student) return;

            $rules = DB::table('lvs_followup_rules')->where('enabled', 1)
                ->where(function ($query) use ($type) {
                    $query->whereNull('category_id')->orWhere('category_id', $type->meldingscategorieId);
                })
                ->where(function ($query) use ($type) {
                    $query->whereNull('type_id')->orWhere('type_id', $type->id);
                })->get();

            foreach ($rules as $rule) {
                $query = DB::table('meldingen as m')
                    ->join('meldingssoorten as s', 's.id', '=', 'm.meldingssoortId')
                    ->where('m.leerlingId', $incident->leerlingId)
                    ->where('m.id', '<=', $incident->id);
                if ($rule->category_id !== null) $query->where('s.meldingscategorieId', $rule->category_id);
                if ($rule->type_id !== null) $query->where('s.id', $rule->type_id);
                if ((int) $rule->window_days > 0) {
                    $query->where('m.created_at', '>=',
                        \Illuminate\Support\Carbon::parse($incident->created_at)
                            ->subDays((int) $rule->window_days)->toDateTimeString());
                }
                // Trigger once when the Nth registered incident is reached. Rules can
                // be created for separate milestones (4th, 6th, 10th, etc.).
                if ((int) $query->count() !== (int) $rule->threshold) continue;

                if ($rule->action === 'review' || $rule->action === 'sanction_proposal') {
                    $this->record((int) $rule->id, $incident, $rule->action,
                        'leerlingbegeleiding', null, 'pending');
                    continue;
                }
                if ($rule->action !== 'smartschool') continue;
                $this->sendSmartschool($rule, $incident, $student);
            }
        } catch (\Throwable $error) {
            // The incident is already saved and must not be rolled back or duplicated.
            Log::error('LVS opvolgregels konden niet verwerkt worden', [
                'melding_id' => $incident->id,
                'error' => $error->getMessage(),
            ]);
        }
    }

    private function sendSmartschool(object $rule, Melding $incident, object $student): void
    {
        $username = trim((string) ($student->gebruikersnaam ?? ''));
        $recipients = [];
        if ((int) $rule->recipient_student === 1 && $username !== '') {
            $recipients[] = [$username, 0, 'leerling'];
        }
        if ((int) $rule->recipient_parents === 1 && $username !== '') {
            // Never contact parent co-accounts after the student turns 18, even
            // if the privacy deactivation job has not run or has been paused.
            $isAdult = !empty($student->geboortedatum)
                && \Illuminate\Support\Carbon::parse($student->geboortedatum)
                    ->addYears(18)->startOfDay()->lte(now('Europe/Brussels'));
            if (!$isAdult) {
                // The API result of each co-account is individually audited.
                for ($i = 1; $i <= 6; $i++) $recipients[] = [$username, $i, 'ouder/voogd'];
            } else {
                $this->record((int) $rule->id, $incident, 'smartschool',
                    'ouderaccounts niet toegelaten na 18 jaar', 0, 'skipped',
                    'Leerling is meerderjarig; ouder-/voogdaccounts worden niet automatisch aangeschreven.');
            }
        }
        if ((int) $rule->recipient_counselor === 1) {
            $recipients[] = [trim((string) $rule->counselor_username), 0, 'leerlingbegeleiding'];
        }
        if ((int) $rule->recipient_mentor === 1) {
            $recipients[] = [trim((string) $rule->mentor_username), 0, 'klastitularis'];
        }
        if ($recipients === []) {
            $this->record((int) $rule->id, $incident, 'smartschool',
                'ontvanger niet gevonden', null, 'skipped', 'Geen geschikte ontvanger geconfigureerd');
            return;
        }

        $vars = [
            '{voornaam}' => (string) $student->voornaam,
            '{naam}' => (string) $student->naam,
            '{aantal}' => (string) $rule->threshold,
            '{categorie}' => (string) (DB::table('meldingscategorien')
                ->where('id', $rule->category_id)->value('naam') ?: 'meldingen'),
        ];
        // Never include the incident's free-text comment automatically.
        $subject = strtr((string) $rule->subject, $vars);
        $body = nl2br(e(strtr((string) $rule->body, $vars)));
        $sender = trim((string) $rule->sender_username) ?: 'lvs';

        foreach ($recipients as [$recipient, $coaccount, $label]) {
            if ($recipient === '') continue;
            $id = $this->record((int) $rule->id, $incident, 'smartschool',
                $recipient, $coaccount, 'processing');
            if (! $id) continue; // Already claimed: no duplicate messages on retries.
            try {
                app(SmartschoolSoap::class)->sendMessage(
                    $recipient, $subject, $body, $sender, false, $coaccount
                );
                $this->finish($id, 'sent');
            } catch (\Throwable $error) {
                $this->finish($id, 'failed', $error->getMessage());
                Log::warning('LVS Smartschool-opvolging mislukt', [
                    'rule_id' => $rule->id, 'melding_id' => $incident->id,
                    'receiver_type' => $label, 'error' => $error->getMessage(),
                ]);
            }
        }
    }

    private function record(int $ruleId, Melding $incident, string $action,
        string $recipient, ?int $coaccount, string $status, ?string $error = null): ?int
    {
        $inserted = DB::table('lvs_followup_events')->insertOrIgnore([
            'rule_id' => $ruleId, 'melding_id' => $incident->id,
            'leerling_id' => $incident->leerlingId,
            'action' => $action, 'recipient' => $recipient,
            'coaccount' => $coaccount ?? 0, 'status' => $status,
            'error_message' => $error, 'created_at' => now(), 'updated_at' => now(),
        ]);
        if (!$inserted) return null;
        return (int) DB::table('lvs_followup_events')
            ->where('rule_id', $ruleId)->where('melding_id', $incident->id)
            ->where('recipient', $recipient)->where('coaccount', $coaccount ?? 0)
            ->value('id');
    }

    private function finish(int $eventId, string $status, ?string $error = null): void
    {
        DB::table('lvs_followup_events')->where('id', $eventId)->update([
            'status' => $status,
            'error_message' => $error ? mb_substr($error, 0, 1800) : null,
            'updated_at' => now(),
        ]);
    }
}
