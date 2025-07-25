<?php

namespace LaravelSpectrum\Performance;

use RuntimeException;

class MemoryManager
{
    private int $memoryLimit;

    private float $warningThreshold = 0.8; // 80%で警告

    private float $criticalThreshold = 0.9; // 90%でクリティカル

    public function __construct()
    {
        $this->memoryLimit = $this->parseMemoryLimit(ini_get('memory_limit'));
    }

    public function checkMemoryUsage(): void
    {
        $usage = memory_get_usage(true);
        $percentage = $usage / $this->memoryLimit;

        if ($percentage > $this->criticalThreshold) {
            throw new RuntimeException(
                sprintf(
                    'Memory usage critical: %s of %s (%.2f%%)',
                    $this->formatBytes($usage),
                    $this->formatBytes($this->memoryLimit),
                    $percentage * 100
                )
            );
        }

        if ($percentage > $this->warningThreshold) {
            // ログに警告を記録
            if (function_exists('app') && app()->has('log')) {
                app('log')->warning('High memory usage detected', [
                    'usage' => $this->formatBytes($usage),
                    'limit' => $this->formatBytes($this->memoryLimit),
                    'percentage' => $percentage * 100,
                ]);
            }

            // ガベージコレクションを実行
            $this->runGarbageCollection();
        }
    }

    public function getAvailableMemory(): int
    {
        return $this->memoryLimit - memory_get_usage(true);
    }

    public function runGarbageCollection(): void
    {
        gc_collect_cycles();
    }

    public function getMemoryStats(): array
    {
        $usage = memory_get_usage(true);
        $peakUsage = memory_get_peak_usage(true);

        return [
            'current' => $this->formatBytes($usage),
            'peak' => $this->formatBytes($peakUsage),
            'limit' => $this->formatBytes($this->memoryLimit),
            'percentage' => round(($usage / $this->memoryLimit) * 100, 2),
        ];
    }

    private function parseMemoryLimit(string $limit): int
    {
        $limit = trim($limit);
        $last = strtolower($limit[strlen($limit) - 1]);
        $value = (int) $limit;

        switch ($last) {
            case 'g':
                $value *= 1024 * 1024 * 1024;
                break;
            case 'm':
                $value *= 1024 * 1024;
                break;
            case 'k':
                $value *= 1024;
                break;
        }

        return $value;
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2).' '.$units[$i];
    }
}
