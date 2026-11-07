<?php

declare(strict_types=1);

namespace App\Deletion\Domain\ValueObject;

use InvalidArgumentException;

/**
 * Класс-значение, представляющий FQCN (полное имя) сущности.
 *
 * Инварианты:
 *  - Класс должен существовать в системе (защита от опечаток).
 *  - Префикс прокси-классов Doctrine (Proxies\__CG__\...) автоматически отсекается,
 *    чтобы мы могли безопасно передавать сюда ленивые прокси-объекты.
 */
final readonly class EntityClass
{
    /**
     * @param string $value
     */
    private function __construct(public string $value)
    {
    }

    /**
     * Создает VO из строки.
     *
     * @throws InvalidArgumentException Если класс не найден
     */
    public static function fromString(string $fqcn): self
    {
        // 1. Убираем ведущий слэш (чтобы \App\Entity\Order и App\Entity\Order были равны)
        $normalized = ltrim($fqcn, '\\');

        // 2. Отсекаем префикс прокси Doctrine
        //    \Proxies\__CG__\App\Entity\Order -> App\Entity\Order
        $normalized = preg_replace('/^Proxies\\\\__(?:CG|PM)__\\\\/', '', $normalized);

        if (!class_exists($normalized)) {
            throw new InvalidArgumentException(sprintf('Класс сущности "%s" не найден.', $normalized));
        }

        return new self($normalized);
    }

    /**
     * Сравнение объектов по значению (стандарт для Value Objects).
     *
     * @param EntityClass $other
     * @return bool
     */
    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    /**
     * Короткое имя класса для логов и метрик (например, "Order" вместо "App\Entity\Order").
     *
     * @return string
     */
    public function shortName(): string
    {
        $pos = strrpos($this->value, '\\');
        return $pos === false ? $this->value : substr($this->value, $pos + 1);
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        return $this->value;
    }
}
