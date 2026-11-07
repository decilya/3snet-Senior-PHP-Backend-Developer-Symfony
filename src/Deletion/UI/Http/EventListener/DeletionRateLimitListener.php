<?php

declare(strict_types=1);

namespace App\Deletion\UI\Http\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactory;

/**
 * Проверяет rate limit ДО валидации payload (kernel.controller_arguments).
 *
 * Гарантирует, что невалидные запросы тоже расходуют бюджет,
 * предотвращая флуд мусорными данными.
 */
#[AsEventListener(event: 'kernel.controller', priority: 100)]
final readonly class DeletionRateLimitListener
{
    /**
     * @param RateLimiterFactory $deletionLimitLimiter Фабрика лимитера для эндпоинта удаления
     */
    public function __construct(
        private RateLimiterFactory $deletionLimitLimiter,
    ) {
    }

    /**
     * @param ControllerEvent $event Событие контроллера
     *
     * @return void
     *
     * @throws TooManyRequestsHttpException Если лимит запросов исчерпан
     */
    public function __invoke(ControllerEvent $event): void
    {
        $request = $event->getRequest();

        if ('api_deletion_execute' !== $request->attributes->get('_route')) {
            return;
        }

        $limiter = $this->deletionLimitLimiter->create($request->getClientIp() ?? 'unknown');
        $limit   = $limiter->consume(1);

        if (!$limit->isAccepted()) {
            $retryAfter = max(1, $limit->getRetryAfter()->getTimestamp() - time());

            throw new TooManyRequestsHttpException(
                retryAfter: $retryAfter,
                message: 'Превышен лимит запросов на удаление (максимум 5 в минуту).',
            );
        }
    }
}
