<?php

declare(strict_types=1);

namespace App\Deletion\UI\Http\Request;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO для HTTP-запроса на удаление сущности.
 *
 * Валидация происходит автоматически благодаря атрибуту #[MapRequestPayload]
 * в контроллере и constraints, описанным ниже.
 *
 * @psalm-immutable
 */
final readonly class DeleteEntityRequest
{
    /**
     * @param string           $entityClass FQCN корневой сущности
     * @param list<int|string> $ids         Идентификаторы сущностей
     * @param bool             $force       Игнорировать ли блокировки
     */
    public function __construct(
        #[Assert\NotBlank(message: 'Поле entityClass обязательно.')]
        #[Assert\ClassExists(message: 'Класс "{{ value }}" не существует.')]
        public string $entityClass,

        #[Assert\NotBlank(message: 'Массив ids не может быть пустым.')]
        #[Assert\Count(min: 1, max: 1000, minMessage: 'Нужно передать хотя бы один ID.')]
        #[Assert\All([
            new Assert\Type(type: 'integer', message: 'Каждый ID должен быть числом.')
        ])]
        public array $ids,

        #[Assert\Type(type: 'boolean', message: 'Поле force должно быть boolean.')]
        public bool $force = false,
    ) {
    }
}
