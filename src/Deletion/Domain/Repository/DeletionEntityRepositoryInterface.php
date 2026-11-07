<?php

declare(strict_types=1);

namespace App\Deletion\Domain\Repository;

use App\Deletion\Domain\ValueObject\EntityClass;

/**
 * Порт (интерфейс) для выполнения операций удаления/отсоединения в БД.
 *
 * Спроектирован под bulk-операции (работа с массивами ID), чтобы избежать
 * N+1 проблемы и утечек памяти.
 */
interface DeletionEntityRepositoryInterface
{
    /**
     * Находит ID всех сущностей по заданному пути связей.
     *
     * @param EntityClass  $entity       Тип сущности
     * @param list<string> $relationPath Путь связей
     *
     * @return list<int|string|array<string, mixed>>
     */
    public function findIdsByRelationPath(EntityClass $entity, array $relationPath): array;

    /**
     * Удаляет сущности по их ID (Cascade).
     *
     * @param EntityClass                           $entity
     * @param list<int|string|array<string, mixed>> $ids
     *
     * @return int Количество удаленных записей
     */
    public function deleteByIds(EntityClass $entity, array $ids): int;

    /**
     * Отсоединяет связи для указанных сущностей (Detach).
     *
     * @param EntityClass                           $entity
     * @param list<int|string|array<string, mixed>> $ids
     *
     * @return int Количество затронутых записей
     */
    public function detachByIds(EntityClass $entity, array $ids): int;

    /**
     * Помечает сущности как удаленные (SoftDelete).
     *
     * @param EntityClass                           $entity
     * @param list<int|string|array<string, mixed>> $ids
     *
     * @return int Количество затронутых записей
     */
    public function softDeleteByIds(EntityClass $entity, array $ids): int;
}
