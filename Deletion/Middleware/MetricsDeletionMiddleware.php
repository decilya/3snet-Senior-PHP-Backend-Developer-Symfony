<?php

declare(strict_types=1);

namespace Shared\Deletion\Middleware;

use Shared\Deletion\Metrics\MetricsRecorderInterface;
use Throwable;
use WeakMap;

final class MetricsDeletionMiddleware implements DeletionMiddlewareInterface
{
    private const PHASE_ROOT = 'root';

    /** @var WeakMap<object, array{phases: array<string, float>}> */
    private WeakMap $timings;

    public function __construct(
        private readonly MetricsRecorderInterface $metrics,
        /** @var list<class-string> $supportedClasses */
        private readonly array $supportedClasses = [],
    ) {
        $this->timings = new WeakMap();
    }

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

    public function beforeDeleteRoot(object $root): void
    {
        if (!$this->supports($root::class)) return;

        $labels = ['root_class' => $this->classLabel($root::class)];
        $this->metrics->incrementCounter('deletion_root_started_total', $labels);
        $this->metrics->incrementGauge('deletion_root_in_progress', $labels);
        $this->start($root, self::PHASE_ROOT);
    }

    public function afterDeleteRoot(object $root): void
    {
        if (!$this->supports($root::class)) return;

        $labels = ['root_class' => $this->classLabel($root::class)];
        $duration = $this->stop($root, self::PHASE_ROOT);

        $this->metrics->decrementGauge('deletion_root_in_progress', $labels);
        $this->metrics->incrementCounter('deletion_root_completed_total', $labels);

        if ($duration !== null) {
            $this->metrics->observeHistogram('deletion_root_duration_seconds', $duration, $labels);
        }
    }

    public function beforeDeleteChildren(string $childClass, array $childIds, object $root): void
    {
        if (!$this->supports($root::class)) return;

        $labels = [
            'root_class' => $this->classLabel($root::class),
            'child_class' => $this->classLabel($childClass),
        ];
        $this->metrics->incrementCounter('delete_children_started_total', $labels);
        $this->metrics->incrementGauge('delete_children_in_progress', $labels);
    }

    public function afterDeleteChildren(string $childClass, array $childIds, object $root): void
    {
        if (!$this->supports($root::class)) return;

        $labels = [
            'root_class' => $this->classLabel($root::class),
            'child_class' => $this->classLabel($childClass),
        ];
        $this->metrics->decrementGauge('delete_children_in_progress', $labels);
        $this->metrics->incrementCounter('delete_children_completed_total', $labels);
        $this->metrics->observeHistogram('delete_children_batch_size', count($childIds), $labels);
        $this->metrics->incrementCounter('deleted_child_entities_total', $labels, count($childIds));
    }

    public function beforeDetachRelations(string $parentClass, string $childClass, array $childIds, array $relation, object $root): void
    {
        // не используется
    }

    public function afterDetachRelations(string $parentClass, string $childClass, array $childIds, array $relation, object $root): void
    {
        if (!$this->supports($root::class)) return;

        $labels = [
            'parent_class' => $this->classLabel($parentClass),
            'child_class' => $this->classLabel($childClass),
        ];
        $this->metrics->incrementCounter('detach_relations_rows_total', $labels, count($childIds));
    }

    /**
     * Кастомный метод, вызываемый из обёртки при ошибке.
     */
    public function onError(object $root, Throwable $exception): void
    {
        if (!$this->supports($root::class)) return;

        $labels = [
            'root_class' => $this->classLabel($root::class),
            'exception_class' => $this->classLabel($exception::class),
        ];
        $this->metrics->incrementCounter('deletion_root_error_total', $labels);
        $this->metrics->decrementGauge('deletion_root_in_progress', [
            'root_class' => $this->classLabel($root::class),
        ]);
    }

    private function start(object $root, string $key): void
    {
        $state = $this->timings[$root] ?? ['phases' => []];
        $state['phases'][$key] = microtime(true);
        $this->timings[$root] = $state;
    }

    private function stop(object $root, string $key): ?float
    {
        $state = $this->timings[$root] ?? null;
        if ($state === null || !isset($state['phases'][$key])) {
            return null;
        }

        $duration = microtime(true) - $state['phases'][$key];
        unset($state['phases'][$key]);

        if ($state['phases'] === []) {
            unset($this->timings[$root]);
        } else {
            $this->timings[$root] = $state;
        }

        return $duration;
    }

    private function classLabel(string $class): string
    {
        return str_replace('\\', '_', ltrim($class, '\\'));
    }
}