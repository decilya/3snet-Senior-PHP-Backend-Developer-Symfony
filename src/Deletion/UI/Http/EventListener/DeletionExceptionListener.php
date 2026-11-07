<?php

declare(strict_types=1);

namespace App\Deletion\UI\Http\EventListener;

use App\Deletion\Domain\Exception\CyclicDependencyException;
use App\Deletion\Domain\Exception\DeletionBlockedException;
use App\Deletion\UI\Http\Response\ProblemDetailsFactory;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\Persistence\Mapping\MappingException;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Глобальный обработчик исключений для API удаления.
 *
 * Перехватывает известные ошибки на уровне ядра Symfony и возвращает их
 * в формате RFC 7807 (Problem Details).
 */
#[AsEventListener(event: KernelEvents::EXCEPTION)]
final readonly class DeletionExceptionListener
{
    /**
     * @param ProblemDetailsFactory $problemDetails Фабрика для создания RFC 7807 ответов
     */
    public function __construct(
        private ProblemDetailsFactory $problemDetails,
    ) {
    }

    /**
     * @param ExceptionEvent $event Событие исключения ядра Symfony
     *
     * @return void
     */
    public function __invoke(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        $request   = $event->getRequest();

        // Реагируем только на запросы к нашему API
        if (!str_starts_with($request->getPathInfo(), '/api/v1/deletion')) {
            return;
        }

        $response = match (true) {
            // Rate limit exceeded (thrown by DeletionRateLimitListener)
            $exception instanceof TooManyRequestsHttpException => $this->problemDetails->create(
                status: Response::HTTP_TOO_MANY_REQUESTS,
                title: 'Too Many Requests',
                detail: $exception->getMessage() ?: 'Rate limit exceeded.',
            ),

            // Ошибка валидации DTO (выбрасывается #[MapRequestPayload])
            $exception instanceof UnprocessableEntityHttpException => $this->problemDetails->create(
                status: Response::HTTP_UNPROCESSABLE_ENTITY,
                title: 'Validation Failed',
                detail: $exception->getMessage(),
            ),

            // Доменные конфликты: блокировка или цикл
            $exception instanceof DeletionBlockedException,
                $exception instanceof CyclicDependencyException => $this->problemDetails->create(
                status: Response::HTTP_CONFLICT,
                title: 'Deletion Conflict',
                detail: $exception->getMessage(),
            ),

            // Ошибка БД: нарушение FK
            $exception instanceof ForeignKeyConstraintViolationException => $this->problemDetails->create(
                status: Response::HTTP_CONFLICT,
                title: 'Foreign Key Violation',
                detail: 'Невозможно удалить сущность: она связана с другими записями (FK constraint).',
            ),

            // Класс не найден в метаданных Doctrine
            $exception instanceof MappingException => $this->problemDetails->create(
                status: Response::HTTP_UNPROCESSABLE_ENTITY,
                title: 'Unprocessable Entity',
                detail: 'Переданный класс не является Doctrine-сущностью.',
            ),

            // Неизвестная ошибка -> пусть Symfony обрабатывает как 500
            default => null,
        };

        if ($response !== null) {
            // Пробрасываем заголовки из исключения (например, Retry-After для 429)
            if (method_exists($exception, 'getHeaders') && $exception->getHeaders()) {
                $response->headers->add($exception->getHeaders());
            }

            $event->setResponse($response);
        }
    }
}
