<?php

declare(strict_types=1);

namespace App\Tests\Unit\Deletion\Domain\Service;

use App\Deletion\Domain\Exception\CyclicDependencyException;
use App\Deletion\Domain\Model\RelationMap;
use App\Deletion\Domain\Service\DeletionPlanner;
use App\Deletion\Domain\ValueObject\DeleteAction;
use App\Deletion\Domain\ValueObject\EntityClass;
use App\Deletion\Domain\ValueObject\RelationDescriptor;
use App\Deletion\Domain\ValueObject\RelationType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * Тесты для доменного сервиса DeletionPlanner.
 */
#[CoversClass(DeletionPlanner::class)]
final class DeletionPlannerTest extends TestCase
{
    private DeletionPlanner $planner;

    /**
     * Подготавливает планировщик перед каждым тестом.
     * @return void
     */
    protected function setUp(): void
    {
        // Все связи по умолчанию — каскадные
        $resolver = static fn (RelationDescriptor $rel) => DeleteAction::Cascade;
        $this->planner = new DeletionPlanner($resolver);
    }

    /**
     * Проверяет, что дети удаляются раньше родителей.
     * @return void
     */
    public function testBuildsPlanInCorrectTopologicalOrder(): void
    {
        $root  = EntityClass::fromString(stdClass::class);
        $child = EntityClass::fromString(\ArrayObject::class);

        // Root -> Child
        $rel = new RelationDescriptor('items', $root, $child, RelationType::OneToMany, true);

        $map = new RelationMap($root, [$rel]);

        $plan = $this->planner->plan($root, $map);
        $nodes = $plan->nodes();

        // В плане 2 узла
        self::assertCount(2, $nodes);
        // Первым должен идти Child
        self::assertTrue($nodes[0]->entity->equals($child));
        // Последним — Root
        self::assertTrue($nodes[1]->entity->equals($root));
    }

    /**
     * Проверяет обнаружение циклической зависимости (A -> B -> A).
     * @return void
     */
    public function testThrowsExceptionOnCyclicDependency(): void
    {
        $a = EntityClass::fromString(stdClass::class);
        $b = EntityClass::fromString(\ArrayObject::class);

        // A -> B
        $relAtoB = new RelationDescriptor('b', $a, $b, RelationType::OneToOne, true);
        // B -> A
        $relBtoA = new RelationDescriptor('a', $b, $a, RelationType::OneToOne, true);

        $map = new RelationMap($a, [$relAtoB, $relBtoA]);

        $this->expectException(CyclicDependencyException::class);
        $this->expectExceptionMessageMatches('/Обнаружена циклическая зависимость/');

        $this->planner->plan($a, $map);
    }
}
