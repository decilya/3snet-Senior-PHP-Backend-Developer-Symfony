<?php

declare(strict_types=1);

namespace App\Deletion\UI\Http\Controller;

use App\Deletion\Application\Command\DeleteEntityCommand;
use App\Deletion\Application\Service\DeleteEntityUseCase;
use App\Deletion\Domain\Exception\CyclicDependencyException;
use App\Deletion\Domain\Exception\DeletionBlockedException;
use App\Deletion\UI\Http\Request\DeleteEntityRequest;
use Doctrine\Persistence\Mapping\MappingException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

/**
 * HTTP-контроллер для управления удалением сущностей.
 *
 * Вся логика делегируется в Application слой (DeleteEntityUseCase).
 * Ошибки возвращаются в формате RFC 7807 (Problem Details).
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

        } catch (DeletionBlockedException | CyclicDependencyException $e) {
            // Доменные конфликты: блокировка удаления или цикл в графе
            return $this->problem(
                title:  'Deletion Conflict',
                detail: $e->getMessage(),
                status: Response::HTTP_CONFLICT,
            );
        } catch (MappingException $e) {
            // Класс существует в PHP, но не является Doctrine-сущностью
            return $this->problem(
                title:  'Unprocessable Entity',
                detail: sprintf(
                    'Класс "%s" не является Doctrine-сущностью.',
                    $request->entityClass
                ),
                status: Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }
    }

    /**
     * Формирует ответ в формате RFC 7807 (Problem Details for HTTP APIs).
     *
     * @param string $title  Краткое описание класса ошибки
     * @param string $detail Подробное описание конкретной ситуации
     * @param int    $status HTTP-статус
     *
     * @return JsonResponse
     */
    private function problem(string $title, string $detail, int $status): JsonResponse
    {
        return new JsonResponse([
            'type'   => 'about:blank',
            'title'  => $title,
            'status' => $status,
            'detail' => $detail,
        ], $status);
    }
}
