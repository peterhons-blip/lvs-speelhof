<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Immutable audit of the first attempt for each 18-year task.
 * Failed/uncertain remote operations are NOT retried blindly.
 */
class Birthday18Actions
{
    public function status(int $schoolId, int $studentId, string $date, string $action): ?string
    {
        return DB::table('birthday_18_actions')
            ->where('school_id', $schoolId)->where('student_id', $studentId)
            ->where('target_date', $date)->where('action_key', $action)
            ->value('status');
    }

    public function run(int $schoolId, int $studentId, string $date, string $action, string $recipient, callable $callback): bool
    {
        $inserted = DB::table('birthday_18_actions')->insertOrIgnore([
            'school_id' => $schoolId,
            'student_id' => $studentId,
            'target_date' => $date,
            'action_key' => $action,
            'recipient' => $recipient,
            'status' => 'processing',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        if (! $inserted) return $this->status($schoolId, $studentId, $date, $action) === 'sent';

        try {
            $callback();
            $status = 'sent';
            $message = null;
        } catch (\Throwable $error) {
            $status = 'failed';
            $message = mb_substr($error->getMessage(), 0, 1800);
            \Illuminate\Support\Facades\Log::error('18-jarigenactie mislukt', [
                'school_id' => $schoolId, 'student_id' => $studentId, 'action' => $action, 'error' => $message,
            ]);
        }
        DB::table('birthday_18_actions')
            ->where('school_id', $schoolId)->where('student_id', $studentId)
            ->where('target_date', $date)->where('action_key', $action)
            ->update(['status' => $status, 'error_message' => $message, 'updated_at' => now()]);
        return $status === 'sent';
    }
}
