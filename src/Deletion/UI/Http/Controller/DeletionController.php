<?php

declare(strict_types=1);

namespace App\Deletion\UI\Http\Controller;

use App\Deletion\Application\Command\DeleteEntityCommand;
use App\Deletion\Application\Service\DeleteEntityUseCase;
use App\Deletion\Domain\Exception\DeletionBlockedException;
use App\Deletion\UI\Http\Request\DeleteEntityRequest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

/**
 * HTTP-контроллер для управления удалением сущностей.
 *
 * Вся логика делегируется в Application слой (DeleteEntityUseCase).
 * Контроллер отвечает только за прием запроса и формирование ответа.
 */
#[Route('/api/v1/deletion', name: 'api_deletion_')]
final readonly class DeletionController
{
    public function __construct(
        private DeleteEntityUseCase $useCase,
    ) {
    }

    /**
     * Запускает процесс удаления сущностей.
     *
     * @param DeleteEntityRequest $request Валидированный DTO запроса
     *
     * @throws DeletionBlockedException Если удаление заблокировано политикой
     *
     * @return JsonResponse
     */
    #[Route('', name: 'execute', methods: ['POST'])]
    public function __invoke(
        #[MapRequestPayload] DeleteEntityRequest $request
    ): JsonResponse {
        try {
            $command = new DeleteEntityCommand(
                entityClass: $request->entityClass,
                ids:         $request->ids,
                force:       $request->force,
            );

            $result = $this->useCase->execute($command);

            return new JsonResponse([
                'status'  => 'success',
                'message' => 'Операция удаления выполнена успешно.',
                'data'    => [
                    'stats' => $result->stats,
                    'total' => $result->totalAffected(),
                ],
            ], Response::HTTP_OK);

        } catch (DeletionBlockedException $e) {
            // Возвращаем 409 Conflict, если сработала блокировка
            return new JsonResponse([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], Response::HTTP_CONFLICT);
        }
    }
}
