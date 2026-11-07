<?php

declare(strict_types=1);

namespace App\Deletion\Infrastructure\Attribute;

use App\Deletion\Attribute\BlockingDeletion;
use App\Deletion\Attribute\CascadeDelete;
use App\Deletion\Attribute\DetachRelation;
use App\Deletion\Domain\Service\RelationActionResolverInterface;
use App\Deletion\Domain\ValueObject\DeleteAction;
use App\Deletion\Domain\ValueObject\RelationDescriptor;
use ReflectionClass;

/**
 * Infrastructure-адаптер порта {@see RelationActionResolverInterface}.
 *
 * Читает PHP-атрибуты (CascadeDelete, DetachRelation, BlockingDeletion)
 * у свойства исходной сущности. Реализация специфична для PHP-атрибутов —
 * при желании можно заменить на чтение YAML, Doctrine-метаданных и т.д.,
 * не меняя Domain/Application.
 */
final readonly class RelationActionResolver implements RelationActionResolverInterface
{
    /**
     * @throws \ReflectionException
     */
    public function resolve(RelationDescriptor $relation): DeleteAction
    {
        $reflection = new ReflectionClass($relation->source->value);

        if (!$reflection->hasProperty($relation->field)) {
            return DeleteAction::Cascade;
        }

        $attributes = $reflection->getProperty($relation->field)->getAttributes();

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
