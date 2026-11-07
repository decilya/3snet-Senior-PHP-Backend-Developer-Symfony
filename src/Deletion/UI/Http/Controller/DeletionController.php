<?php

declare(strict_types=1);

namespace App\Deletion\UI\Http\Controller;

use App\Deletion\Application\Command\DeleteEntityCommand;
use App\Deletion\Application\Service\DeleteEntityUseCase;
use App\Deletion\UI\Http\Request\DeleteEntityRequest;
use App\Deletion\UI\Http\Response\ProblemDetailsFactory;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * HTTP-контроллер для управления удалением сущностей.
 *
 * Вся логика делегируется в Application слой (DeleteEntityUseCase).
 * Ошибки перехватываются глобальным DeletionExceptionListener.
 */
#[Route('/api/v1/deletion', name: 'api_deletion_')]
final readonly class DeletionController
{
    /**
     * @param DeleteEntityUseCase $useCase
     * @param RateLimiterFactory $deletionLimitLimiter
     * @param ProblemDetailsFactory $problemDetails
     */
    public function __construct(
        private DeleteEntityUseCase $useCase,
        private RateLimiterFactory $deletionLimitLimiter,
        private ProblemDetailsFactory $problemDetails,
    ) {
    }

    /**
     * @param DeleteEntityRequest $request
     * @param Request $httpRequest
     * @return JsonResponse
     */
    #[Route('', name: 'execute', methods: ['POST'])]
    #[IsGranted('ROLE_API_USER')]
    public function __invoke(
        #[MapRequestPayload] DeleteEntityRequest $request,
        Request $httpRequest,
    ): JsonResponse {
        // Rate Limit по IP (null-safe)
        $clientIp = $httpRequest->getClientIp() ?? 'unknown';
        $limiter  = $this->deletionLimitLimiter->create($clientIp);

        if (!$limiter->consume(1)->isAccepted()) {
            return $this->problemDetails->create(
                status: Response::HTTP_TOO_MANY_REQUESTS,
                title:  'Too Many Requests',
                detail: 'Превышен лимит запросов на удаление (максимум 5 в минуту).',
            );
        }

        // Выполняем операцию
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
