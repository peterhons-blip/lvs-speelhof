<?php

namespace App\Services;

class PerformanceLogReader
{
    public function read(string $path, int $limit = 2000): array
    {
        $limit = max(1, min(5000, $limit));
        if (!is_file($path) || !is_readable($path)) return [];
        $handle = @fopen($path, 'rb');
        if (!$handle) return [];
        try {
            // At most 4 MiB from the end; never load the whole application log.
            $size = fstat($handle)['size'] ?? 0;
            $offset = max(0, $size - 4 * 1024 * 1024);
            fseek($handle, $offset);
            if ($offset > 0) fgets($handle);
            $tail = stream_get_contents($handle, 4 * 1024 * 1024);
        } finally {
            fclose($handle);
        }
        $rows = [];
        foreach (array_reverse(explode("\n", (string)$tail)) as $line) {
            if (!preg_match('/^\[([0-9-]+ [0-9:]+)\].*?LVS slow web request (\{.*\})\s*$/D', $line, $match)) continue;
            $data = json_decode($match[2], true);
            if (!is_array($data)) continue;
            $route = $data['route'] ?? '';
            if (!is_string($route) || !preg_match('/\A[a-zA-Z0-9_.-]{1,120}\z/D', $route)) continue;
            if ($route === 'beheer.performance.index') continue;
            foreach (['duration_ms','sql_queries','sql_time_ms','php_memory_peak_mb'] as $field) {
                if (!isset($data[$field]) || !is_numeric($data[$field]) || !is_finite((float)$data[$field]) || $data[$field] < 0) continue 2;
            }
            $rows[] = [
                'at'=>$match[1], 'route'=>$route, 'duration_ms'=>(float)$data['duration_ms'],
                'sql_queries'=>(int)$data['sql_queries'], 'sql_time_ms'=>(float)$data['sql_time_ms'],
                'memory'=>(float)$data['php_memory_peak_mb'],
            ];
            if (count($rows) >= $limit) break;
        }
        return $rows;
    }

    public function summarize(array $rows): array
    {
        $groups = [];
        foreach ($rows as $row) $groups[$row['route']][] = $row;
        $result = [];
        foreach ($groups as $route => $items) {
            $times = array_column($items, 'duration_ms');
            sort($times, SORT_NUMERIC);
            $count = count($times);
            $result[] = [
                'route'=>$route, 'count'=>$count, 'avg'=>array_sum($times)/$count,
                'p95'=>$times[max(0, (int)ceil($count * .95)-1)], 'max'=>max($times),
                'queries'=>array_sum(array_column($items,'sql_queries'))/$count,
                'sql'=>array_sum(array_column($items,'sql_time_ms'))/$count,
            ];
        }
        usort($result, fn($a,$b)=>$b['avg'] <=> $a['avg']);
        return $result;
    }
}
