<?php
namespace App\Services;

class WindowsOperationsStatus
{
    private function read(string $name): ?array
    {
        $dir = config('lvs_performance.status_directory');
        if (!is_string($dir) || $dir === '') return null;
        $path = rtrim($dir, '/\\').DIRECTORY_SEPARATOR.$name;
        if (!is_file($path) || !is_readable($path)) return null;
        $data = json_decode((string)@file_get_contents($path,false,null,0,8192),true);
        if (!is_array($data) || !is_string($data['timestamp'] ?? null)) return null;
        try {
            $time = new \DateTimeImmutable($data['timestamp']);
            $age = time()-$time->getTimestamp();
            if ($age < -60) return null;
            $data['age'] = max(0,$age);
            return $data;
        } catch (\Throwable) { return null; }
    }

    public function health(): ?array
    {
        $data = $this->read('server-health.json');
        if (!$data) return null;
        foreach (['cpu_percent','ram_percent','ram_total_mb','ram_free_mb'] as $key) {
            if (!is_numeric($data[$key] ?? null) || !is_finite((float)$data[$key]) || $data[$key] < 0) return null;
        }
        if ($data['cpu_percent'] > 100 || $data['ram_percent'] > 100 || $data['ram_free_mb'] > $data['ram_total_mb']) return null;
        return array_intersect_key($data,array_flip(['age','cpu_percent','ram_percent','ram_total_mb','ram_free_mb']));
    }

    public function backup(): ?array
    {
        $data = $this->read('backup-status.json');
        if (!$data || !in_array($data['local'] ?? null,['ok','failed'],true) || !in_array($data['external'] ?? null,['ok','failed','not_configured'],true) || !is_numeric($data['bytes'] ?? null) || $data['bytes'] < 0) return null;
        return array_intersect_key($data,array_flip(['age','local','external','bytes']));
    }
}
