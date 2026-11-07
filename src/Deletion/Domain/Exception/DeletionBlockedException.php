<?php

declare(strict_types=1);

namespace App\Deletion\Domain\Exception;

use RuntimeException;

/**
 * Исключение, выбрасываемое при попытке удалить сущность,
 * которая защищена политикой безопасности (BLOCK).
 */
final class DeletionBlockedException extends RuntimeException
{
    /**
     * @param string       $entity Название сущности
     * @param list<string> $chain  Путь, по которому мы дошли до блокирующей связи
     *
     * @return self
     */
    public static function forNode(string $entity, array $chain = []): self
    {
        $path = $chain !== [] ? implode(' -> ', $chain) : $entity;
        return new self("Удаление заблокировано для сущности {$entity} (путь: {$path}).");
    }
}
