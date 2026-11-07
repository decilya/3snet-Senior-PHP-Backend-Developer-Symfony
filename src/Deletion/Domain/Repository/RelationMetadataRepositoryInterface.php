<?php

declare(strict_types=1);

namespace App\Deletion\Domain\Repository;

use App\Deletion\Domain\Model\RelationMap;
use App\Deletion\Domain\ValueObject\EntityClass;

/**
 * Порт (интерфейс) для получения метаданных о связях сущностей.
 *
 * Реализуется в Infrastructure слое (обычно через ClassMetadata Doctrine).
 */
interface RelationMetadataRepositoryInterface
{
    /**
     * Возвращает карту связей для указанной сущности.
     *
     * @param EntityClass $class Корневая сущность
     *
     * @return RelationMap
     */
    public function for(EntityClass $class): RelationMap;
}
