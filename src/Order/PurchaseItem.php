<?php

declare(strict_types=1);

namespace Contenir\Commerce\Order;

use Contenir\Commerce\Money\Money;

/**
 * A cart line at the moment of purchase. Title, artist and price are
 * snapshots: the order must stay accurate if the artwork later changes.
 * Build the price from the artwork row on the server, never from the
 * request.
 *
 * @api
 */
final readonly class PurchaseItem
{
    public function __construct(
        public int $artworkId,
        public string $title,
        public Money $price,
        public ?string $artistName = null,
    ) {}
}
