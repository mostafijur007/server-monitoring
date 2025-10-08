<?php
declare(strict_types=1);

interface MetricsProvider
{
    public function cpu(): array;
    public function ram(): array;
    public function disk(): array;
    public function network(): array;
    public function serverInfo(): array;
}
