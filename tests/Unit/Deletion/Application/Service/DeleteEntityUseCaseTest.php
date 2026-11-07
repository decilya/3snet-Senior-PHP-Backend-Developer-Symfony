<?php

declare(strict_types=1);


namespace App\Tests\Unit\Deletion\Application\Service;

use App\Deletion\Application\Command\DeleteEntityCommand;
use App\Deletion\Application\Service\DeleteEntityUseCase;
use App\Deletion\Domain\Exception\DeletionBlockedException;
use App\Deletion\Domain\Model\RelationMap;
use App\Deletion\Domain\Repository\DeletionEntityRepositoryInterface;
use App\Deletion\Domain\Repository\RelationMetadataRepositoryInterface;
use App\Deletion\Domain\Service\DeletionPlanner;
use App\Deletion\Domain\ValueObject\DeleteAction;
use App\Deletion\Domain\ValueObject\EntityClass;
use App\Deletion\Domain\ValueObject\RelationDescriptor;
use App\Deletion\Domain\ValueObject\RelationType;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * Тесты для сценария использования DeleteEntityUseCase.
 *
 * Проверяют оркестрацию доменных сервисов, транзакционность
 * и соблюдение политик безопасности (блокировок).
 */
#[CoversClass(DeleteEntityUseCase::class)]
final class DeleteEntityUseCaseTest extends TestCase
{
    /** @var EntityManagerInterface&MockObject */
    private EntityManagerInterface $em;

    /** @var RelationMetadataRepositoryInterface&MockObject */
    private RelationMetadataRepositoryInterface $metadataRepo;

    /** @var DeletionEntityRepositoryInterface&MockObject */
    private DeletionEntityRepositoryInterface $entityRepo;

    /**
     * Подготавливает моки перед каждым тестом.
     * @return void
     */
    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->metadataRepo = $this->createMock(RelationMetadataRepositoryInterface::class);
        $this->entityRepo = $this->createMock(DeletionEntityRepositoryInterface::class);
    }

    /**
     * Проверяет успешное выполнение сценария с каскадным удалением.
     *
     * @return void
     */
    public function testExecutesDeletionSuccessfully(): void
    {
        $entityClass = stdClass::class;
        $root = EntityClass::fromString($entityClass);
        $command = new DeleteEntityCommand($entityClass, [1, 2], false);

        $map = new RelationMap($root, []);

        // Используем реальный планировщик (он final, поэтому не мокается)
        $planner = new DeletionPlanner(static fn () => DeleteAction::Cascade);
        $useCase = new DeleteEntityUseCase($this->em, $planner, $this->metadataRepo, $this->entityRepo);

        $this->metadataRepo->expects($this->once())
            ->method('for')
            ->with($root)
            ->willReturn($map);

        $this->em->expects($this->once())
            ->method('wrapInTransaction')
            ->willReturnCallback(static fn (callable $callback) => $callback());

        $this->entityRepo->expects($this->once())
            ->method('deleteByIds')
            ->with($this->isInstanceOf(EntityClass::class), [1, 2])
            ->willReturn(2);

        $result = $useCase->execute($command);

        self::assertSame(2, $result->totalAffected());
        self::assertSame(['stdClass' => 2], $result->stats);
    }

    /**
     * Проверяет, что при блокировке выбрасывается исключение, если нет флага force.
     *
     * @return void
     */
    public function testThrowsExceptionWhenBlocked(): void
    {
        $entityClass = stdClass::class;
        $root = EntityClass::fromString($entityClass);
        $child = EntityClass::fromString(\ArrayObject::class);
        $command = new DeleteEntityCommand($entityClass, [1], false);

        // Создаем связь с ребенком
        $relation = new RelationDescriptor('child', $root, $child, RelationType::OneToMany, true);
        $map = new RelationMap($root, [$relation]);

        // Планировщик, который возвращает Block для этой связи
        $planner = new DeletionPlanner(static fn () => DeleteAction::Block);
        $useCase = new DeleteEntityUseCase($this->em, $planner, $this->metadataRepo, $this->entityRepo);

        // Явно указываем ожидания, чтобы PHPUnit 12 не ругался
        $this->metadataRepo->expects($this->once())->method('for')->willReturn($map);
        $this->em->expects($this->once())->method('wrapInTransaction')
            ->willReturnCallback(static fn (callable $callback) => $callback());

        // Репозиторий сущностей не должен вызываться, так как удаление заблокировано
        $this->entityRepo->expects($this->never())->method($this->anything());

        $this->expectException(DeletionBlockedException::class);
        $this->expectExceptionMessageMatches('/Удаление заблокировано/');

        $useCase->execute($command);
    }
}
