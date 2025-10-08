<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

use ServerMonitoring\MonitorService;
use ServerMonitoring\MetricsProvider;

final class MonitorServiceTest extends TestCase
{
    public function testCollectUsesProviderData(): void
    {
        // Create a simple anonymous provider for testing
        $provider = new class implements MetricsProvider {
            public function cpu(): array { return ['cores' => 2, 'load' => 0.2, 'percentage' => 20.0]; }
            public function ram(): array { return ['total' => 1024, 'used' => 512, 'free' => 512, 'percentage' => 50.0]; }
            public function disk(): array { return ['total' => 10, 'used' => 5, 'free' => 5, 'percentage' => 50]; }
            public function network(): array { return ['rx_bytes' => 1000, 'tx_bytes' => 500, 'rx_mbps' => 0.008, 'tx_mbps' => 0.004]; }
            public function serverInfo(): array { return ['php_version' => 'test', 'server_software' => 'test']; }
        };

        $service = new MonitorService($provider);
        $result = $service->collect();

        $this->assertArrayHasKey('cpu', $result);
        $this->assertArrayHasKey('ram', $result);
        $this->assertArrayHasKey('disk', $result);
        $this->assertArrayHasKey('network', $result);
        $this->assertArrayHasKey('server', $result);

        $this->assertSame(2, $result['cpu']['cores']);
        $this->assertSame(1024, $result['ram']['total']);
        $this->assertSame(10, $result['disk']['total']);
        $this->assertSame(1000, $result['network']['rx_bytes']);
    }
}
