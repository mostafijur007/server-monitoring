<?php
declare(strict_types=1);

namespace ServerMonitoring;

final class MonitorService
{
    private MetricsProvider $provider;

    public function __construct(MetricsProvider $provider)
    {
        $this->provider = $provider;
    }

    public function collect(): array
    {
        $data = [
            'time' => date('H:i:s'),
            'os' => PHP_OS . ' (' . php_uname() . ')',
        ];

        try {
            $data['cpu'] = $this->provider->cpu();
        } catch (\Throwable $t) {
            $data['cpu'] = ['error' => $t->getMessage()];
        }

        try {
            $data['ram'] = $this->provider->ram();
        } catch (\Throwable $t) {
            $data['ram'] = ['error' => $t->getMessage()];
        }

        try {
            $data['disk'] = $this->provider->disk();
        } catch (\Throwable $t) {
            $data['disk'] = ['error' => $t->getMessage()];
        }

        try {
            $data['network'] = $this->provider->network();
        } catch (\Throwable $t) {
            $data['network'] = ['error' => $t->getMessage()];
        }

        $data['server'] = $this->provider->serverInfo();

        return $data;
    }
}
