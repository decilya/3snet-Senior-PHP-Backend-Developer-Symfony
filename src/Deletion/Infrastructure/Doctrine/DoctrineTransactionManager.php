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
     * @return T
     * @throws \Throwable
     */
    public function transactional(callable $operation): mixed
    {
        $this->entityManager->beginTransaction();

        try {
            $result = $operation();
            $this->entityManager->flush();
            $this->entityManager->commit();

            return $result;
        } catch (\Throwable $e) {
            $this->entityManager->rollback();
            throw $e;
        }
    }
}
