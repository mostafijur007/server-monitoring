<?php
declare(strict_types=1);

// Bootstrap - keep this file small and require implementations from src/
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/src/MetricsProvider.php';
require_once __DIR__ . '/src/MonitorService.php';

$isWindows = strncasecmp(PHP_OS, 'WIN', 3) === 0;
if ($isWindows) {
    require_once __DIR__ . '/src/WindowsMetricsProvider.php';
    $provider = new WindowsMetricsProvider();
} else {
    require_once __DIR__ . '/src/UnixMetricsProvider.php';
    $provider = new UnixMetricsProvider();
}

$monitor = new MonitorService($provider);
try {
    echo json_encode($monitor->collect(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    try {
        echo json_encode([
            'error' => true,
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'os' => PHP_OS,
            'time' => date('H:i:s'),
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    } catch (Throwable $_) {
        echo '{"error":true,"message":"Unexpected error"}';
    }
}
