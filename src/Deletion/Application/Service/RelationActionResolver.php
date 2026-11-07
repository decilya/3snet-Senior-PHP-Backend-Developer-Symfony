<?php

declare(strict_types=1);

namespace App\Deletion\Application\Service;

use App\Deletion\Attribute\BlockingDeletion;
use App\Deletion\Attribute\CascadeDelete;
use App\Deletion\Attribute\DetachRelation;
use App\Deletion\Domain\ValueObject\DeleteAction;
use App\Deletion\Domain\ValueObject\RelationDescriptor;
use ReflectionClass;

/**
 * Сервис, определяющий действие для связи на основе PHP-атрибутов.
 *
 * Читает атрибуты (CascadeDelete, DetachRelation, BlockingDeletion)
 * у свойства исходной сущности и возвращает соответствующее действие.
 */
final readonly class RelationActionResolver
{
    /**
     * Возвращает действие для указанной связи.
     *
     * @param RelationDescriptor $relation Описание связи
     *
     * @return DeleteAction
     * @throws \ReflectionException
     */
    public function __invoke(RelationDescriptor $relation): DeleteAction
    {
        $reflection = new ReflectionClass($relation->source->value);

        if (!$reflection->hasProperty($relation->field)) {
            return DeleteAction::Cascade;
        }

        $property = $reflection->getProperty($relation->field);
        $attributes = $property->getAttributes();

        foreach ($attributes as $attribute) {
            $name = $attribute->getName();

            if ($name === CascadeDelete::class) {
                return DeleteAction::Cascade;
            }
            if ($name === DetachRelation::class) {
                return DeleteAction::Detach;
            }
            if ($name === BlockingDeletion::class) {
                return DeleteAction::Block;
            }
        }

        return DeleteAction::Detach;
    }
}
