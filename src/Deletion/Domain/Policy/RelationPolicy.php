<?php

declare(strict_types=1);

namespace App\Deletion\Domain\Policy;

use App\Deletion\Domain\ValueObject\DeleteAction;

/**
 * Политика, определяющая правила удаления для конкретной связи.
 *
 * Инварианты:
 *  - Если blocksCurrentDeletion = true, сущность нельзя удалить (выбросится исключение),
 *    если только не передан флаг force.
 */
final readonly class RelationPolicy
{
    /**
     * @param bool              $blocksParentDeletion  Блокирует ли эта связь удаление родителя
     * @param bool              $blocksCurrentDeletion Блокирует ли эта связь удаление самой сущности
     * @param DeleteAction|null $onBlockedAction       Действие при блокировке (обычно Block)
     */
    public function __construct(
        public bool $blocksParentDeletion = false,
        public bool $blocksCurrentDeletion = false,
        public ?DeleteAction $onBlockedAction = null,
    ) {
    }

    /**
     * Объединяет несколько политик в одну (логическое ИЛИ для блокировок).
     *
     * @param self ...$policies
     *
     * @return self
     */
    public static function merge(self ...$policies): self
    {
        return new self(
            blocksParentDeletion: array_reduce($policies, static fn ($c, $p) => $c || $p->blocksParentDeletion, false),
            blocksCurrentDeletion: array_reduce($policies, static fn ($c, $p) => $c || $p->blocksCurrentDeletion, false),
            onBlockedAction: array_reduce($policies, static fn ($c, $p) => $c ?? $p->onBlockedAction, null),
        );
    }

    /**
     * Можно ли удалить сущность с учетом флага force.
     *
     * @param bool $force Принудительное удаление (игнорирует блокировки)
     *
     * @return bool
     */
    public function canDelete(bool $force): bool
    {
        return $force || !$this->blocksCurrentDeletion;
    }
}
