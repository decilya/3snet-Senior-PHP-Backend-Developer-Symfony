<?php

declare(strict_types=1);

namespace App\Deletion\Application\Command;

/**
 * Команда на удаление сущностей.
 *
 * Содержит все необходимые данные для запуска сценария удаления.
 * Является иммутабельным объектом.
 *
 * @psalm-immutable
 */
final readonly class DeleteEntityCommand
{
    /**
     * @param string                           $entityClass FQCN корневой сущности
     * @param list<int|string|array<string, mixed>> $ids    Идентификаторы сущностей (скалярные или составные)
     * @param bool                             $force       Игнорировать ли блокировки (опасно!)
     */
    public function __construct(
        public string $entityClass,
        public array $ids,
        public bool $force = false,
    ) {
    }
}
