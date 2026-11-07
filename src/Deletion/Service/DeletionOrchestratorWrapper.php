<?php

declare(strict_types=1);

namespace Shared\Deletion\Service;

use Shared\Deletion\Dto\{OrderedPlanDto, RelationsDto};
use Shared\Deletion\Middleware\DeletionMiddlewareInterface;
use Throwable;

/**
 * Обёртка над оригинальным DeletionOrchestrator для перехвата ошибок
 * и уведомления middleware через метод onError().
 *
 * Используется для обхода ограничения final-класса DeletionOrchestrator.
 * Реализует тот же интерфейс DeletionOrchestratorInterface и делегирует
 * все вызовы внутреннему объекту, перехватывая исключения в execute().
 */
final class DeletionOrchestratorWrapper implements DeletionOrchestratorInterface
{
    /**
     * @param DeletionOrchestrator $inner оригинальный оркестратор (final)
     * @param iterable<DeletionMiddlewareInterface> $middlewares коллекция middleware для уведомлений об ошибках
     */
    public function __construct(
        private readonly DeletionOrchestrator $inner,
        private readonly iterable             $middlewares
    )
    {
    }

    /**
     * {@inheritdoc}
     *
     * Перехватывает исключения, уведомляет middleware через onError()
     * и пробрасывает исключение дальше.
     */
    public function execute(object $root, bool $dryRun = false): void
    {
        try {
            $this->inner->execute($root, $dryRun);
        } catch (Throwable $e) {
            $this->notifyError($root, $e);
            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     *
     * Делегирует вызов внутреннему оркестратору.
     */
    public function plan(object $root): RelationsDto
    {
        return $this->inner->plan($root);
    }

    /**
     * {@inheritdoc}
     *
     * Делегирует вызов внутреннему оркестратору.
     */
    public function getOrderedPlan(object $root): OrderedPlanDto
    {
        return $this->inner->getOrderedPlan($root);
    }

    /**
     * Уведомляет все middleware, поддерживающие метод onError, о возникшем исключении.
     *
     * @param object $root корневая сущность, операция над которой была прервана
     * @param Throwable $exception возникшее исключение
     */
    private function notifyError(object $root, Throwable $exception): void
    {
        foreach ($this->middlewares as $mw) {
            if (method_exists($mw, 'onError')) {
                try {
                    $mw->onError($root, $exception);
                } catch (Throwable) {
                    // ошибки в middleware не должны прерывать основной поток
                }
            }
        }
    }
}