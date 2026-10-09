<?php

declare(strict_types=1);

namespace Contenir\Commerce\Order;

use Contenir\Commerce\Exception\InvalidArgumentException;
use Contenir\Commerce\Exception\OverflowException;
use Contenir\Commerce\Money\Money;

use function sprintf;

/**
 * A cart line at the moment of purchase: a quantity of one item variant.
 * Title, variant label, description and unit price are snapshots: the
 * order must stay accurate if the item later changes. Build the price from
 * the variant row on the server, never from the request.
 *
 * @api
 */
final readonly class PurchaseItem
{
    /**
     * @param string|null $variantLabel The variant's label, such as "A3"; null for an item's only variant.
     * @param string|null $description  A line shown under the title at checkout, such as the artist's name.
     *
     * @throws InvalidArgumentException When the quantity is below 1.
     *
     * @mago-expect lint:excessive-parameter-list One parameter per snapshot field of an order line.
     */
    public function __construct(
        public int $itemVariantId,
        public string $title,
        public Money $unitPrice,
        public int $quantity = 1,
        public ?string $variantLabel = null,
        public ?string $description = null,
    ) {
        if ($quantity < 1) {
            throw new InvalidArgumentException(sprintf('Purchase item quantity must be at least 1, got %d', $quantity));
        }
    }

    /**
     * @throws OverflowException When the total exceeds the integer range of cents.
     */
    public function getTotal(): Money
    {
        return $this->unitPrice->multiply($this->quantity);
    }
}
