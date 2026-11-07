<?php

declare(strict_types=1);

namespace App\Deletion\Domain\Model;

use App\Deletion\Domain\ValueObject\EntityClass;
use App\Deletion\Domain\ValueObject\RelationDescriptor;

/**
 * Карта связей (граф) для одной корневой сущности.
 *
 * Содержит список всех ассоциаций, которые нужно обойти при удалении.
 * Является иммутабельным объектом.
 *
 * @psalm-immutable
 */
final readonly class RelationMap
{
    /**
     * @param EntityClass              $root      Корневая сущность, с которой начинается удаление
     * @param list<RelationDescriptor> $relations Список ассоциаций этой сущности
     */
    public function __construct(
        public EntityClass $root,
        public array $relations,
    ) {
    }

    /**
     * Возвращает все связи, где указанная сущность является источником (source).
     *
     * @param EntityClass $entity Сущность, для которой ищем связи
     *
     * @return list<RelationDescriptor>
     */
    public function relationsFor(EntityClass $entity): array
    {
        return array_values(array_filter(
            $this->relations,
            static fn (RelationDescriptor $rel) => $rel->source->equals($entity)
        ));
    }
}
