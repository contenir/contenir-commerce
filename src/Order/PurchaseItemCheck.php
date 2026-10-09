<?php

declare(strict_types=1);

namespace Contenir\Commerce\Order;

use Contenir\Commerce\Exception\InvalidArgumentException;
use Contenir\Commerce\Exception\PurchaseItemMismatchException;
use Contenir\Commerce\Model\Entity\AbstractItemEntity;
use Contenir\Commerce\Model\Entity\AbstractItemVariantEntity;

use function in_array;
use function sprintf;

/**
 * Checks the items of a new order against each other and against their
 * stored variants: a variant appears in an order once, with its quantity,
 * and a caller's price, title or label is never trusted.
 *
 * @internal
 */
final readonly class PurchaseItemCheck
{
    /**
     * @param list<PurchaseItem> $items
     *
     * @throws InvalidArgumentException When a variant appears more than once.
     */
    public function assertDistinct(array $items): void
    {
        $seen = [];
        foreach ($items as $item) {
            if (in_array($item->itemVariantId, $seen, strict: true)) {
                throw new InvalidArgumentException(sprintf('"%s" appears in the order more than once', $item->title));
            }

            $seen[] = $item->itemVariantId;
        }
    }

    /**
     * Each item's unit price must equal its variant's, and its title and
     * variant label must equal the item's title and the variant's label
     * where those are set.
     *
     * @param list<array{PurchaseItem, AbstractItemVariantEntity, AbstractItemEntity}> $listed
     *
     * @throws PurchaseItemMismatchException When an item's price, title or label differs from the stored one.
     * @throws InvalidArgumentException When a stored price is negative.
     */
    public function assertListed(array $listed): void
    {
        foreach ($listed as [$item, $variant, $catalogueItem]) {
            $price = $variant->getPrice();
            if (! $price->equals($item->unitPrice)) {
                throw PurchaseItemMismatchException::forPrice($item, $price);
            }

            $title = $catalogueItem->getTitle();
            if (null !== $title && $title !== $item->title) {
                throw PurchaseItemMismatchException::forTitle($item, $title);
            }

            $label = $variant->label;
            if (null !== $label && $label !== $item->variantLabel) {
                throw PurchaseItemMismatchException::forLabel($item, $label);
            }
        }
    }
}
