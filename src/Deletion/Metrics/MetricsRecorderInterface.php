<?php

declare(strict_types=1);

namespace Shared\Deletion\Metrics;

/**
 * Интерфейс рекордера метрик для сбора статистики по операциям удаления.
 *
 * Предоставляет унифицированный API для различных систем мониторинга:
 * - логирование (LogMetricsRecorder)
 * - Prometheus (PrometheusMetricsRecorder)
 * - StatsD, OpenTelemetry и др.
 *
 * Все метрики поддерживают лейблы (labels) для детализации по классам сущностей,
 * типам ошибок и другим параметрам.
 */
interface MetricsRecorderInterface
{
    /**
     * Увеличивает счётчик на указанное значение.
     *
     * Счётчики используются для подсчёта количества событий:
     * - количество запусков удаления
     * - количество завершённых операций
     * - количество ошибок
     * - количество удалённых сущностей
     *
     * @param string $name имя метрики (например, 'deletion_root_started_total')
     * @param array<string, string> $labels ассоциативный массив лейблов для группировки
     *                                      (например, ['root_class' => 'OrderEntity'])
     * @param int $value значение, на которое увеличить счётчик (по умолчанию 1)
     */
    public function incrementCounter(string $name, array $labels = [], int $value = 1): void;

    /**
     * Записывает наблюдение в гистограмму для анализа распределения значений.
     *
     * Гистограммы используются для измерения:
     * - длительности операций (в секундах)
     * - размеров batch'ей (количество записей)
     *
     * Значения автоматически распределяются по корзинам (buckets) для расчёта
     * квантилей (p50, p95, p99) и средних значений.
     *
     * @param string $name имя метрики (например, 'deletion_root_duration_seconds')
     * @param float|int $value наблюдаемое значение (длительность, размер и т.д.)
     * @param array<string, string> $labels ассоциативный массив лейблов
     */
    public function observeHistogram(string $name, float|int $value, array $labels = []): void;

    /**
     * Увеличивает калибр (gauge) на 1.
     *
     * Gauges используются для отслеживания текущих значений,
     * которые могут увеличиваться и уменьшаться:
     * - количество одновременно выполняющихся операций (in-progress)
     *
     * Важно: каждый вызов incrementGauge должен быть сбалансирован
     * соответствующим decrementGauge, чтобы избежать утечек.
     *
     * @param string $name имя метрики (например, 'deletion_root_in_progress')
     * @param array<string, string> $labels ассоциативный массив лейблов
     */
    public function incrementGauge(string $name, array $labels = []): void;

    /**
     * Уменьшает калибр (gauge) на 1.
     *
     * Декрементирует gauge, обычно вызывается после завершения операции
     * или при возникновении ошибки для корректного закрытия фазы.
     *
     * @param string $name имя метрики
     * @param array<string, string> $labels ассоциативный массив лейблов
     */
    public function decrementGauge(string $name, array $labels = []): void;
}