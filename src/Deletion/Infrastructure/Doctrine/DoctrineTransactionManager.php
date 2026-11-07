<?php

declare(strict_types=1);

namespace App\Deletion\Infrastructure\Doctrine;

use App\Deletion\Application\Port\TransactionManagerInterface;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Реализация TransactionManager для Doctrine ORM.
 */
final readonly class DoctrineTransactionManager implements TransactionManagerInterface
{
    /**
     * @param EntityManagerInterface $entityManager Менеджер сущностей Doctrine
     */
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @template T
     * @param callable(): T $operation
     * @return mixed
     * @phpstan-return T
     * @psalm-return T
     * @throws \Throwable
     */
    public function transactional(callable $operation): mixed
    {
        $connection = $this->entityManager->getConnection();
        $connection->beginTransaction();

        try {
            $result = $operation();
            $connection->commit();

            return $result;
        } catch (\Throwable $e) {
            $connection->rollBack();
            throw $e;
        }
    }
}
