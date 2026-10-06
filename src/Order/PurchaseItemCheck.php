<?php

declare(strict_types=1);

namespace Contenir\Commerce\Order;

use Contenir\Commerce\Exception\InvalidArgumentException;
use Contenir\Commerce\Exception\PurchaseItemMismatchException;
use Contenir\Commerce\Model\Entity\AbstractArtworkEntity;

use function in_array;
use function sprintf;

/**
 * Checks the items of a new order against each other and against their
 * artworks: each original can be sold once, so a work may appear in an
 * order once, and a caller's price or title is never trusted.
 *
 * @internal
 */
final readonly class PurchaseItemCheck
{
    /**
     * @param list<PurchaseItem> $items
     *
     * @throws InvalidArgumentException When a work appears more than once.
     */
    public function assertDistinct(array $items): void
    {
        $seen = [];
        foreach ($items as $item) {
            if (in_array($item->artworkId, $seen, strict: true)) {
                throw new InvalidArgumentException(sprintf('"%s" appears in the order more than once', $item->title));
            }

            $seen[] = $item->artworkId;
        }
    }

    /**
     * Each item must carry its artwork's stored price and, when the artwork
     * maps a title (AbstractArtworkEntity::getTitle()), its title.
     *
     * @param list<array{PurchaseItem, AbstractArtworkEntity}> $listed
     *
     * @throws PurchaseItemMismatchException When an item's price or title differs from its artwork's.
     * @throws InvalidArgumentException When a stored price is negative.
     */
    public function assertListed(array $listed): void
    {
        foreach ($listed as [$item, $artwork]) {
            $price = $artwork->getPrice();
            if (! $price->equals($item->price)) {
                throw PurchaseItemMismatchException::forPrice($item, $price);
            }

            $title = $artwork->getTitle();
            if (null !== $title && $title !== $item->title) {
                throw PurchaseItemMismatchException::forTitle($item, $title);
            }
        }
    }
}
