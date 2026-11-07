<?php

declare(strict_types=1);

namespace App\Deletion\Domain\ValueObject;

/**
 * Тип ассоциации между двумя сущностями.
 *
 * Намеренно изолирован от конкретной ORM: домен не импортирует
 * ни одного класса Doctrine. Маппинг значений из `ClassMetadata`
 * выполняется в Infrastructure-слое (Anti-Corruption Layer).
 *
 * @psalm-immutable
 */
enum RelationType: string
{
    case OneToOne    = 'one_to_one';
    case OneToMany   = 'one_to_many';
    case ManyToOne   = 'many_to_one';
    case ManyToMany  = 'many_to_many';

    /**
     * true для типов, где у одной сущности много связанных.
     *
     * @return bool
     */
    public function isToMany(): bool
    {
        return $this === self::OneToMany || $this === self::ManyToMany;
    }

    /**
     * true для типов, где связь хранится во внешнем ключе.
     *
     * @return bool
     */
    public function isToOne(): bool
    {
        return $this === self::OneToOne || $this === self::ManyToOne;
    }
}
