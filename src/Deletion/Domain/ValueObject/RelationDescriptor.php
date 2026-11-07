<?php

declare(strict_types=1);

namespace App\Deletion\Domain\ValueObject;

/**
 * Описание одной ассоциации между сущностями.
 *
 * Является доменной проекцией массива ClassMetadata::getAssociationMappings().
 */
final readonly class RelationDescriptor
{
    /**
     * @param string $field
     * @param EntityClass $source
     * @param EntityClass $target
     * @param RelationType $type
     * @param bool $isOwningSide
     * @param string|null $mappedBy
     * @param string|null $inversedBy
     * @param array|null $joinTable
     * @param bool $orphanRemoval
     */
    public function __construct(
        public string $field,
        public EntityClass $source,
        public EntityClass $target,
        public RelationType $type,
        public bool $isOwningSide,
        public ?string $mappedBy = null,
        public ?string $inversedBy = null,
        public ?array $joinTable = null,
        public bool $orphanRemoval = false,
    ) {
    }

    /**
     * @return bool
     */
    public function isToMany(): bool
    {
        return $this->type->isToMany();
    }

    /**
     * @return bool
     */
    public function isToOne(): bool
    {
        return $this->type->isToOne();
    }

    public function isManyToMany(): bool
    {
        return $this->type === RelationType::ManyToMany;
    }

    /**
     * Человекочитаемое имя для логов.
     *
     * @return string
     */
    public function describe(): string
    {
        return sprintf('%s.%s (%s)', $this->source->shortName(), $this->field, $this->type->value);
    }
}
