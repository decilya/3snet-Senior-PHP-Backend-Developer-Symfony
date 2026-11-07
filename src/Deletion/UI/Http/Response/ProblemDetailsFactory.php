<?php

declare(strict_types=1);

namespace App\Deletion\UI\Http\Response;

use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Фабрика для создания HTTP-ответов в формате RFC 7807 (Problem Details).
 *
 * Инкапсулирует структуру ответа, чтобы избежать дублирования кода
 * в контроллерах и слушателях событий.
 */
final readonly class ProblemDetailsFactory
{
    /**
     * Формирует JSON-ответ с описанием ошибки.
     *
     * @param string $title  Краткое описание класса ошибки
     * @param string $detail Подробное описание конкретной ситуации
     * @param int    $status HTTP-статус
     *
     * @return JsonResponse
     */
    public function create(string $title, string $detail, int $status): JsonResponse
    {
        return new JsonResponse([
            'type'   => 'about:blank',
            'title'  => $title,
            'status' => $status,
            'detail' => $detail,
        ], $status);
    }
}
