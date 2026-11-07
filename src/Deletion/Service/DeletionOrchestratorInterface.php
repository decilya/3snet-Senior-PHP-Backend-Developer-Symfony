<?php

declare(strict_types=1);

namespace Shared\Deletion\Service;

use Shared\Deletion\Dto\{OrderedPlanDto, RelationsDto};

/**
 * Интерфейс оркестратора удаления.
 *
 * Определяет контракт для выполнения операции удаления сущности,
 * анализа зависимостей и построения плана удаления.
 */
interface DeletionOrchestratorInterface
{
    /**
     * Выполняет операцию удаления корневой сущности с каскадным удалением/отсоединением.
     *
     * @param object $root корневая сущность, которую нужно удалить
     * @param bool $dryRun если true, операция выполняется в режиме сухого прогона
     *                       (только анализ и уведомления, без фактического удаления)
     *
     * @throws \Throwable если удаление невозможно или произошла ошибка
     */
    public function execute(object $root, bool $dryRun = false): void;

    /**
     * Анализирует зависимости корневой сущности и возвращает результат анализа.
     *
     * @param object $root корневая сущность
     *
     * @return RelationsDto содержит информацию о родителях, дочерних элементах,
     *                       которые будут удалены или отсоединены, и флаг canDelete
     */
    public function plan(object $root): RelationsDto;

    /**
     * Строит упорядоченный план удаления на основе анализа зависимостей.
     *
     * Возвращает структурированный план, содержащий:
     * - список сущностей для удаления (в правильном порядке)
     * - список связей для отсоединения (detach)
     *
     * @param object $root корневая сущность
     *
     * @return OrderedPlanDto план удаления
     */
    public function getOrderedPlan(object $root): OrderedPlanDto;
}