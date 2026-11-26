<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class RunScheduledTask extends Command
{
    protected $signature = 'monitor:scheduled {task : Configured scheduled task key}';
    protected $description = 'Execute a registered nightly task and persist its real status';

    private const TASKS = [
        'secretariat_reminders' => ['command'=>'leerlingen:secretariaat-herinneringen'],
        'becoming_18' => ['command' => 'leerlingen:check-18'],
        'birthdays' => ['command' => 'verjaardagen:check'],
        'student_sync' => ['command' => 'smartschool:sync-leerlingen', 'parameters' => ['--schoolid' => 1]],
        'staff_sync' => ['command' => 'smartschool:sync-personeel', 'parameters' => ['--schoolid' => 1, '--apply' => true]],
        'library_reminders' => ['command' => 'bibliotheek:herinneringen'],
    ];

    public function handle(): int
    {
        $key = (string) $this->argument('task');
        if (! array_key_exists($key, self::TASKS)) {
            $this->error('Onbekende geplande taak.');
            return self::FAILURE;
        }

        if (! Schema::hasTable('scheduled_task_runs')) {
            // Monitoring must never stop the underlying pupil/privacy jobs.
            // Keep scheduled commands running even when a new migration is pending.
            $this->warn('Monitoring nog niet geïnstalleerd; taak wordt zonder registratie uitgevoerd.');
            try {
                $task = self::TASKS[$key];
                $code = Artisan::call($task['command'], $task['parameters'] ?? []);
                $output = trim(Artisan::output());
                if ($output !== '') $this->line($output);
                return $code === 0 ? self::SUCCESS : self::FAILURE;
            } catch (\Throwable $e) {
                Log::error('Geplande taak mislukt zonder monitoring', [
                    'task' => $key, 'error' => $e->getMessage(),
                ]);
                return self::FAILURE;
            }
        }

        $runId = DB::table('scheduled_task_runs')->insertGetId([
            'task_key' => $key, 'status' => 'running', 'started_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        try {
            $task = self::TASKS[$key];
            $code = Artisan::call($task['command'], $task['parameters'] ?? []);
            $output = trim(Artisan::output());
            if ($output !== '') $this->line($output);

            $summary = $this->summary($key, $output);
            $warning = $this->hasWarning($key, $output);
            $status = $code === 0 ? ($warning ? 'warning' : 'completed') : 'failed';

            DB::table('scheduled_task_runs')->where('id', $runId)->update([
                'status' => $status,
                'summary' => $summary,
                'finished_at' => now(), 'updated_at' => now(),
            ]);
            return $code === 0 ? self::SUCCESS : self::FAILURE;
        } catch (\Throwable $e) {
            DB::table('scheduled_task_runs')->where('id', $runId)->update([
                'status' => 'failed', 'summary' => 'Technische fout; raadpleeg het Laravel-log.',
                'finished_at' => now(), 'updated_at' => now(),
            ]);
            Log::error('Nachtelijke taak mislukt', ['task' => $key, 'error' => $e->getMessage()]);
            $this->error('Nachtelijke taak mislukt. Raadpleeg het Laravel-log.');
            return self::FAILURE;
        }
    }

    private function hasWarning(string $key, string $output): bool
    {
        if ($key === 'student_sync') {
            return preg_match('/\b(failed_classes|scancodes_conflicts)=[1-9][0-9]*/', $output) === 1;
        }
        if ($key === 'library_reminders' && preg_match('/Klaar:\s*(\d+)\s+van\s+(\d+)\s+herinneringen/', $output, $m)) {
            return (int) $m[1] < (int) $m[2];
        }
        return preg_match('/(?:^|\n).*?(?:FOUT|fout naar|MAIL FOUT|fout bij versturen)/mi', $output) === 1;
    }

    private function summary(string $key, string $output): string
    {
        // Never expose unfiltered Smartschool log lines containing pupil names or usernames.
        if ($key === 'student_sync') {
            if (preg_match('/Klaar\.\s*Processed=(\d+),\s*created=(\d+),\s*updated=(\d+),\s*classes_created=(\d+),\s*deactivated=(\d+),\s*scancodes_updated=(\d+),\s*scancodes_missing=(\d+),\s*scancodes_conflicts=(\d+),\s*failed_classes=(\d+),\s*empty_classes=(\d+)/', $output, $m)) {
                return "Verwerkt: {$m[1]}; nieuw: {$m[2]}; bijgewerkt: {$m[3]}; nieuwe klassen: {$m[4]}; gedeactiveerd: {$m[5]}; QR-codes bijgewerkt: {$m[6]}; ontbrekende QR-codes: {$m[7]}; conflicten: {$m[8]}; mislukte klassen: {$m[9]}; lege klassen: {$m[10]}.";
            }
            return 'Leerlingensync uitgevoerd; resultaat controleren in serverlog.';
        }
        if ($key === 'staff_sync') {
            if (preg_match('/Nieuw:\s*(\d+).*?Bijwerken:\s*(\d+).*?Uitschakelen:\s*(\d+)/u', $output, $m)) {
                return "Synchronisatie: {$m[1]} nieuw, {$m[2]} bij te werken, {$m[3]} inactief te markeren.";
            }
            return 'Personeelssync uitgevoerd; controleer de beveiligde serverlog.';
        }
        if ($key === 'library_reminders') {
            if (str_contains($output, 'Smartschool-herinneringen staan uit')) return 'Geen berichten verzonden: herinneringen staan uit.';
            if (preg_match('/Klaar:\s*(\d+)\s+van\s+(\d+)\s+herinneringen verzonden/', $output, $m)) {
                return "{$m[1]} van {$m[2]} geplande herinneringen verzonden.";
            }
            return 'Herinneringen gecontroleerd; zie serverlog voor details.';
        }
        if ($key === 'birthdays') {
            $pupils = preg_match_all('/^Leerling: verstuurd naar /m', $output);
            $staff = preg_match_all('/^User: verstuurd naar /m', $output);
            return "{$pupils} leerling- en {$staff} personeelsverjaardagsberichten gemeld als verzonden.";
        }
        if ($key === 'becoming_18') {
            return '18-jarigen en waarschuwingen gecontroleerd; details in beveiligd serverlog.';
        }
        return 'Taak uitgevoerd.';
    }
}
