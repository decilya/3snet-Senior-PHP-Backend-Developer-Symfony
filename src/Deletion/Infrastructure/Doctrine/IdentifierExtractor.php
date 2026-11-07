<?php

declare(strict_types=1);

namespace App\Deletion\Infrastructure\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\Proxy;

/**
 * Извлекает идентификаторы сущностей, безопасно работая с прокси-объектами Doctrine.
 *
 * Поддерживает как скалярные первичные ключи, так и составные (Composite Keys).
 */
final readonly class IdentifierExtractor
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    /**
     * Извлекает ID из сущности.
     *
     * @param object $entity Сущность или прокси-объект
     *
     * @return int|string|array<string, mixed> Скалярный ID или ассоциативный массив для composite key
     */
    public function extract(object $entity): int|string|array
    {
        // Если это прокси и он не инициализирован — принудительно загружаем
        if ($entity instanceof Proxy && !$entity->__isInitialized()) {
            $entity->__load();
        }

        $meta = $this->em->getClassMetadata($entity::class);
        $values = $meta->getIdentifierValues($entity);

        return count($values) === 1 ? reset($values) : $values;
    }

    /**
     * Извлекает ID из массива сущностей.
     *
     * @param list<object> $entities Массив сущностей
     *
     * @return list<int|string|array<string, mixed>>
     */
    public function extractMany(array $entities): array
    {
        return array_map(fn (object $e) => $this->extract($e), $entities);
    }
}
