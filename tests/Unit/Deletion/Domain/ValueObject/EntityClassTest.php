<?php

declare(strict_types=1);

namespace App\Tests\Unit\Deletion\Domain\ValueObject;

use App\Deletion\Domain\ValueObject\EntityClass;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * Тесты для Value Object EntityClass.
 */
#[CoversClass(EntityClass::class)]
final class EntityClassTest extends TestCase
{
    /**
     * Проверяет создание VO из существующего класса.
     * @return void
     */
    public function testCreatesFromExistingClass(): void
    {
        $vo = EntityClass::fromString(stdClass::class);

        self::assertSame(stdClass::class, $vo->value);
        self::assertSame('stdClass', $vo->shortName());
    }

    /**
     * Проверяет, что префикс прокси Doctrine отсекается.
     * @return void
     */
    public function testStripsDoctrineProxyPrefix(): void
    {
        $proxied = 'Proxies\\__CG__\\' . stdClass::class;
        $vo = EntityClass::fromString($proxied);

        self::assertSame(stdClass::class, $vo->value);
    }

    /**
     * Проверяет выбрасывание исключения, если класс не найден.
     * @return void
     */
    public function testThrowsOnUnknownClass(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/не найден/');

        EntityClass::fromString('App\\Entity\\DoesNotExist');
    }

    /**
     * Проверяет сравнение объектов по значению.
     * @return void
     */
    public function testEqualityByValue(): void
    {
        $a = EntityClass::fromString(stdClass::class);
        $b = EntityClass::fromString('\\' . stdClass::class);

        self::assertTrue($a->equals($b));
    }
}
