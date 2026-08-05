<?php

declare(strict_types=1);

namespace Shared\Deletion\Metrics;

use Psr\Log\LoggerInterface;

final class LogMetricsRecorder implements MetricsRecorderInterface
{
    public function __construct(private readonly LoggerInterface $logger) {}

    public function incrementCounter(string $name, array $labels = [], int $value = 1): void
    {
        if ($value <= 0) return;
        $this->logger->info("Counter $name +$value", ['labels' => $labels]);
    }

    public function observeHistogram(string $name, float|int $value, array $labels = []): void
    {
        $this->logger->info("Histogram $name = $value", ['labels' => $labels]);
    }

    public function incrementGauge(string $name, array $labels = []): void
    {
        $this->logger->info("Gauge $name +1", ['labels' => $labels]);
    }

    public function decrementGauge(string $name, array $labels = []): void
    {
        $this->logger->info("Gauge $name -1", ['labels' => $labels]);
    }
}