<?php

declare(strict_types=1);

namespace App\Deletion\Infrastructure\Doctrine\Repository;

use App\Deletion\Domain\Model\RelationMap;
use App\Deletion\Domain\Repository\RelationMetadataRepositoryInterface;
use App\Deletion\Domain\ValueObject\EntityClass;
use App\Deletion\Domain\ValueObject\RelationDescriptor;
use App\Deletion\Domain\ValueObject\RelationType;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Реализация порта метаданных на основе ClassMetadata Doctrine.
 */
final readonly class DoctrineRelationMetadataRepository implements RelationMetadataRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function for(EntityClass $class): RelationMap
    {
        $meta = $this->em->getClassMetadata($class->value);
        $relations = [];

        foreach ($meta->getAssociationMappings() as $field => $mapping) {
            $relations[] = new RelationDescriptor(
                field: $field,
                source: $class,
                target: EntityClass::fromString($mapping['targetEntity']),
                type: RelationType::fromDoctrine($mapping['type']),
                isOwningSide: (bool) ($mapping['isOwningSide'] ?? true),
                mappedBy: $mapping['mappedBy'] ?? null,
                inversedBy: $mapping['inversedBy'] ?? null,
                joinTable: $mapping['joinTable'] ?? null,
                orphanRemoval: (bool) ($mapping['orphanRemoval'] ?? false),
            );
        }

        return new RelationMap($class, $relations);
    }
}
