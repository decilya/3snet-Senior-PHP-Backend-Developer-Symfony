<?php

declare(strict_types=1);

namespace Shared\Deletion\Service;

use Shared\Deletion\Dto\{OrderedPlanDto, RelationsDto};
use Shared\Deletion\Middleware\DeletionMiddlewareInterface;
use Throwable;

final class DeletionOrchestratorWrapper
{
    /**
     * @param DeletionOrchestrator                  $inner   оригинальный оркестратор (final)
     * @param iterable<DeletionMiddlewareInterface> $middlewares
     */
    public function __construct(
        private readonly DeletionOrchestrator $inner,
        private readonly iterable $middlewares
    ) {
    }

    public function execute(object $root, bool $dryRun = false): void
    {
        try {
            $this->inner->execute($root, $dryRun);
        } catch (Throwable $e) {
            $this->notifyError($root, $e);
            throw $e;
        }
    }

    public function plan(object $root): RelationsDto
    {
        return $this->inner->plan($root);
    }

    public function getOrderedPlan(object $root): OrderedPlanDto
    {
        return $this->inner->getOrderedPlan($root);
    }

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