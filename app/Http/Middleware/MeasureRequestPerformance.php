<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Low-overhead, opt-in TTFB diagnostics. Does not log SQL text, bindings,
 * usernames, full URLs, session identifiers or student information.
 */
class MeasureRequestPerformance
{
    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = microtime(true);
        $queryCount = 0;
        $queryTimeMs = 0.0;

        $enabled = (bool) config('lvs_performance.enabled', false);
        if ($enabled) {
            DB::listen(static function ($query) use (&$queryCount, &$queryTimeMs): void {
                $queryCount++;
                $queryTimeMs += (float) $query->time;
            });
        }

        try {
            return $next($request);
        } finally {
            if ($enabled) {
                $durationMs = (microtime(true) - $startedAt) * 1000;
                $thresholdMs = (int) config('lvs_performance.slow_request_ms', 1000);

                if ($durationMs >= $thresholdMs) {
                    Log::warning('LVS slow web request', [
                        'route' => $request->route()?->getName() ?? 'unnamed',
                        'method' => $request->method(),
                        'duration_ms' => round($durationMs, 1),
                        'sql_queries' => $queryCount,
                        'sql_time_ms' => round($queryTimeMs, 1),
                        'php_memory_peak_mb' => round(memory_get_peak_usage(true) / 1048576, 1),
                    ]);
                }
            }
        }
    }
}
