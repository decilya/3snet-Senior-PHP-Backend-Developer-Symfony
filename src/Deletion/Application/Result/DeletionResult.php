<?php

declare(strict_types=1);

namespace App\Deletion\Application\Result;

/**
 * Результат выполнения сценария удаления.
 *
 * Собирает статистику по каждой обработанной сущности.
 */
final readonly class DeletionResult
{
    /**
     * @param array<string, int> $stats Ассоциативный массив [ИмяСущности => Кол-во затронутых записей]
     */
    public function __construct(
        public array $stats = [],
    ) {
    }

    /**
     * Возвращает новый экземпляр результата с добавленной статистикой.
     *
     * @param string $entityName Короткое имя сущности (например, "Order")
     * @param int    $affected   Количество удаленных/измененных записей
     *
     * @return self
     */
    public function withNode(string $entityName, int $affected): self
    {
        $newStats = $this->stats;
        $newStats[$entityName] = ($newStats[$entityName] ?? 0) + $affected;

        return new self($newStats);
    }

    /**
     * Возвращает общее количество затронутых записей по всем сущностям.
     *
     * @return int
     */
    public function totalAffected(): int
    {
        return array_sum($this->stats);
    }
}
