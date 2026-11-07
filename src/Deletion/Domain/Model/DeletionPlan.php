<?php

declare(strict_types=1);

namespace App\Deletion\Domain\Model;

use App\Deletion\Domain\ValueObject\EntityClass;
use DomainException;

/**
 * Агрегат (Aggregate Root), представляющий готовый план удаления.
 *
 * Инварианты:
 *  - Корневая сущность всегда должна быть последней в списке (удаляется в самом конце).
 *  - Список узлов не может быть пустым.
 *
 * @psalm-immutable
 */
final readonly class DeletionPlan
{
    /**
     * @param EntityClass        $root  Корневая сущность
     * @param list<DeletionNode> $nodes Упорядоченный список узлов (дети -> родители)
     */
    private function __construct(
        public EntityClass $root,
        private array $nodes,
    ) {
    }

    /**
     * Создает план и проверяет его инварианты.
     *
     * @param EntityClass        $root         Корневая сущность
     * @param list<DeletionNode> $orderedNodes Упорядоченный список узлов
     *
     * @throws DomainException Если инварианты нарушены
     *
     * @return self
     */
    public static function fromOrdered(EntityClass $root, array $orderedNodes): self
    {
        if (empty($orderedNodes)) {
            throw new DomainException('План удаления не может быть пустым.');
        }

        $lastNode = end($orderedNodes);
        if (!$lastNode->entity->equals($root)) {
            throw new DomainException('Корневая сущность должна быть последней в плане удаления.');
        }

        return new self($root, array_values($orderedNodes));
    }

    /**
     * Возвращает упорядоченный список узлов.
     *
     * @return list<DeletionNode>
     */
    public function nodes(): array
    {
        return $this->nodes;
    }

    /**
     * Возвращает общее количество узлов в плане.
     *
     * @return int
     */
    public function count(): int
    {
        return count($this->nodes);
    }
}
