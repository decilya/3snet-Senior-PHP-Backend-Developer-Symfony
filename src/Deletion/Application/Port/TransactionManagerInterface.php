<?php

declare(strict_types=1);

namespace App\Deletion\Application\Port;

/**
 * Порт для управления транзакциями.
 *
 * Абстрагирует Application-слой от конкретной реализации (Doctrine, DBAL, Cycle).
 */
interface TransactionManagerInterface
{
    /**
     * Выполняет операцию в транзакции.
     *
     * Если операция выбросит исключение — транзакция откатывается.
     * Если успешно — коммитится.
     *
     * @template T
     * @param callable(): T $operation
     * @return T
     * @throws \Throwable Если операция бросила исключение (после rollback)
     */
    public function transactional(callable $operation): mixed;
}
