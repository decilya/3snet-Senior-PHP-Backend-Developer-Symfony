<?php

declare(strict_types=1);

namespace App\Deletion\Domain\Model;

use App\Deletion\Domain\ValueObject\DeleteAction;
use App\Deletion\Domain\ValueObject\EntityClass;

/**
 * Узел плана удаления.
 *
 * Содержит информацию о том, какую сущность нужно обработать,
 * какое действие к ней применить и по какому пути мы до нее дошли.
 *
 * @psalm-immutable
 */
final readonly class DeletionNode
{
    /**
     * @param EntityClass  $entity        Сущность, которую нужно обработать
     * @param DeleteAction $action        Действие (Cascade, Detach, SoftDelete, Block)
     * @param list<string> $relationChain Путь до этой сущности (для логов и ошибок)
     */
    public function __construct(
        public EntityClass $entity,
        public DeleteAction $action,
        public array $relationChain,
    ) {
    }

    /**
     * Возвращает человекочитаемый путь (например, "Order -> OrderItem -> Product").
     *
     * @return string
     */
    public function describePath(): string
    {
        return implode(' -> ', $this->relationChain);
    }
}
