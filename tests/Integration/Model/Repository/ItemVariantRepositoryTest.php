<?php

declare(strict_types=1);

namespace Contenir\Commerce\Tests\Integration\Model\Repository;

use Contenir\Commerce\Model\Entity\AbstractItemVariantEntity;
use Contenir\Commerce\Model\Repository\ItemVariantRepository;
use Contenir\Commerce\Tests\Trait\SqliteDatabaseTrait;
use Contenir\Db\Model\Exception\PersistenceException;
use Contenir\Db\Model\Type\TypeRegistry;
use DateTimeImmutable;
use Override;
use PhpDb\Adapter\AdapterInterface;
use PhpDb\Adapter\Driver\DriverInterface;
use PhpDb\Adapter\Driver\StatementInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_map;

/**
 * Variants 1 to 3 belong to item 1: an original (one unit), an edition of
 * five, and an untracked print-on-demand version. Variant 4 belongs to
 * item 2 and is sold out.
 */
#[Group('integration')]
#[Group('repository')]
final class ItemVariantRepositoryTest extends TestCase
{
    use SqliteDatabaseTrait;

    private ItemVariantRepository $repository;

    /**
     * @return array<string, array{int, int}>
     */
    public static function unclaimableProvider(): array
    {
        return [
            'more than remain' => [2, 6],
            'sold out'         => [4, 1],
            'untracked stock'  => [3, 1],
            'never existed'    => [999, 1],
            'deleted (id 0)'   => [0, 1],
        ];
    }

    #[Test]
    public function aClaimLeavesTheManagedEntityForFindCurrentToReread(): void
    {
        $held = $this->repository->find(2);

        $this->repository->claim(2, 2, new DateTimeImmutable('2026-08-20 10:20:00'));

        static::assertSame([5, 3], [$held?->stock, $this->repository->findCurrent(2)?->stock]);
    }

    #[Test]
    public function aClaimSellsTheLastUnitExactlyOnce(): void
    {
        $at = new DateTimeImmutable('2026-08-20 10:20:00');

        static::assertSame(
            [true, false, ['stock' => 0, 'updated' => '2026-08-20 10:20:00']],
            [
                $this->repository->claim(1, 1, $at),
                $this->repository->claim(1, 1, $at),
                $this->stockAndUpdated(1),
            ],
        );
    }

    #[Test]
    public function aClaimTakesItsQuantityWhileEnoughRemain(): void
    {
        $at = new DateTimeImmutable('2026-08-20 10:20:00');

        static::assertSame(
            [true, true, false, ['stock' => 0, 'updated' => '2026-08-20 10:20:00']],
            [
                $this->repository->claim(2, 3, $at),
                $this->repository->claim(2, 2, $at),
                $this->repository->claim(2, 1, $at),
                $this->stockAndUpdated(2),
            ],
        );
    }

    #[Test]
    public function aClaimTheDriverReturnsNoResultForIsAnError(): void
    {
        $statement = static::createStub(StatementInterface::class);
        $statement->method('execute')->willReturn(null);
        $driver = static::createStub(DriverInterface::class);
        $driver->method('createStatement')->willReturn($statement);
        $adapter = static::createStub(AdapterInterface::class);
        $adapter->method('getDriver')->willReturn($driver);
        $adapter->method('getPlatform')->willReturn($this->adapter->getPlatform());

        $this->expectException(PersistenceException::class);
        $this->expectExceptionMessage('The database driver returned no result for a write statement');

        (new ItemVariantRepository($this->em, $adapter, TypeRegistry::withDefaults()))->claim(
            1,
            1,
            new DateTimeImmutable('2026-08-20 10:20:00'),
        );
    }

    #[Test]
    public function aClaimTouchesOnlyItsOwnVariant(): void
    {
        $this->repository->claim(1, 1, new DateTimeImmutable('2026-08-20 10:20:00'));

        static::assertSame(['stock' => 5, 'updated' => null], $this->stockAndUpdated(2));
    }

    #[DataProvider('unclaimableProvider')]
    #[Test]
    public function aVariantWithoutEnoughTrackedStockCannotBeClaimed(int $itemVariantId, int $quantity): void
    {
        static::assertFalse(
            $this->repository->claim($itemVariantId, $quantity, new DateTimeImmutable('2026-08-20 10:20:00')),
        );
    }

    #[Test]
    public function findCurrentRereadsAVariantTheManagerAlreadyHolds(): void
    {
        $held = $this->repository->find(2);
        $this->updateBehindTheManager('UPDATE item_variant SET stock = 1 WHERE item_variant_id = 2');

        $current = $this->repository->findCurrent(2);

        static::assertSame([$held, 1], [$current, $current?->stock]);
    }

    #[Test]
    public function findCurrentReturnsNullForAHeldVariantWhoseRowIsGone(): void
    {
        $this->repository->find(2);
        $this->updateBehindTheManager('DELETE FROM item_variant WHERE item_variant_id = 2');

        static::assertNull($this->repository->findCurrent(2));
    }

    #[Test]
    public function findCurrentReturnsNullForAMissingVariant(): void
    {
        static::assertNull($this->repository->findCurrent(999));
    }

    #[Test]
    public function findsAnItemsVariantsInSequence(): void
    {
        static::assertSame(
            [3, 1, 2],
            array_map(
                static fn(AbstractItemVariantEntity $variant): ?int => $variant->itemVariantId,
                $this->repository->findByItemId(1),
            ),
        );
    }

    #[Test]
    public function mapsEveryColumn(): void
    {
        $this->insert('item_variant', [
            'item_variant_id' => 50,
            'item_id'         => 7,
            'label'           => 'A2',
            'sku'             => 'HD-A2',
            'price'           => 3_500,
            'stock'           => 12,
            'sequence'        => 4,
            'created'         => '2026-08-01 09:00:00',
            'updated'         => '2026-08-02 10:30:00',
        ]);

        $variant = $this->repository->find(50);

        static::assertEquals(
            [
                50,
                7,
                'A2',
                'HD-A2',
                3_500,
                12,
                4,
                new DateTimeImmutable('2026-08-01 09:00:00'),
                new DateTimeImmutable('2026-08-02 10:30:00'),
            ],
            [
                $variant?->itemVariantId,
                $variant?->itemId,
                $variant?->label,
                $variant?->sku,
                $variant?->price,
                $variant?->stock,
                $variant?->sequence,
                $variant?->created,
                $variant?->updated,
            ],
        );
    }

    #[Override]
    protected function setUp(): void
    {
        $this->setUpDatabase();
        $this->repository = new ItemVariantRepository($this->em, $this->adapter, TypeRegistry::withDefaults());

        $fixtures = [
            ['item_id' => 1, 'label' => 'Original', 'stock' => 1, 'sequence' => 2],
            ['item_id' => 1, 'label' => 'Edition', 'stock' => 5, 'sequence' => 2],
            ['item_id' => 1, 'label' => 'Print on demand', 'stock' => null, 'sequence' => 1],
            ['item_id' => 2, 'label' => null, 'stock' => 0, 'sequence' => 0],
        ];

        foreach ($fixtures as $fixture) {
            $this->insert('item_variant', [...$fixture, 'price' => 100_000]);
        }
    }

    /**
     * @return array{stock: mixed, updated: mixed}
     */
    private function stockAndUpdated(int $itemVariantId): array
    {
        $row = $this->row('item_variant', 'item_variant_id', $itemVariantId);

        return ['stock' => $row['stock'], 'updated' => $row['updated']];
    }
}
