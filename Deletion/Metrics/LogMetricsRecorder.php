<?php

declare(strict_types=1);

namespace Shared\Deletion\Metrics;

use Psr\Log\LoggerInterface;

/**
 * Реализация рекордера метрик через PSR-3 логгер.
 *
 * Записывает все метрики в лог с уровнем INFO. Подходит для разработки,
 * локального тестирования и начальных этапов внедрения мониторинга.
 *
 * В production рекомендуется заменить на адаптер для Prometheus,
 * StatsD или другой системы агрегации метрик.
 *
 * @see MetricsRecorderInterface
 */
final class LogMetricsRecorder implements MetricsRecorderInterface
{
    /**
     * @param LoggerInterface $logger PSR-3 логгер
     */
    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    /**
     * {@inheritdoc}
     *
     * Логирует увеличение счётчика с указанием значения и лейблов.
     * Если значение <= 0, запись не производится.
     */
    public function incrementCounter(string $name, array $labels = [], int $value = 1): void
    {
        if ($value <= 0) {
            return;
        }
        $this->logger->info("Counter $name +$value", ['labels' => $labels]);
    }

    /**
     * {@inheritdoc}
     *
     * Логирует наблюдение гистограммы с указанием значения и лейблов.
     */
    public function observeHistogram(string $name, float|int $value, array $labels = []): void
    {
        $this->logger->info("Histogram $name = $value", ['labels' => $labels]);
    }

    /**
     * {@inheritdoc}
     *
     * Логирует увеличение калибра (gauge) на 1.
     */
    public function incrementGauge(string $name, array $labels = []): void
    {
        $this->logger->info("Gauge $name +1", ['labels' => $labels]);
    }

    /**
     * {@inheritdoc}
     *
     * Логирует уменьшение калибра (gauge) на 1.
     */
    public function decrementGauge(string $name, array $labels = []): void
    {
        $this->logger->info("Gauge $name -1", ['labels' => $labels]);
    }
}