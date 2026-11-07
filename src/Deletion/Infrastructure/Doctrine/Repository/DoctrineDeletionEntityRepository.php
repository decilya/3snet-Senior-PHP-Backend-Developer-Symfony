<?php

declare(strict_types=1);

namespace App\Deletion\Infrastructure\Doctrine\Repository;

use App\Deletion\Domain\Repository\DeletionEntityRepositoryInterface;
use App\Deletion\Domain\ValueObject\EntityClass;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use LogicException;

/**
 * Реализация порта удаления через Doctrine DBAL (bulk-операции).
 *
 * Использование DBAL вместо ORM позволяет избежать утечек памяти
 * и проблем с identity map при массовом удалении.
 */
final readonly class DoctrineDeletionEntityRepository implements DeletionEntityRepositoryInterface
{
    private Connection $conn;

    /**
     * @param EntityManagerInterface $em
     * @param string $softDeleteField
     */
    public function __construct(
        private EntityManagerInterface $em,
        private string $softDeleteField = 'deletedAt',
    ) {
        $this->conn = $em->getConnection();
    }

    /**
     * {@inheritdoc}
     */
    public function findIdsByRelationPath(EntityClass $entity, array $relationPath): array
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function deleteByIds(EntityClass $entity, array $ids): int
    {
        if ($ids === []) {
            return 0;
        }

        $meta = $this->em->getClassMetadata($entity->value);
        $affected = $this->executeBulkDelete($meta, $ids);

        $this->em->clear($entity->value);

        return $affected;
    }

    /**
     * {@inheritdoc}
     */
    public function detachByIds(EntityClass $entity, array $ids): int
    {
        if ($ids === []) {
            return 0;
        }

        $meta = $this->em->getClassMetadata($entity->value);
        $pkField = $meta->getIdentifierFieldNames()[0];
        $pkColumn = $this->conn->quoteSingleIdentifier($meta->getColumnName($pkField));

        $affected = 0;

        foreach ($meta->getAssociationMappings() as $mapping) {
            // ManyToMany: удаляем из join-таблицы
            if (isset($mapping['joinTable'])) {
                $joinTable = $mapping['joinTable'];
                $affected += $this->conn->executeStatement(
                    sprintf('DELETE FROM %s WHERE %s IN (?)',
                        $this->conn->quoteSingleIdentifier($joinTable['name']),
                        $this->conn->quoteSingleIdentifier($joinTable['joinColumns'][0]['name'])
                    ),
                    [$ids],
                    [ArrayParameterType::STRING]
                );
                continue;
            }

            // To-One: обнуляем FK
            if (($mapping['isOwningSide'] ?? false) && isset($mapping['joinColumns'][0]['name'])) {
                $affected += $this->conn->executeStatement(
                    sprintf('UPDATE %s SET %s = NULL WHERE %s IN (?)',
                        $this->conn->quoteSingleIdentifier($meta->getTableName()),
                        $this->conn->quoteSingleIdentifier($mapping['joinColumns'][0]['name']),
                        $pkColumn
                    ),
                    [$ids],
                    [ArrayParameterType::STRING]
                );
            }
        }

        $this->em->clear($entity->value);

        return $affected;
    }

    /**
     * {@inheritdoc}
     */
    public function softDeleteByIds(EntityClass $entity, array $ids): int
    {
        if ($ids === []) {
            return 0;
        }

        $meta = $this->em->getClassMetadata($entity->value);

        if (!$meta->hasField($this->softDeleteField)) {
            throw new LogicException(sprintf('Сущность %s не имеет поля %s для мягкого удаления.', $entity->value, $this->softDeleteField));
        }

        $pkField = $meta->getIdentifierFieldNames()[0];
        $pkColumn = $this->conn->quoteSingleIdentifier($meta->getColumnName($pkField));

        $affected = $this->conn->executeStatement(
            sprintf('UPDATE %s SET %s = :now WHERE %s IN (?)',
                $this->conn->quoteSingleIdentifier($meta->getTableName()),
                $this->conn->quoteSingleIdentifier($meta->getColumnName($this->softDeleteField)),
                $pkColumn
            ),
            [
                'ids' => $ids,
                'now' => (new \DateTimeImmutable())->format('Y-m-d H:i:s')
            ],
            [
                'ids' => ArrayParameterType::STRING
            ]
        );

        $this->em->clear($entity->value);

        return $affected;
    }

    /**
     * Выполняет bulk-удаление с учетом скалярных и составных ключей.
     *
     * @param ClassMetadata $meta
     * @param array $ids
     * @return int
     * @throws \Doctrine\DBAL\Exception
     */
    private function executeBulkDelete(ClassMetadata $meta, array $ids): int
    {
        $pkFields = $meta->getIdentifierFieldNames();

        if (count($pkFields) === 1) {
            $pkColumn = $meta->getColumnName($pkFields[0]);

            return $this->conn->executeStatement(
                sprintf('DELETE FROM %s WHERE %s IN (?)',
                    $this->conn->quoteSingleIdentifier($meta->getTableName()),
                    $this->conn->quoteSingleIdentifier($pkColumn)
                ),
                [$ids],
                [ArrayParameterType::STRING]
            );
        }

        // Обработка Composite Keys (составных ключей) — транзакция уже открыта в TransactionManager
        $affected = 0;
        foreach ($ids as $row) {
            $affected += $this->conn->delete($meta->getTableName(), array_combine($pkFields, (array) $row));
        }

        return $affected;
    }

    /**
     * Блокирует записи через SELECT ... FOR UPDATE на уровне DBAL.
     *
     * @param EntityClass $entity
     * @param array<int|string> $ids
     * @return void
     * @throws \Doctrine\DBAL\Exception
     */
    public function lockByIds(EntityClass $entity, array $ids): void
    {
        if ($ids === []) {
            return;
        }

        $meta = $this->em->getClassMetadata($entity->value);

        $pkField = $meta->getIdentifierFieldNames()[0];
        $tableName = $this->conn->quoteSingleIdentifier($meta->getTableName());
        $columnName = $this->conn->quoteSingleIdentifier($meta->getColumnName($pkField));

        $sql = sprintf('SELECT 1 FROM %s WHERE %s IN (?) FOR UPDATE', $tableName, $columnName);

        $this->conn->executeQuery(
            $sql,
            [$ids],
            [ArrayParameterType::STRING]
        );
    }
}
