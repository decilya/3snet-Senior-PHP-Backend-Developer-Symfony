<?php

declare(strict_types=1);

namespace App\Deletion\Domain\Service;

use App\Deletion\Domain\ValueObject\DeleteAction;
use App\Deletion\Domain\ValueObject\RelationDescriptor;

/**
 * Порт для определения действия над связью (Cascade, Detach, Block и т.д.).
 *
 * Инкапсулирует логику чтения атрибутов, оставляя домен чистым.
 */
interface RelationActionResolverInterface
{
    /**
     * Возвращает действие для указанной связи.
     *
     * @param RelationDescriptor $relation Описание связи
     *
     * @return DeleteAction
     */
    public function resolve(RelationDescriptor $relation): DeleteAction;
}
