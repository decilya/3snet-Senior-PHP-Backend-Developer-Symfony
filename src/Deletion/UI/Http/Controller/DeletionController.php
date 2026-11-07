<?php

declare(strict_types=1);

namespace App\Deletion\UI\Http\Controller;

use App\Deletion\Application\Command\DeleteEntityCommand;
use App\Deletion\Application\Service\DeleteEntityUseCase;
use App\Deletion\UI\Http\Request\DeleteEntityRequest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * HTTP-контроллер для управления удалением сущностей.
 *
 * Rate limit вынесен в DeletionRateLimitListener (kernel.controller).
 * Ошибки перехватываются DeletionExceptionListener.
 */
#[Route('/api/v1/deletion', name: 'api_deletion_')]
final readonly class DeletionController
{
    /**
     * @param DeleteEntityUseCase $useCase Сервис выполнения операции удаления
     */
    public function __construct(
        private DeleteEntityUseCase $useCase,
    ) {
    }

    /**
     * Запускает процесс удаления сущностей.
     *
     * @param DeleteEntityRequest $request Валидированный DTO запроса
     *
     * @return JsonResponse
     */
    #[Route('', name: 'execute', methods: ['POST'])]
    #[IsGranted('ROLE_API_USER')]
    public function __invoke(
        #[MapRequestPayload] DeleteEntityRequest $request,
    ): JsonResponse {
        $result = $this->useCase->execute(new DeleteEntityCommand(
            entityClass: $request->entityClass,
            ids:         $request->ids,
            force:       $request->force,
        ));

        return new JsonResponse([
            'status'  => 'success',
            'message' => 'Операция удаления выполнена успешно.',
            'data'    => [
                'stats' => $result->stats,
                'total' => $result->totalAffected(),
            ],
        ], Response::HTTP_OK);
    }
}
