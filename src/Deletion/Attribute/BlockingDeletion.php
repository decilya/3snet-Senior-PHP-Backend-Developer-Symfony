<?php

declare(strict_types=1);

namespace App\Deletion\Attribute;

use Attribute;

/**
 * Атрибут, блокирующий удаление сущности, если с ней есть связанные записи.
 * При попытке удаления будет выброшено исключение DeletionBlockedException.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class BlockingDeletion
{
}
