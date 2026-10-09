<?php

declare(strict_types=1);

namespace Contenir\Commerce\Order;

use Contenir\Commerce\Exception\ItemUnavailableException;
use Contenir\Commerce\Model\Entity\AbstractItemEntity;
use Contenir\Commerce\Model\Entity\AbstractItemVariantEntity;
use Contenir\Commerce\Model\Repository\ItemRepository;
use Contenir\Commerce\Model\Repository\ItemVariantRepository;
use Contenir\Db\Model\Exception\ExceptionInterface as DbModelException;
use DateTimeImmutable;

/**
 * Availability of the items in an order, always read from the database,
 * and the claim that takes the units sold out of stock.
 *
 * @internal
 */
final readonly class ItemInventory
{
    public function __construct(
        private ItemRepository $items,
        private ItemVariantRepository $variants,
    ) {}

    /**
     * @param list<PurchaseItem> $items
     *
     * @throws ItemUnavailableException When an item has sold out, been unlisted or been deleted.
     * @throws DbModelException
     */
    public function assertAvailable(array $items): void
    {
        $this->available($items);
    }

    /**
     * Each purchase item with its variant and item as currently stored,
     * when every item is listed and its variant has the quantity in stock.
     *
     * @param list<PurchaseItem> $items
     *
     * @return list<array{PurchaseItem, AbstractItemVariantEntity, AbstractItemEntity}>
     *
     * @throws ItemUnavailableException When an item has sold out, been unlisted or been deleted.
     * @throws DbModelException
     */
    public function available(array $items): array
    {
        $listed = [];
        $lost   = [];
        foreach ($items as $item) {
            $variant       = $this->variants->findCurrent($item->itemVariantId);
            $catalogueItem = null === $variant ? null : $this->items->findCurrent($variant->itemId);
            if (
                null === $variant
                || null === $catalogueItem
                || ! $catalogueItem->isListed()
                || ! $variant->hasStock($item->quantity)
            ) {
                $lost[] = $item->title;
                continue;
            }

            $listed[] = [$item, $variant, $catalogueItem];
        }

        return [] === $lost ? $listed : throw ItemUnavailableException::forTitles($lost);
    }

    /**
     * Claims each item's quantity from its variant's stock, returning the
     * titles of the items that could not be claimed. Variants whose stock
     * is not tracked are always claimed; deleted ones never are. Run inside
     * a transaction and roll back when anything is lost, so that the
     * claims that succeeded are returned.
     *
     * @param list<PurchaseItem> $items
     *
     * @return list<string>
     *
     * @throws DbModelException
     */
    public function claim(array $items, DateTimeImmutable $at): array
    {
        $lost = [];
        foreach ($items as $item) {
            $variant = $this->variants->find($item->itemVariantId);
            if (null === $variant) {
                $lost[] = $item->title;
                continue;
            }

            if (! $variant->isStockTracked() || $this->variants->claim($item->itemVariantId, $item->quantity, $at)) {
                continue;
            }

            $lost[] = $item->title;
        }

        return $lost;
    }

    /**
     * Re-reads the items' variants, so that entities the EntityManager
     * already holds show the stock their claims left.
     *
     * @param list<PurchaseItem> $items
     *
     * @throws DbModelException
     */
    public function refresh(array $items): void
    {
        foreach ($items as $item) {
            $this->variants->findCurrent($item->itemVariantId);
        }
    }
}
