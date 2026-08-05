<?php

declare(strict_types=1);

namespace Shared\Deletion\Metrics;

interface MetricsRecorderInterface
{
    /**
     * @param array<string, string> $labels
     */
    public function incrementCounter(string $name, array $labels = [], int $value = 1): void;

    /**
     * @param array<string, string> $labels
     */
    public function observeHistogram(string $name, float|int $value, array $labels = []): void;

    /**
     * @param array<string, string> $labels
     */
    public function incrementGauge(string $name, array $labels = []): void;

    /**
     * @param array<string, string> $labels
     */
    public function decrementGauge(string $name, array $labels = []): void;
}