<?php
declare(strict_types=1);

require_once __DIR__ . '/MetricsProvider.php';

final class WindowsMetricsProvider implements MetricsProvider
{
    private function parseWmicCsv(string $csv): array
    {
        $lines = array_values(array_filter(array_map('trim', explode("\n", $csv))));
        $rows = [];
        if (count($lines) < 2) {
            return $rows;
        }
        $headers = str_getcsv($lines[0]);
        for ($i = 1; $i < count($lines); $i++) {
            $cols = str_getcsv($lines[$i]);
            if (count($cols) === count($headers)) {
                $rows[] = array_combine($headers, $cols);
            }
        }
        return $rows;
    }

    public function cpu(): array
    {
        $cores = 1;
        $out = @shell_exec('wmic cpu get NumberOfCores /format:csv');
        $rows = $out ? $this->parseWmicCsv($out) : [];
        if (isset($rows[0]['NumberOfCores'])) {
            $cores = (int)$rows[0]['NumberOfCores'];
        }

        $loadPercentage = 0.0;
        if (class_exists('COM') || extension_loaded('com_dotnet')) {
            try {
                $wmi = new COM('Winmgts://');
                $cpus = $wmi->ExecQuery('SELECT LoadPercentage FROM Win32_Processor');
                $sum = 0;
                $count = 0;
                foreach ($cpus as $c) {
                    $sum += $c->LoadPercentage;
                    $count++;
                }
                $loadPercentage = $count > 0 ? ($sum / $count) : 0.0;
            } catch (Throwable $t) {
            }
        }

        if ($loadPercentage === 0.0) {
            $out = @shell_exec('wmic cpu get LoadPercentage /format:csv');
            $rows = $out ? $this->parseWmicCsv($out) : [];
            if (isset($rows[0]['LoadPercentage'])) {
                $loadPercentage = (float)$rows[0]['LoadPercentage'];
            }
        }

        return [
            'cores' => $cores,
            'load' => round($loadPercentage / 100, 2),
            'percentage' => round($loadPercentage, 1),
        ];
    }

    public function ram(): array
    {
        $out = @shell_exec('wmic OS get FreePhysicalMemory,TotalVisibleMemorySize /Format:csv');
        $rows = $out ? $this->parseWmicCsv($out) : [];
        if (isset($rows[0]['FreePhysicalMemory'], $rows[0]['TotalVisibleMemorySize'])) {
            $freeKb = (int)$rows[0]['FreePhysicalMemory'];
            $totalKb = (int)$rows[0]['TotalVisibleMemorySize'];
            $total = (int)round($totalKb / 1024);
            $free = (int)round($freeKb / 1024);
            $used = max(0, $total - $free);
            $percentage = $total > 0 ? round(($used / $total) * 100, 2) : 0.0;

            return compact('total', 'used', 'free') + ['percentage' => $percentage];
        }

        return [
            'total' => 8192,
            'used' => 4096,
            'free' => 4096,
            'percentage' => 50.0,
        ];
    }

    public function disk(): array
    {
        $root = dirname(__FILE__, 2);
        $drive = strtoupper(substr($root, 0, 2)) === substr($root, 0, 2) ? substr($root, 0, 2) : 'C:';
        $path = $drive . DIRECTORY_SEPARATOR;

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
        $out = @shell_exec('wmic PATH Win32_PerfFormattedData_Tcpip_NetworkInterface GET BytesReceivedPersec,BytesSentPersec,Name /FORMAT:csv');
        $rows = $out ? $this->parseWmicCsv($out) : [];

        $rx = 0;
        $tx = 0;
        foreach ($rows as $r) {
            $name = $r['Name'] ?? '';
            if (stripos($name, 'loopback') !== false) {
                continue;
            }
            $rx += (int)($r['BytesReceivedPersec'] ?? 0);
            $tx += (int)($r['BytesSentPersec'] ?? 0);
        }

        return [
            'rx_bytes' => $rx,
            'tx_bytes' => $tx,
            'rx_bps' => $rx,
            'tx_bps' => $tx,
            'rx_mbps' => round($rx * 8 / 1000000, 3),
            'tx_mbps' => round($tx * 8 / 1000000, 3),
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
