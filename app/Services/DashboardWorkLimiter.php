<?php

declare(strict_types=1);

namespace App\Services;

final class DashboardWorkLimiter
{
    public function run(string $key, int $intervalSeconds, callable $work): array
    {
        $directory = BASE_PATH . '/storage/cache';
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $safeKey = preg_replace('/[^a-z0-9_.-]+/i', '_', $key) ?: 'dashboard';
        $lockPath = $directory . '/' . $safeKey . '.lock';
        $statePath = $directory . '/' . $safeKey . '.json';
        $lock = fopen($lockPath, 'c+');

        if ($lock === false) {
            return [
                'ran' => true,
                'result' => $work(),
            ];
        }

        try {
            if (!flock($lock, LOCK_EX | LOCK_NB)) {
                return [
                    'ran' => false,
                    'skipped' => 'busy',
                    'result' => null,
                ];
            }

            $now = time();
            $lastRunAt = $this->lastRunAt($statePath);
            if ($lastRunAt > 0 && ($now - $lastRunAt) < max(1, $intervalSeconds)) {
                return [
                    'ran' => false,
                    'skipped' => 'recent',
                    'result' => null,
                ];
            }

            $result = $work();
            file_put_contents($statePath, json_encode(['last_run_at' => $now], JSON_UNESCAPED_UNICODE));

            return [
                'ran' => true,
                'result' => $result,
            ];
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function lastRunAt(string $statePath): int
    {
        if (!is_file($statePath)) {
            return 0;
        }

        $state = json_decode((string) file_get_contents($statePath), true);

        return is_array($state) ? (int) ($state['last_run_at'] ?? 0) : 0;
    }
}
