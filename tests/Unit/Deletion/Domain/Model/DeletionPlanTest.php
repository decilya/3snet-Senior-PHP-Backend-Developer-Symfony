<?php

declare(strict_types=1);

namespace App\Tests\Unit\Deletion\Domain\Model;

use App\Deletion\Domain\Model\DeletionNode;
use App\Deletion\Domain\Model\DeletionPlan;
use App\Deletion\Domain\ValueObject\DeleteAction;
use App\Deletion\Domain\ValueObject\EntityClass;
use DomainException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * Тесты инвариантов агрегата DeletionPlan.
 */
#[CoversClass(DeletionPlan::class)]
final class DeletionPlanTest extends TestCase
{
    /**
     * Проверяет успешное создание плана удаления.
     * @return void
     */
    public function testCreatesValidPlan(): void
    {
        $child = EntityClass::fromString(\ArrayObject::class);
        $root  = EntityClass::fromString(stdClass::class);

        $nodes = [
            new DeletionNode($child, DeleteAction::Cascade, ['stdClass', 'ArrayObject']),
            new DeletionNode($root, DeleteAction::Cascade, ['stdClass']),
        ];

        $plan = DeletionPlan::fromOrdered($root, $nodes);

        self::assertSame($root, $plan->root);
        self::assertCount(2, $plan->nodes());
        self::assertSame(2, $plan->count());
    }

    /**
     * Проверяет, что выбрасывается исключение, если корневая сущность не последняя.
     * @return void
     */
    public function testThrowsExceptionIfRootIsNotLast(): void
    {
        $child = EntityClass::fromString(\ArrayObject::class);
        $root  = EntityClass::fromString(stdClass::class);

        // Нарушаем порядок: корень идет первым
        $nodes = [
            new DeletionNode($root, DeleteAction::Cascade, ['stdClass']),
            new DeletionNode($child, DeleteAction::Cascade, ['stdClass', 'ArrayObject']),
        ];

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Корневая сущность должна быть последней');

        DeletionPlan::fromOrdered($root, $nodes);
    }

    /**
     * Проверяет, что выбрасывается исключение при пустом списке узлов.
     * @return void
     */
    public function testThrowsExceptionIfEmpty(): void
    {
        $root = EntityClass::fromString(stdClass::class);

        $this->expectException(DomainException::class);

        DeletionPlan::fromOrdered($root, []);
    }
}
