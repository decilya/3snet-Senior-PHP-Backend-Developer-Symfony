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
    public function __construct(
        private ProblemDetailsFactory $problemDetails,
    ) {
    }

    /**
     * @param ExceptionEvent $event
     *
     * @return void
     */
    public function __invoke(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        $request = $event->getRequest();

        // Реагируем только на запросы к нашему API
        if (!str_starts_with($request->getPathInfo(), '/api/v1/deletion')) {
            return;
        }

        $response = match (true) {
            // Ошибка валидации DTO (выбрасывается #[MapRequestPayload])
            $exception instanceof UnprocessableEntityHttpException => $this->problemDetails->create(
                title: 'Validation Failed',
                detail: $exception->getMessage(),
                status: Response::HTTP_UNPROCESSABLE_ENTITY,
            ),

            // Доменные конфликты: блокировка или цикл
            $exception instanceof DeletionBlockedException,
                $exception instanceof CyclicDependencyException => $this->problemDetails->create(
                title: 'Deletion Conflict',
                detail: $exception->getMessage(),
                status: Response::HTTP_CONFLICT,
            ),

            // Ошибка БД: нарушение FK
            $exception instanceof ForeignKeyConstraintViolationException => $this->problemDetails->create(
                title: 'Foreign Key Violation',
                detail: 'Невозможно удалить сущность: она связана с другими записями (FK constraint).',
                status: Response::HTTP_CONFLICT,
            ),

            // Класс не найден в метаданных Doctrine
            $exception instanceof MappingException => $this->problemDetails->create(
                title: 'Unprocessable Entity',
                detail: 'Переданный класс не является Doctrine-сущностью.',
                status: Response::HTTP_UNPROCESSABLE_ENTITY,
            ),

            // Неизвестная ошибка -> пусть Symfony обрабатывает как 500
            default => null,
        };

        if ($response !== null) {
            $event->setResponse($response);
        }
    }
}
