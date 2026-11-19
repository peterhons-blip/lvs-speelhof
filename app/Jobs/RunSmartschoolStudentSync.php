<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RunSmartschoolStudentSync implements ShouldQueue
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
        $run = DB::table('smartschool_sync_runs')->where('id', $this->runId)->first();
        if (! $run || $run->status !== 'waiting') return;

        DB::table('smartschool_sync_runs')->where('id', $this->runId)
            ->where('status', 'waiting')->update([
                'status' => 'running', 'started_at' => now(), 'updated_at' => now(),
            ]);

        try {
            $parameters = ['--schoolid' => (int) $run->school_id];
            if ($run->refresh_scancodes) $parameters['--refresh-scancodes'] = true;
            $code = Artisan::call('smartschool:sync-leerlingen', $parameters);
            $output = trim(Artisan::output());
            // Only summarize: full per-student logs should not be displayed on a web page.
            $lines = preg_split('/\R/', $output) ?: [];
            $summary = collect($lines)->filter(fn ($line) =>
                str_contains($line, 'Klaar.') || str_contains($line, 'Sync reeds actief')
            )->last();
            DB::table('smartschool_sync_runs')->where('id', $this->runId)->update([
                'status' => $code === 0 ? 'completed' : 'failed',
                'summary' => mb_substr((string) ($summary ?: ($code === 0
                    ? 'Synchronisatie voltooid.' : 'Synchronisatie niet geslaagd; zie Laravel-log.')), 0, 1000),
                'finished_at' => now(), 'updated_at' => now(),
            ]);
            if ($code !== 0) Log::warning('Handmatige Smartschool-leerlingensync exitcode', ['run_id' => $this->runId, 'code' => $code]);
        } catch (\Throwable $e) {
            $this->failed($e);
            throw $e;
        }
    }

    public function failed(?\Throwable $exception): void
    {
        DB::table('smartschool_sync_runs')->where('id', $this->runId)->update([
            'status' => 'failed',
            'summary' => 'Technische fout. Controleer het Laravel-log.',
            'finished_at' => now(), 'updated_at' => now(),
        ]);
        Log::error('Handmatige leerlingen-sync mislukt', [
            'run_id' => $this->runId, 'error' => $exception?->getMessage(),
        ]);
    }
}
