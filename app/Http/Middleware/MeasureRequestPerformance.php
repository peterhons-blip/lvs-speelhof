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
        $groups = [];
        $detail = (bool) config('lvs_performance.query_details', false);

        $enabled = (bool) config('lvs_performance.enabled', false);
        if ($enabled) {
            DB::listen(static function ($query) use (&$queryCount, &$queryTimeMs, &$groups, $detail): void {
                $queryCount++;
                $queryTimeMs += (float) $query->time;
                if ($detail) {
                    // HMAC fingerprint only: never record SQL or bound values.
                    $fingerprint = substr(hash_hmac('sha256', $query->sql, (string) config('app.key')), 0, 16);
                    if (!isset($groups[$fingerprint])) $groups[$fingerprint] = ['count'=>0,'total_ms'=>0.0,'max_ms'=>0.0];
                    $groups[$fingerprint]['count']++;
                    $groups[$fingerprint]['total_ms'] += (float) $query->time;
                    $groups[$fingerprint]['max_ms'] = max($groups[$fingerprint]['max_ms'], (float) $query->time);
                }
            });
        }

        try {
            return $next($request);
        } finally {
            if ($enabled) {
                $durationMs = (microtime(true) - $startedAt) * 1000;
                $thresholdMs = (int) config('lvs_performance.slow_request_ms', 1000);

                if ($durationMs >= $thresholdMs) {
                    uasort($groups, fn($a,$b)=>$b['total_ms'] <=> $a['total_ms']);
                    Log::warning('LVS slow web request', [
                        'route' => $request->route()?->getName() ?? 'unnamed',
                        'method' => $request->method(),
                        'duration_ms' => round($durationMs, 1),
                        'sql_queries' => $queryCount,
                        'sql_time_ms' => round($queryTimeMs, 1),
                        'query_groups' => $detail ? array_slice($groups, 0, 8, true) : [],
                        'php_memory_peak_mb' => round(memory_get_peak_usage(true) / 1048576, 1),
                    ]);
                }
            }
        }
    }
}
