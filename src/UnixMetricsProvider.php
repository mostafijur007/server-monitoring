<?php
declare(strict_types=1);

require_once __DIR__ . '/MetricsProvider.php';

final class UnixMetricsProvider implements MetricsProvider
{
    public function cpu(): array
    {
        $cores = (int)shell_exec('nproc') ?: 1;

        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            $loadValue = $load[0];
            $percentage = min(100.0, round(($loadValue / max(1, $cores)) * 100, 1));

            return [
                'cores' => $cores,
                'load' => round($loadValue, 2),
                'percentage' => $percentage,
            ];
        }

        return [
            'cores' => $cores,
            'load' => 1.0,
            'percentage' => 50.0,
        ];
    }

    public function ram(): array
    {
        $output = @shell_exec('free -m');
        if ($output) {
            $lines = explode("\n", trim($output));
            if (isset($lines[1])) {
                $parts = preg_split('/\s+/', trim($lines[1]));
                if (count($parts) >= 4) {
                    $total = (int)$parts[1];
                    $used = (int)$parts[2];
                    $free = (int)$parts[3];
                    $percentage = $total > 0 ? round(($used / $total) * 100, 2) : 0.0;

                    return compact('total', 'used', 'free') + ['percentage' => $percentage];
                }
            }
        }

        return [
            'total' => 4096,
            'used' => 2048,
            'free' => 2048,
            'percentage' => 50.0,
        ];
    }

    public function disk(): array
    {
        $path = '/';
        $totalBytes = @disk_total_space($path) ?: 1;
        $freeBytes = @disk_free_space($path) ?: 0;
        $usedBytes = max(0, $totalBytes - $freeBytes);
        $percentage = $totalBytes > 0 ? round(($usedBytes / $totalBytes) * 100, 2) : 0.0;

        return [
            'total' => round($totalBytes / 1024 / 1024 / 1024, 2),
            'used' => round($usedBytes / 1024 / 1024 / 1024, 2),
            'free' => round($freeBytes / 1024 / 1024 / 1024, 2),
            'percentage' => $percentage,
        ];
    }

    public function network(): array
    {
        static $last = null;

        $interface = trim((string)shell_exec("ip route | awk '/^default/ {print \$5; exit}'"));
        if ($interface === '') {
            $list = array_filter(explode("\n", trim((string)shell_exec("ls /sys/class/net"))));
            $interface = $list[0] ?? '';
        }

        if ($interface === '') {
            return [
                'rx_bytes' => 0,
                'tx_bytes' => 0,
                'rx_mbps' => 0.0,
                'tx_mbps' => 0.0,
                'error' => 'No network interface found',
            ];
        }

        $rxPath = "/sys/class/net/{$interface}/statistics/rx_bytes";
        $txPath = "/sys/class/net/{$interface}/statistics/tx_bytes";

        if (!is_readable($rxPath) || !is_readable($txPath)) {
            return [
                'rx_bytes' => 0,
                'tx_bytes' => 0,
                'rx_mbps' => 0.0,
                'tx_mbps' => 0.0,
                'error' => 'Could not read interface statistics',
            ];
        }

        $now = microtime(true);
        $rx = (int)@file_get_contents($rxPath);
        $tx = (int)@file_get_contents($txPath);

        if ($last === null || ($now - $last['time']) > 10) {
            $last = ['rx' => $rx, 'tx' => $tx, 'time' => $now, 'interface' => $interface];
            return [
                'rx_bytes' => 0,
                'tx_bytes' => 0,
                'rx_mbps' => 0.0,
                'tx_mbps' => 0.0,
                'interface' => $interface,
            ];
        }

        $timeDiff = $now - $last['time'];
        $rxDiff = max(0, $rx - $last['rx']);
        $txDiff = max(0, $tx - $last['tx']);

        $last = ['rx' => $rx, 'tx' => $tx, 'time' => $now, 'interface' => $interface];

        $rxBps = $timeDiff > 0 ? $rxDiff / $timeDiff : 0;
        $txBps = $timeDiff > 0 ? $txDiff / $timeDiff : 0;

        return [
            'rx_bytes' => $rxDiff,
            'tx_bytes' => $txDiff,
            'rx_bps' => round($rxBps),
            'tx_bps' => round($txBps),
            'rx_mbps' => round($rxBps * 8 / 1000000, 3),
            'tx_mbps' => round($txBps * 8 / 1000000, 3),
            'interface' => $interface,
        ];
    }

    public function serverInfo(): array
    {
        return [
            'php_version' => phpversion(),
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
        ];
    }
}
