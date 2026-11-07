<?php

declare(strict_types=1);

namespace App\Deletion\Application\Service;

use App\Deletion\Application\Command\DeleteEntityCommand;
use App\Deletion\Application\Result\DeletionResult;
use App\Deletion\Domain\Exception\DeletionBlockedException;
use App\Deletion\Domain\Repository\DeletionEntityRepositoryInterface;
use App\Deletion\Domain\Repository\RelationMetadataRepositoryInterface;
use App\Deletion\Domain\Service\DeletionPlanner;
use App\Deletion\Domain\ValueObject\DeleteAction;
use App\Deletion\Domain\ValueObject\EntityClass;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;

/**
 * Сценарий использования: удаление сущности с каскадной обработкой связей.
 *
 * Оркестрирует доменные сервисы, репозитории и транзакцию. Не знает
 * ни о HTTP, ни о Doctrine-маппингах — эти детали скрыты за портами.
 *
 * Жизненный цикл:
 *   1. Pessimistic lock корневых записей (защита от phantom reads);
 *   2. Проверка блокировок из плана;
 *   3. Bulk-операции через порт репозитория;
 *   4. Возврат статистики в DeletionResult.
 *
 * @psalm-immutable
 */
final readonly class DeleteEntityUseCase
{
    /**
     * @param EntityManagerInterface              $em           Транзакция и pessimist-lock
     * @param DeletionPlanner                     $planner      Доменный сервис построения плана
     * @param RelationMetadataRepositoryInterface $metadataRepo Порт метаданных связей (ACL над ClassMetadata)
     * @param DeletionEntityRepositoryInterface   $entityRepo   Порт bulk-операций (DBAL)
     */
    public function __construct(
        private EntityManagerInterface $em,
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
        // fromString() бросает InvalidArgumentException на опечатку в FQCN —
        // это отсеивает «App\Entity\Odrer» до открытия транзакции.
        $root = EntityClass::fromString($command->entityClass);

        $plan = $this->planner->plan($root, $this->metadataRepo->for($root));

        return $this->em->wrapInTransaction(
            fn (): DeletionResult => $this->executePlan($command, $root, $plan),
        );
    }

    /**
     * Выполняет уже построенный план внутри транзакции.
     *
     * @param DeleteEntityCommand            $command
     * @param EntityClass                    $root
     * @param \App\Deletion\Domain\Model\DeletionPlan $plan
     *
     * @throws DeletionBlockedException
     *
     * @return DeletionResult
     */
    private function executePlan(
        DeleteEntityCommand $command,
        EntityClass $root,
        \App\Deletion\Domain\Model\DeletionPlan $plan,
    ): DeletionResult {
        // Держим FOR UPDATE на корневых строках, пока идёт весь обход.
        // Без этого между планированием и удалением кто-то успеет добавить
        // нового ребёнка, и FK-констрейнт упадёт в самом конце.
        $this->lockRootEntities($root, $command->ids);

        $result = new DeletionResult();

        foreach ($plan->nodes() as $node) {
            // BLOCK + force=false → прерываем всю операцию, откатываем транзакцию.
            // BLOCK + force=true  → пропускаем этот узел целиком: удалять нечего,
            //                       но и мешать обходу не надо.
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

            // Пустой результат — легальная ситуация: у родителя может не быть
            // детей данного типа. Пропускаем, не считаем это ошибкой.
            if ($ids === []) {
                continue;
            }

            // Все четыре действия покрыты match'ем. Block недостижим —
            // выше есть ветка, которая его отсекает. LogicException здесь
            // защищает от регрессии, если кто-то поменяет порядок проверок.
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

    /**
     * Блокирует корневые записи через `SELECT ... FOR UPDATE`.
     *
     * @param EntityClass                           $root
     * @param list<int|string|array<string, mixed>> $ids
     *
     * @return void
     */
    private function lockRootEntities(EntityClass $root, array $ids): void
    {
        if ($ids === []) {
            return;
        }

        /** @var class-string $entityClassName */
        $entityClassName = $root->value;

        $this->em->createQueryBuilder()
            ->select('e')
            ->from($entityClassName/** @type  */, 'e')
            ->where('e.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->setLockMode(\Doctrine\DBAL\LockMode::PESSIMISTIC_WRITE)
            ->getResult();
    }
}
