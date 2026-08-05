<?php

declare(strict_types=1);

namespace Shared\Deletion\Middleware;

use Shared\Deletion\Metrics\MetricsRecorderInterface;
use Throwable;
use WeakMap;

/**
 * Middleware для сбора метрик по операции удаления.
 *
 * Собирает:
 * - счётчики (запуски, завершения, ошибки, удалённые дети, отсоединённые связи)
 * - гистограммы (длительность удаления корня, размер batch'а детей)
 * - in-progress gauges (количество активных операций, помогает выявить зависшие удаления)
 *
 * Использует WeakMap для хранения таймингов, чтобы избежать утечек памяти в long-running процессах.
 */
final class MetricsDeletionMiddleware implements DeletionMiddlewareInterface
{
    private const PHASE_ROOT = 'root';

    /**
     * @var WeakMap<object, array{phases: array<string, array{start: float, gauge: string, labels: array<string, string>}>}>
     */
    private WeakMap $timings;

    /**
     * @param MetricsRecorderInterface $metrics           рекордер метрик (лог, Prometheus и т.д.)
     * @param list<class-string>       $supportedClasses  опционально – только для этих классов собирать метрики
     */
    public function __construct(
        private readonly MetricsRecorderInterface $metrics,
        private readonly array $supportedClasses = [],
    ) {
        $this->timings = new WeakMap();
    }

    /**
     * {@inheritdoc}
     */
    public function supports(string $entityClass): bool
    {
        if ($this->supportedClasses === []) {
            return true;
        }

        foreach ($this->supportedClasses as $supportedClass) {
            if (is_a($entityClass, $supportedClass, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function beforeDeleteRoot(object $root): void
    {
        if (!$this->supports($root::class)) {
            return;
        }

        $labels = ['root_class' => $this->classLabel($root::class)];

        $this->metrics->incrementCounter('deletion_root_started_total', $labels);
        $this->start($root, self::PHASE_ROOT, 'deletion_root_in_progress', $labels);
    }

    /**
     * {@inheritdoc}
     */
    public function afterDeleteRoot(object $root): void
    {
        if (!$this->supports($root::class)) {
            return;
        }

        $labels = ['root_class' => $this->classLabel($root::class)];
        $duration = $this->stop($root, self::PHASE_ROOT);

        // Декремент gauge выполняется только если фаза действительно была открыта
        if ($duration !== null) {
            $this->metrics->decrementGauge('deletion_root_in_progress', $labels);
            $this->metrics->observeHistogram('deletion_root_duration_seconds', $duration, $labels);
        }

        $this->metrics->incrementCounter('deletion_root_completed_total', $labels);
    }

    /**
     * {@inheritdoc}
     */
    public function beforeDeleteChildren(string $childClass, array $childIds, object $root): void
    {
        if (!$this->supports($root::class)) {
            return;
        }

        $labels = [
            'root_class' => $this->classLabel($root::class),
            'child_class' => $this->classLabel($childClass),
        ];

        $this->metrics->incrementCounter('delete_children_started_total', $labels);
        $this->start($root, 'delete_children_in_progress', 'delete_children_in_progress', $labels);
    }

    /**
     * {@inheritdoc}
     */
    public function afterDeleteChildren(string $childClass, array $childIds, object $root): void
    {
        if (!$this->supports($root::class)) {
            return;
        }

        $labels = [
            'root_class' => $this->classLabel($root::class),
            'child_class' => $this->classLabel($childClass),
        ];

        $duration = $this->stop($root, 'delete_children_in_progress');

        // Декремент gauge только если фаза была открыта
        if ($duration !== null) {
            $this->metrics->decrementGauge('delete_children_in_progress', $labels);
        }

        $this->metrics->incrementCounter('delete_children_completed_total', $labels);
        $this->metrics->observeHistogram('delete_children_batch_size', count($childIds), $labels);
        $this->metrics->incrementCounter('deleted_child_entities_total', $labels, count($childIds));
    }

    /**
     * {@inheritdoc}
     */
    public function beforeDetachRelations(string $parentClass, string $childClass, array $childIds, array $relation, object $root): void
    {
        // не используется для сбора метрик, можно добавить при необходимости
    }

    /**
     * {@inheritdoc}
     */
    public function afterDetachRelations(string $parentClass, string $childClass, array $childIds, array $relation, object $root): void
    {
        if (!$this->supports($root::class)) {
            return;
        }

        $labels = [
            'parent_class' => $this->classLabel($parentClass),
            'child_class' => $this->classLabel($childClass),
        ];

        $this->metrics->incrementCounter('detach_relations_rows_total', $labels, count($childIds));
    }

    /**
     * Кастомный метод для обработки ошибок (вызывается из декоратора).
     *
     * При возникновении ошибки:
     * - увеличивает счётчик ошибок
     * - закрывает все активные in-progress gauges, чтобы они не "зависли"
     *
     * @param object    $root      корневая сущность, операция над которой была прервана
     * @param Throwable $exception возникшее исключение
     */
    public function onError(object $root, Throwable $exception): void
    {
        if (!$this->supports($root::class)) {
            return;
        }

        $labels = [
            'root_class' => $this->classLabel($root::class),
            'exception_class' => $this->classLabel($exception::class),
        ];

        $this->metrics->incrementCounter('deletion_root_error_total', $labels);

        // Закрываем все активные фазы, чтобы gauges не "зависли" и не ушли в минус
        $state = $this->timings[$root] ?? null;
        if ($state !== null) {
            foreach ($state['phases'] as $phase) {
                $this->metrics->decrementGauge($phase['gauge'], $phase['labels']);
            }
            unset($this->timings[$root]);
        }
    }

    /**
     * Сохраняет время начала фазы и название gauge для последующего закрытия в onError.
     *
     * @param object                   $root
     * @param string                   $key   уникальный идентификатор фазы
     * @param string                   $gauge имя gauge-метрики
     * @param array<string, string>    $labels лейблы для gauge
     */
    private function start(object $root, string $key, string $gauge, array $labels): void
    {
        $state = $this->timings[$root] ?? ['phases' => []];
        $state['phases'][$key] = [
            'start'  => microtime(true),
            'gauge'  => $gauge,
            'labels' => $labels,
        ];
        $this->timings[$root] = $state;
    }

    /**
     * Завершает фазу и возвращает длительность, если фаза была активна.
     * Удаляет фазу из WeakMap, но не уменьшает gauge – это делается в after‑методах.
     *
     * @param object $root
     * @param string $key
     *
     * @return float|null длительность в секундах или null, если фаза не была открыта
     */
    private function stop(object $root, string $key): ?float
    {
        $state = $this->timings[$root] ?? null;
        if ($state === null || !isset($state['phases'][$key])) {
            return null;
        }

        $phase = $state['phases'][$key];
        $duration = microtime(true) - $phase['start'];

        unset($state['phases'][$key]);

        if ($state['phases'] === []) {
            unset($this->timings[$root]);
        } else {
            $this->timings[$root] = $state;
        }

        return $duration;
    }

    /**
     * Преобразует FQCN в безопасный для метрик строковый идентификатор.
     *
     * @param string $class полное имя класса
     *
     * @return string заменяет обратные слэши на подчёркивания
     */
    private function classLabel(string $class): string
    {
        return str_replace('\\', '_', ltrim($class, '\\'));
    }
}