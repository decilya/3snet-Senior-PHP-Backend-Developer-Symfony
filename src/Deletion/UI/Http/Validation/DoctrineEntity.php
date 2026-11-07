<?php

declare(strict_types=1);

namespace App\Deletion\UI\Http\Validation;

use Symfony\Component\Validator\Constraint;

/**
 * Ограничение, проверяющее, что переданное значение является FQCN
 * существующей Doctrine-сущности.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD)]
final class DoctrineEntity extends Constraint
{
    public string $message = 'Класс "{{ value }}" не является Doctrine-сущностью.';
}
