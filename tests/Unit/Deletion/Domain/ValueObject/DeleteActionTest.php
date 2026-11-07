<?php

declare(strict_types=1);

namespace App\Tests\Unit\Deletion\Domain\ValueObject;

use App\Deletion\Domain\ValueObject\DeleteAction;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Тесты для enum DeleteAction.
 *
 * Проверяют корректность определения мутирующих действий,
 * которые изменяют данные в базе данных.
 */
#[CoversClass(DeleteAction::class)]
final class DeleteActionTest extends TestCase
{
    /**
     * Проверяет, что действие Block не изменяет данные в БД.
     * @return void
     */
    public function testBlockDoesNotMutateData(): void
    {
        self::assertFalse(DeleteAction::Block->mutatesData());
    }

    /**
     * Проверяет, что каскадное удаление изменяет данные в БД.
     * @return void
     */
    public function testCascadeMutatesData(): void
    {
        self::assertTrue(DeleteAction::Cascade->mutatesData());
    }

    /**
     * Проверяет, что отсоединение (Detach) изменяет данные в БД.
     * @return void
     */
    public function testDetachMutatesData(): void
    {
        self::assertTrue(DeleteAction::Detach->mutatesData());
    }

    /**
     * Проверяет, что мягкое удаление (SoftDelete) изменяет данные в БД.
     * @return void
     */
    public function testSoftDeleteMutatesData(): void
    {
        self::assertTrue(DeleteAction::SoftDelete->mutatesData());
    }
}
