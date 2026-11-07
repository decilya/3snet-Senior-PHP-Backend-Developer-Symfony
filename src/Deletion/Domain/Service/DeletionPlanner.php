<?php

declare(strict_types=1);

namespace App\Deletion\Domain\Service;

use App\Deletion\Domain\Exception\CyclicDependencyException;
use App\Deletion\Domain\Model\DeletionNode;
use App\Deletion\Domain\Model\DeletionPlan;
use App\Deletion\Domain\Model\RelationMap;
use App\Deletion\Domain\ValueObject\DeleteAction;
use App\Deletion\Domain\ValueObject\EntityClass;
use App\Deletion\Domain\ValueObject\RelationDescriptor;

/**
 * Доменный сервис для построения плана удаления.
 *
 * Использует алгоритм поиска в глубину (DFS) для обхода графа зависимостей.
 * Формирует топологический порядок (post-order), при котором дочерние
 * сущности всегда обрабатываются раньше родительских.
 */
final class DeletionPlanner
{
    /** @var callable(RelationDescriptor): DeleteAction */
    private $actionResolver;

    /**
     * @param callable(RelationDescriptor): DeleteAction $actionResolver
     *        Функция, которая по описанию связи возвращает действие (Cascade, Detach, Block)
     */
    public function __construct(callable $actionResolver)
    {
        $this->actionResolver = $actionResolver;
    }

    /**
     * Строит план удаления для корневой сущности.
     *
     * @param EntityClass $root Корневая сущность
     * @param RelationMap $map  Граф связей
     *
     * @throws CyclicDependencyException Если найден цикл
     *
     * @return DeletionPlan
     */
    public function plan(EntityClass $root, RelationMap $map): DeletionPlan
    {
        $visited = [];
        $stack   = [];
        $order   = [];

        // Для корневой сущности действие всегда Cascade
        $this->visit($root, $map, $visited, $stack, $order, [], DeleteAction::Cascade);

        return DeletionPlan::fromOrdered($root, $order);
    }

    /**
     * Рекурсивный обход в глубину (DFS).
     *
     * @param EntityClass        $current  Текущая сущность
     * @param RelationMap        $map      Граф связей
     * @param array<string, bool> $visited  Сущности, которые уже полностью обработаны
     * @param array<string, bool> $stack    Текущий путь обхода (для поиска циклов)
     * @param list<DeletionNode> $order    Список узлов в порядке удаления (post-order)
     * @param list<string>       $path     Человекочитаемый путь для логов
     * @param DeleteAction       $action   Действие, применяемое к текущей сущности
     *
     * @throws CyclicDependencyException
     *
     * @return void
     */
    private function visit(
        EntityClass $current,
        RelationMap $map,
        array &$visited,
        array &$stack,
        array &$order,
        array $path,
        DeleteAction $action,
    ): void {
        $key = $current->value;

        // Если сущность уже в текущем стеке — мы нашли цикл
        if (isset($stack[$key])) {
            throw CyclicDependencyException::forPath(array_keys($stack), $key);
        }

        // Если сущность уже обработана — пропускаем
        if (isset($visited[$key])) {
            return;
        }

        $stack[$key] = true;
        $path[] = $current->shortName();

        // Обходим всех детей
        foreach ($map->relationsFor($current) as $relation) {
            $nextAction = ($this->actionResolver)($relation);

            // Если связь блокирующая — добавляем узел в план как Block и не идем вглубь
            if ($nextAction === DeleteAction::Block) {
                $order[] = new DeletionNode(
                    entity: $relation->target,
                    action: DeleteAction::Block,
                    relationChain: [...$path, $relation->target->shortName()],
                );
                continue;
            }

            $this->visit($relation->target, $map, $visited, $stack, $order, $path, $nextAction);
        }

        unset($stack[$key]);
        $visited[$key] = true;

        // Добавляем в конец (post-order)
        $order[] = new DeletionNode(
            entity: $current,
            action: $action,
            relationChain: $path,
        );
    }
}
