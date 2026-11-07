<?php

declare(strict_types=1);

namespace App\Deletion\Attribute;

use Attribute;

/**
 * Атрибут, указывающий, что при удалении сущности связанные с ней
 * записи должны быть удалены каскадно.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class CascadeDelete
{
}
