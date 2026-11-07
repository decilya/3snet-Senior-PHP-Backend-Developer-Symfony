<?php

declare(strict_types=1);

namespace App\Deletion\Domain\ValueObject;

use Doctrine\ORM\Mapping\ClassMetadata;
use InvalidArgumentException;

/**
 * Тип ассоциации между сущностями.
 *
 * Изолирует домен от констант Doctrine. Если завтра мы перейдем на другую ORM,
 * изменится только фабричный метод fromDoctrine().
 */
enum RelationType: string
{
    case OneToOne    = 'one_to_one';
    case OneToMany   = 'one_to_many';
    case ManyToOne   = 'many_to_one';
    case ManyToMany  = 'many_to_many';

    /**
     * Фабрика из констант Doctrine ClassMetadata.
     *
     * @param int $doctrineType
     * @return self
     */
    public static function fromDoctrine(int $doctrineType): self
    {
        return match ($doctrineType) {
            ClassMetadata::ONE_TO_ONE   => self::OneToOne,
            ClassMetadata::MANY_TO_ONE  => self::ManyToOne,
            ClassMetadata::ONE_TO_MANY  => self::OneToMany,
            ClassMetadata::MANY_TO_MANY => self::ManyToMany,
            default => throw new InvalidArgumentException("Неизвестный тип ассоциации: {$doctrineType}"),
        };
    }

    /**
     * Относится ли связь к типу "Один-ко-Многим" или "Многие-ко-Многим".
     */
    public function isToMany(): bool
    {
        return $this === self::OneToMany || $this === self::ManyToMany;
    }

    /**
     * Относится ли связь к типу "Один-к-Одному" или "Многие-к-Одному".
     */
    public function isToOne(): bool
    {
        return $this === self::OneToOne || $this === self::ManyToOne;
    }
}
