<?php

declare(strict_types=1);

namespace App\Deletion\Infrastructure\Doctrine\Repository;

use App\Deletion\Domain\Model\RelationMap;
use App\Deletion\Domain\Repository\RelationMetadataRepositoryInterface;
use App\Deletion\Domain\ValueObject\EntityClass;
use App\Deletion\Domain\ValueObject\RelationDescriptor;
use App\Deletion\Domain\ValueObject\RelationType;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use InvalidArgumentException;

/**
 * Doctrine-адаптер порта RelationMetadataRepositoryInterface.
 *
 * Выполняет роль Anti-Corruption Layer: переводит сырые массивы
 * `ClassMetadata::getAssociationMappings()` в доменные Value Objects.
 * Домен никогда не видит Doctrine-структур напрямую.
 */
final readonly class DoctrineRelationMetadataRepository implements RelationMetadataRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function for(EntityClass $class): RelationMap
    {
        $meta = $this->em->getClassMetadata($class->value);
        $relations = [];

        foreach ($meta->getAssociationMappings() as $field => $mapping) {
            $relations[] = new RelationDescriptor(
                field:         $field,
                source:        $class,
                target:        EntityClass::fromString($mapping['targetEntity']),
                type:          $this->mapDoctrineType($mapping['type']),
                isOwningSide:  (bool) ($mapping['isOwningSide'] ?? true),
                mappedBy:      $mapping['mappedBy']     ?? null,
                inversedBy:    $mapping['inversedBy']   ?? null,
                joinTable:     $mapping['joinTable']    ?? null,
                orphanRemoval: (bool) ($mapping['orphanRemoval'] ?? false),
            );
        }

        return new RelationMap($class, $relations);
    }

    /**
     * Преобразует константу типа ассоциации Doctrine в доменный enum.
     *
     * @param int $doctrineType
     *
     * @throws InvalidArgumentException
     *
     * @return RelationType
     */
    private function mapDoctrineType(int $doctrineType): RelationType
    {
        return match ($doctrineType) {
            ClassMetadata::ONE_TO_ONE   => RelationType::OneToOne,
            ClassMetadata::MANY_TO_ONE  => RelationType::ManyToOne,
            ClassMetadata::ONE_TO_MANY  => RelationType::OneToMany,
            ClassMetadata::MANY_TO_MANY => RelationType::ManyToMany,
            default => throw new InvalidArgumentException(
                sprintf('Неизвестный тип ассоциации Doctrine: %d.', $doctrineType),
            ),
        };
    }
}
