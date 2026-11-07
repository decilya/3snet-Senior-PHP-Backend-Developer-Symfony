<?php

declare(strict_types=1);

namespace App\Tests\Unit\Deletion\Domain\Policy;

use App\Deletion\Domain\Policy\RelationPolicy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Тесты для доменной политики RelationPolicy.
 *
 * Проверяют инварианты: возможность удаления сущности
 * в зависимости от флага блокировки и принудительного удаления.
 */
#[CoversClass(RelationPolicy::class)]
final class RelationPolicyTest extends TestCase
{
    /**
     * Проверяет, что сущность можно удалить, если она не заблокирована.
     * @return void
     */
    public function testCanDeleteWhenNotBlocked(): void
    {
        $policy = new RelationPolicy(blocksCurrentDeletion: false);

        self::assertTrue($policy->canDelete(force: false));
    }

    /**
     * Проверяет, что сущность нельзя удалить, если она заблокирована
     * и флаг force не передан.
     * @return void
     */
    public function testCannotDeleteWhenBlockedAndNoForce(): void
    {
        $policy = new RelationPolicy(blocksCurrentDeletion: true);

        self::assertFalse($policy->canDelete(force: false));
    }

    /**
     * Проверяет, что заблокированную сущность можно удалить,
     * если передан флаг force = true.
     * @return void
     */
    public function testCanDeleteWhenBlockedButForced(): void
    {
        $policy = new RelationPolicy(blocksCurrentDeletion: true);

        self::assertTrue($policy->canDelete(force: true));
    }
}
