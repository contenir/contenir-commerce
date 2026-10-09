<?php

declare(strict_types=1);

namespace Contenir\Commerce\Tests\Integration\Model\Repository;

use Contenir\Commerce\Item\ItemStatus;
use Contenir\Commerce\Model\Entity\AbstractItemEntity;
use Contenir\Commerce\Model\Entity\AbstractItemVariantEntity;
use Contenir\Commerce\Model\Entity\ItemEntity;
use Contenir\Commerce\Model\Repository\ItemRepository;
use Contenir\Commerce\Tests\Trait\SqliteDatabaseTrait;
use DateTimeImmutable;
use Override;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_map;

#[Group('integration')]
#[Group('repository')]
final class ItemRepositoryTest extends TestCase
{
    use SqliteDatabaseTrait;

    private ItemRepository $repository;

    #[Test]
    public function findCurrentRereadsAnItemTheManagerAlreadyHolds(): void
    {
        $held = $this->repository->find(1);
        $this->updateBehindTheManager("UPDATE item SET status = 'unlisted' WHERE item_id = 1");

        $current = $this->repository->findCurrent(1);

        static::assertSame([$held, ItemStatus::Unlisted], [$current, $current?->status]);
    }

    #[Test]
    public function findCurrentReturnsNullForAHeldItemWhoseRowIsGone(): void
    {
        $this->repository->find(1);
        $this->updateBehindTheManager('DELETE FROM item WHERE item_id = 1');

        static::assertNull($this->repository->findCurrent(1));
    }

    #[Test]
    public function findCurrentReturnsNullForAMissingItem(): void
    {
        static::assertNull($this->repository->findCurrent(999));
    }

    #[Test]
    public function findsTheListedItemsOldestFirst(): void
    {
        static::assertSame(
            [1, 3],
            array_map(static fn(AbstractItemEntity $item): ?int => $item->itemId, $this->repository->findListed()),
        );
    }

    #[Test]
    public function itsVariantsLoadInSequence(): void
    {
        $this->insert('item_variant', ['item_id' => 1, 'label' => 'B', 'price' => 1, 'sequence' => 2]);
        $this->insert('item_variant', ['item_id' => 1, 'label' => 'A', 'price' => 1, 'sequence' => 2]);
        $this->insert('item_variant', ['item_id' => 1, 'label' => 'C', 'price' => 1, 'sequence' => 1]);

        $item = $this->repository->find(1);

        static::assertSame(
            ['C', 'B', 'A'],
            array_map(
                static fn(AbstractItemVariantEntity $variant): ?string => $variant->label,
                [...($item->variants ?? [])],
            ),
        );
    }

    #[Test]
    public function mapsEveryColumn(): void
    {
        $this->insert('item', [
            'item_id'     => 50,
            'title'       => 'Tote bag',
            'description' => 'Screen printed',
            'status'      => 'unlisted',
            'created'     => '2026-08-01 09:00:00',
            'updated'     => '2026-08-02 10:30:00',
        ]);

        $item = $this->repository->find(50);

        static::assertEquals(
            [
                50,
                'Tote bag',
                'Screen printed',
                ItemStatus::Unlisted,
                new DateTimeImmutable('2026-08-01 09:00:00'),
                new DateTimeImmutable('2026-08-02 10:30:00'),
            ],
            [$item?->itemId, $item?->title, $item?->description, $item?->status, $item?->created, $item?->updated],
        );
    }

    #[Test]
    public function newEntitiesAreTheConfiguredClass(): void
    {
        static::assertInstanceOf(ItemEntity::class, $this->repository->newEntity());
    }

    #[Override]
    protected function setUp(): void
    {
        $this->setUpDatabase();
        $this->repository = new ItemRepository($this->em);

        foreach (['Zebra Finch' => 'listed', 'Moonah' => 'unlisted', 'Apple Gum' => 'listed'] as $title => $status) {
            $this->insert('item', ['title' => $title, 'status' => $status]);
        }
    }
}
