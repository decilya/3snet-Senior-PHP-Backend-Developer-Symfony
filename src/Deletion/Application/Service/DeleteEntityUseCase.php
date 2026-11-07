<?php

declare(strict_types=1);

namespace App\Deletion\Application\Service;

use App\Deletion\Application\Command\DeleteEntityCommand;
use App\Deletion\Application\Port\TransactionManagerInterface;
use App\Deletion\Application\Result\DeletionResult;
use App\Deletion\Domain\Exception\DeletionBlockedException;
use App\Deletion\Domain\Model\DeletionPlan;
use App\Deletion\Domain\Repository\DeletionEntityRepositoryInterface;
use App\Deletion\Domain\Repository\RelationMetadataRepositoryInterface;
use App\Deletion\Domain\Service\DeletionPlanner;
use App\Deletion\Domain\ValueObject\DeleteAction;
use App\Deletion\Domain\ValueObject\EntityClass;
use LogicException;

/**
 * Сценарий использования: удаление сущности с каскадной обработкой связей.
 *
 * Оркестрирует доменные сервисы, репозитории и транзакцию. Не знает
 * ни о HTTP, ни о Doctrine-маппингах — эти детали скрыты за портами.
 *
 * @psalm-immutable
 */
final readonly class DeleteEntityUseCase
{
    /**
     * @param TransactionManagerInterface         $transactionManager Порт управления транзакциями
     * @param DeletionPlanner                     $planner            Доменный сервис построения плана
     * @param RelationMetadataRepositoryInterface $metadataRepo       Порт метаданных связей
     * @param DeletionEntityRepositoryInterface   $entityRepo         Порт bulk-операций
     */
    public function __construct(
        private TransactionManagerInterface $transactionManager,
        private DeletionPlanner $planner,
        private RelationMetadataRepositoryInterface $metadataRepo,
        private DeletionEntityRepositoryInterface $entityRepo,
    ) {
    }

    /**
     * Выполняет удаление сущностей в единой транзакции.
     *
     * @param DeleteEntityCommand $command FQCN, IDs и флаг force
     *
     * @throws DeletionBlockedException Если узел заблокирован и force = false
     *
     * @return DeletionResult Статистика по каждому узлу плана
     */
    public function execute(DeleteEntityCommand $command): DeletionResult
    {
        $root = EntityClass::fromString($command->entityClass);

        $plan = $this->planner->plan($root, $this->metadataRepo->for($root));

        return $this->transactionManager->transactional(
            fn (): DeletionResult => $this->executePlan($command, $root, $plan),
        );
    }

    /**
     * Выполняет уже построенный план внутри транзакции.
     *
     * @param DeleteEntityCommand $command
     * @param EntityClass         $root
     * @param DeletionPlan        $plan
     *
     * @throws DeletionBlockedException
     *
     * @return DeletionResult
     */
    private function executePlan(
        DeleteEntityCommand $command,
        EntityClass $root,
        DeletionPlan $plan,
    ): DeletionResult {
        // Делегируем блокировку в репозиторий
        $this->entityRepo->lockByIds($root, $command->ids);

        $result = new DeletionResult();

        foreach ($plan->nodes() as $node) {
            if ($node->action === DeleteAction::Block) {
                if (!$command->force) {
                    throw DeletionBlockedException::forNode(
                        $node->entity->value,
                        $node->relationChain,
                    );
                }
                continue;
            }

            $ids = $node->entity->equals($root)
                ? $command->ids
                : $this->entityRepo->findIdsByRelationPath($node->entity, $node->relationChain);

            if ($ids === []) {
                continue;
            }

            $affected = match ($node->action) {
                DeleteAction::Cascade    => $this->entityRepo->deleteByIds($node->entity, $ids),
                DeleteAction::Detach     => $this->entityRepo->detachByIds($node->entity, $ids),
                DeleteAction::SoftDelete => $this->entityRepo->softDeleteByIds($node->entity, $ids),
                DeleteAction::Block      => throw new LogicException(
                    'DeleteAction::Block не должен доходить до диспетчеризации — он обработан выше.',
                ),
            };

            $result = $result->withNode($node->entity->shortName(), $affected);
        }

        return $result;
    }
}
