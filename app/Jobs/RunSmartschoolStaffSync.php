<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RunSmartschoolStaffSync implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;
    public int $timeout = 1800;

    public function __construct(public int $runId)
    {
        $this->onConnection('smartschool_sync');
        $this->onQueue('smartschool-sync');
    }

    public function handle(): void
    {
        $run = DB::table('smartschool_staff_sync_runs')->where('id', $this->runId)->first();
        if (! $run || $run->status !== 'waiting') return;

        $claimed = DB::table('smartschool_staff_sync_runs')
            ->where('id', $this->runId)->where('status', 'waiting')
            ->update(['status' => 'running', 'started_at' => now(), 'updated_at' => now()]);
        if (! $claimed) return;

        try {
            $args = ['--schoolid' => (int) $run->school_id];
            if ($run->apply_changes) $args['--apply'] = true;
            $code = Artisan::call('smartschool:sync-personeel', $args);
            $output = trim(Artisan::output());
            $lines = preg_split('/\R/', $output) ?: [];
            $counts = collect($lines)->first(fn ($line) => str_contains($line, 'Nieuw:'));
            $summary = $counts ?: ($code === 0 ? 'Preview uitgevoerd.' : 'Synchronisatie niet geslaagd; controleer Laravel-log.');
            DB::table('smartschool_staff_sync_runs')->where('id', $this->runId)->update([
                'status' => $code === 0 ? 'completed' : 'failed',
                'summary' => mb_substr($summary, 0, 800),
                'finished_at' => now(), 'updated_at' => now(),
            ]);
            if ($code !== 0) Log::warning('Smartschool personeelssync niet geslaagd', [
                'run_id' => $this->runId, 'code' => $code, 'output' => mb_substr($output, 0, 2000),
            ]);
        } catch (\Throwable $exception) {
            $this->failed($exception);
            throw $exception;
        }
    }

    public function failed(?\Throwable $exception): void
    {
        DB::table('smartschool_staff_sync_runs')->where('id', $this->runId)->update([
            'status' => 'failed',
            'summary' => 'Technische fout. Controleer het Laravel-log.',
            'finished_at' => now(), 'updated_at' => now(),
        ]);
        Log::error('Smartschool personeelssync mislukt', [
            'run_id' => $this->runId, 'error' => $exception?->getMessage(),
        ]);
    }
}
