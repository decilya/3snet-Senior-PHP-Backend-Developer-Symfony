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
use Doctrine\ORM\EntityManagerInterface;

/**
 * Сценарий использования: удаление сущности.
 *
 * Оркестрирует работу доменных сервисов, репозиториев и транзакций.
 * Отвечает за соблюдение границ транзакции (wrapInTransaction).
 */
final class DeleteEntityUseCase
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly DeletionPlanner $planner,
        private readonly RelationMetadataRepositoryInterface $metadataRepo,
        private readonly DeletionEntityRepositoryInterface $entityRepo,
    ) {
    }

    /**
     * Выполняет удаление сущности.
     *
     * @param DeleteEntityCommand $command Команда удаления
     *
     * @throws DeletionBlockedException Если сущность заблокирована политикой (и нет флага force)
     *
     * @return DeletionResult
     */
    public function execute(DeleteEntityCommand $command): DeletionResult
    {
        // 1. Преобразуем строку в VO
        $root = EntityClass::fromString($command->entityClass);

        // 2. Получаем граф связей и строим план
        $map = $this->metadataRepo->for($root);
        $plan = $this->planner->plan($root, $map);

        // 3. Открываем транзакцию (атомарность операции)
        return $this->em->wrapInTransaction(function () use ($command, $root, $plan): DeletionResult {
            $result = new DeletionResult();

            foreach ($plan->nodes() as $node) {
                // 4. Проверяем блокировки
                if ($node->action === DeleteAction::Block && !$command->force) {
                    throw DeletionBlockedException::forNode(
                        $node->entity->value,
                        $node->relationChain
                    );
                }

                // 5. Получаем ID для удаления.
                // Для корневой сущности берем из команды, для детей — ищем в БД по пути.
                $ids = $node->entity->equals($root)
                    ? $command->ids
                    : $this->entityRepo->findIdsByRelationPath($node->entity, $node->relationChain);

                if (empty($ids)) {
                    continue; // Нет дочерних записей — пропускаем
                }

                // 6. Выполняем действие через Порт
                $affected = match ($node->action) {
                    DeleteAction::Cascade    => $this->entityRepo->deleteByIds($node->entity, $ids),
                    DeleteAction::Detach     => $this->entityRepo->detachByIds($node->entity, $ids),
                    DeleteAction::SoftDelete => $this->entityRepo->softDeleteByIds($node->entity, $ids),
                    DeleteAction::Block      => 0, // Сюда не дойдем из-за проверки выше
                };

                $result = $result->withNode($node->entity->shortName(), $affected);
            }

            return $result;
        });
    }
}
