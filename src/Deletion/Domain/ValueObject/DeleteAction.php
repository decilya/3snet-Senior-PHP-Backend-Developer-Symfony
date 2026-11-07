<?php

declare(strict_types=1);

namespace App\Deletion\Domain\ValueObject;

/**
 * Действие, применяемое к узлу графа зависимостей.
 *
 * ВАЖНО: Один и тот же атрибут сущности может порождать разные действия
 * для родителя и для ребенка.
 */
enum DeleteAction: string
{
    /** Полное удаление из БД (DELETE FROM). */
    case Cascade    = 'cascade';

    /** Отсоединение связи: обнуление FK или удаление из join-таблицы. */
    case Detach     = 'detach';

    /** Мягкое удаление: UPDATE deleted_at = NOW(). */
    case SoftDelete = 'soft_delete';

    /** Операция блокируется, выбрасывается исключение. */
    case Block      = 'block';

    /**
     * Изменяет ли действие данные в БД.
     *
     * @return bool
     */
    public function mutatesData(): bool
    {
        return $this !== self::Block;
    }
}
