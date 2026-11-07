<?php

declare(strict_types=1);

namespace App\Deletion\Domain\Exception;

use RuntimeException;

/**
 * Исключение, выбрасываемое при обнаружении циклической зависимости
 * в графе связей сущностей.
 */
final class CyclicDependencyException extends RuntimeException
{
    /**
     * Создает исключение на основе стека обхода.
     *
     * @param list<string> $stack    Стек сущностей, в котором произошел цикл
     * @param string       $repeated Сущность, которая встретилась повторно
     *
     * @return self
     */
    public static function forPath(array $stack, string $repeated): self
    {
        return new self(sprintf(
            'Обнаружена циклическая зависимость: %s -> %s',
            implode(' -> ', $stack),
            $repeated
        ));
    }
}
