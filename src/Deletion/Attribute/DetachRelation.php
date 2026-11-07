<?php

declare(strict_types=1);

namespace App\Deletion\Attribute;

use Attribute;

/**
 * Атрибут, указывающий, что при удалении сущности связь с ней нужно
 * просто разорвать (обнулить FK или удалить из join-таблицы),
 * не удаляя саму связанную сущность.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class DetachRelation
{
}
